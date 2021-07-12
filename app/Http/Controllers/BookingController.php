<?php

namespace App\Http\Controllers;

use App\User;
use App\Booking;
use App\Invoice;
use App\Package;
use App\InvoiceItem;
use App\PackageAccept;
use App\Mail\BookingMail;
use Illuminate\Http\Request;
use App\BookingPackageFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use Illuminate\Support\Facades\Mail;
use App\Helpers\Factories\SMSFactory;
use App\Mail\NotificationBookingMail;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class BookingController extends Controller
{
    use AuthenticatesUsers;

    // صفحة الحجز الرئيسية
    public function index(Request $request, Package $package)
    {
        $locale = LaravelLocalization::getCurrentLocale();

        $package->load(['Type' => function ($query) use ($locale) {
            $query->select('id', DB::raw('name_' . $locale . ' as name'));
        }])->load(['RoomType' => function ($query) use ($locale) {
            $query->select('id', DB::raw('name_' . $locale . ' as name'));
        }])->load(['Hotel' => function ($query) use ($locale) {
            $query->select('id', 'stars_count', DB::raw('title_' . $locale . ' as title'), DB::raw('address_' . $locale . ' as address'), 'distance_from_haram', 'distance_from_kaaba', 'distance_from_jamarat', 'title_ar', 'map_cord');
        }])->load(['UnitMeasure' => function ($query) use ($locale) {
            $query->select('id', DB::raw('name_' . $locale . ' as name'));
        }])->load(['Times' => function ($query) {
            $now = date('Y-m-d');
            $query->whereRaw("date('" . $now . "') between start_date and end_date")->limit(1)->orderBy('id', 'asc');
        }]);

        if (!auth()->user() || !auth()->user()->is_only_hotel_operator) {
            $package->onlyActive();
        }

        $is_pay_page = ($request->is_pay && session()->has('booking_' . $package->id . '.phone')) ? true : false;
        // حساب مجموع الدفع
        $selected_features = session('booking_' . $package->id . '.selected_features');
        $PayAmount = 0;
        if ($is_pay_page && is_array($selected_features) && count($selected_features)) {
            $package->with(['Features' => function ($query) use ($selected_features) {
                $query->whereIn('id', array_keys($selected_features));
            }]);
        }
        
        // Set Times
        foreach ($package->Times as $time) {
            if ($time->inventory > 0)
                $package->inventory = $time->inventory;

            $package->downpayment_amount = $time->downpayment_amount;
            $package->upon_arrival_amount = $time->upon_arrival_amount;
        }
        
        if ($is_pay_page) {
            if ($package->checkBeforePay == 1) {
                if (!Invoice::where("package_id", $package->id)->where("traveller_id", auth()->check() ? auth()->User()->id : session('booking_' . $package->id . '.user_id'))->count()) {
                    $selected_features = session('booking_' . $package->id . '.selected_features');
                    if (is_array($selected_features) && count($selected_features)) {
                        $package->with(['Features' => function ($qu) use ($selected_features) {
                            $qu->whereIn('id', array_keys($selected_features));
                        }]);
                    }

                    $rooms = session('booking_' . $package->id . '.rooms');
                    $Invoice = new Invoice;
                    $Invoice->invoice_hash = str_random(12);
                    $Invoice->name = session('booking_' . $package->id . '.name');
                    $Invoice->phone = session('booking_' . $package->id . '.phone');
                    $Invoice->email = session('booking_' . $package->id . '.email');
                    $Invoice->check_in_date = session('booking_' . $package->id . '.check_in_date');
                    $Invoice->check_out_date = session('booking_' . $package->id . '.check_out_date');
                    $Invoice->payment_method = 'online';
                    $Invoice->user_id = $package->User->id;
                    $Invoice->hotel_id = $package->hotel_id;
                    $Invoice->notes = session('booking_' . $package->id . '.notes');
                    $Invoice->created_by_name = $package->User->name;
                    $Invoice->created_by_phone = $package->User->phone;
                    $Invoice->vat_tax = 0;
                    $Invoice->municipal_tax = 0;
                    $Invoice->admin_tax = 0;
                    $Invoice->package_id = $package->id;
                    $Invoice->traveller_id = auth()->check() ? auth()->User()->id : session('booking_' . $package->id . '.user_id');
                    $Invoice->upon_arrival_amount = $package->upon_arrival_amount * $rooms;
                    $Invoice->downpayment_amount = $package->downpayment_amount * $rooms;
                    $Invoice->save();

                    $packageAccpet = new PackageAccept;
                    $packageAccpet->creator_id = $Invoice->user_id;
                    $packageAccpet->passenger_id = $Invoice->traveller_id;
                    $packageAccpet->package_id = $Invoice->package_id;
                    $packageAccpet->invoice_id = $Invoice->id;
                    $packageAccpet->status = 0;
                    $packageAccpet->save();

                    $extra_prices = 0;
                    if (is_array($selected_features) && count($selected_features)) {
                        foreach ($package->Features as $Feature) {
                            if (isset($selected_features[$Feature->id]) && $selected_features[$Feature->id]) {
                                $extra_prices += $Feature->price;
                            }
                        }
                    }
                    // اضافة اسعار المميزات الاضافية للعرض الى الدفعة المقدمة

                    // Delete Related Items
                    $subtotal = $Invoice->upon_arrival_amount + $Invoice->downpayment_amount;
                    $subtotal += ($extra_prices * $rooms);
                    $insertItems[] = [
                        'image' => $package->Hotel->Image->path,
                        'invoice_id' => $Invoice->id,
                        'room_type_id' => $package->room_type_id,
                        'price' => $Invoice->upon_arrival_amount + $Invoice->downpayment_amount,
                        'quantity' => $rooms,
                        'notes' => '',
                        'amount' => $subtotal
                    ];
                    InvoiceItem::insert($insertItems);
                    Invoice::where('id', $Invoice->id)->update(['subtotal' => $subtotal, 'total' => $subtotal]);

                    // // Update total values
                    // $vat_tax_amount = ($request->vat_tax) ? $subtotal*($request->vat_tax*0.01) : 0;
                    // $municipal_tax_amount = ($request->municipal_tax) ? $subtotal*($request->municipal_tax*0.01) : 0;
                    // $admin_tax_amount = ($request->admin_tax) ? $subtotal*($request->admin_tax*0.01) : 0;

                    // $total = $subtotal+$vat_tax_amount+$municipal_tax_amount+$admin_tax_amount;

                    $line1 = route('invoice-details', ['hash' => $Invoice->invoice_hash]);
                    $message = 'الرجاء التأكيد علي هذا الحجز \n' . $line1;
                    (new SMSFactory())->send($package->User->phone, $message);

                    $Invoice->package_name = $package->title_ar;
                    // Email
                    if ($package->User->email) {
                        Mail::to($package->User->email)->send(new NotificationBookingMail($Invoice));
                    }
                }
            }
            $sdate = strtotime(session('booking_' . $package->id . '.check_in_date'));
            $edate = strtotime(session('booking_' . $package->id . '.check_out_date'));
            $datediff = $edate - $sdate;
            $calculateNights = round($datediff / (60 * 60 * 24));

            $PayAmount = $package->downpayment_amount;
            if ($package->Features->count()) {
                foreach ($package->Features as $Feature) {
                    if (isset($selected_features[$Feature->id]) && $selected_features[$Feature->id]) {
                        $PayAmount = $PayAmount + $Feature->price;
                    }
                }
            }

            // If Payment Type Is Tap
            if (app('settings')->payment_provider == 'tap') {
                $curl = curl_init();
                // Generate Post Body
                $PostBody = new \stdClass();
                // Customer Object
                $PostBody->CustomerDC = new \stdClass();
                $PostBody->CustomerDC->Email = session('booking_' . $package->id . '.email');
                $PostBody->CustomerDC->Mobile = session('booking_' . $package->id . '.phone');
                $PostBody->CustomerDC->Name = session('booking_' . $package->id . '.name');
                // Product List
                $lstProductDC = new \stdClass();
                $lstProductDC->CurrencyCode = "SAR";
                $lstProductDC->ImgUrl = asset('/uploads/hotels/thumb_' . $package->image);
                $lstProductDC->Quantity = session('booking_' . $package->id . '.rooms');
                $lstProductDC->TotalPrice = ($PayAmount * $calculateNights) * session('booking_' . $package->id . '.rooms');
                $lstProductDC->UnitDesc = $package->title;
                $lstProductDC->UnitName = $package->Hotel->title;
                $lstProductDC->UnitID = $package->id;
                $lstProductDC->UnitPrice = $PayAmount;
                $lstProductDC->VndID = $package->Hotel->id;
                $PostBody->lstProductDC = array($lstProductDC);
                // Payment Options
                $lstGateWayDC = new \stdClass();
                $lstGateWayDC->Name = "ALL";
                $PostBody->lstGateWayDC = array($lstGateWayDC);
                // Merchant Options
                $PostBody->MerMastDC = new \stdClass();
                $PostBody->MerMastDC->AutoReturn = "Y";
                $PostBody->MerMastDC->ErrorURL = url("404");
                $PostBody->MerMastDC->LangCode = strtoupper(LaravelLocalization::getCurrentLocale());
                $PostBody->MerMastDC->MerchantID = app('settings')->payment_tap_merchant_id;
                $PostBody->MerMastDC->Password = app('settings')->payment_tap_password;
                $PostBody->MerMastDC->UserName = app('settings')->payment_tap_username;
                $PostBody->MerMastDC->ReferenceID = $package->id . '-' . rand(111111, 999999);
                $PostBody->MerMastDC->ReturnURL = route("pay-return");
                $PostBody->MerMastDC->HashString = $this->generateRequestHash($PostBody);
                // Convert It To Json
                $post = json_encode($PostBody);
                //Send The Request To Genertate Payment Request
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://www.gotapnow.com/TapWebConnect/Tap/WebPay/PaymentRequest",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => $post,
                    CURLOPT_HTTPHEADER => array(
                        "content-type: application/json"
                    ),
                ));

                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                $response = json_decode($response);
            }
            //End Payment Tap

            // create tabby session for this booking
            if ($PayAmount) {
                $payment = [
                    'amount' => ($package->total_amount * $calculateNights) * session('booking_' . $package->id . '.rooms'),
                    'currency' => 'SAR',
                    'description' => $package->title_ar,
                    'buyer' => [
                        'name' => session('booking_' . $package->id . '.name'),
                        'email' => session('booking_' . $package->id . '.email'),
                        'phone' => session('booking_' . $package->id . '.phone')
                    ],
                    'order' => ['reference_id' => (string)$package->id]
                ];
                $urls = ['success' => route("tabby-return"), 'cancel' => route('booking', ['package_id' => $package->id, 'is_pay' => 1]), 'failure' => url("404")];
                $tabby = app('App\Http\Controllers\TabbyController')->createSession($payment, $urls);
            }

            $PayAmount = ($PayAmount * $calculateNights) * session('booking_' . $package->id . '.rooms');
        }

        return view('site.booking', [
            'response' => $response ?? false,
            'Package' => $package,
            'is_pay_page' => $is_pay_page,
            'PayAmount' => $PayAmount,
            'tabby' => $tabby ?? false
        ]);
    }

    // Generate Hash For Tap Payment To Validate the request from our server or not
    public function generateRequestHash($PostBody)
    {
        $APIKey = app('settings')->payment_tap_api_key; //Your API Key Provided by Tap
        $MerchantID = $PostBody->MerMastDC->MerchantID;
        $UserName = $PostBody->MerMastDC->UserName;
        $ref = $PostBody->MerMastDC->ReferenceID; //This is a reference given by you while creating an invoice. (Details can be found in "Create a Payment" endpoint)
        $Mobile = $PostBody->CustomerDC->Mobile; //This is the mobile number for the customer you are sending the invoice to. (Details can be found in "Create a Payment" endpoint)
        $CurrencyCode = $PostBody->lstProductDC[0]->CurrencyCode; //This is the currency of the invoice you are creating. (Details can be found in "Create a Payment" endpoint)
        $Total = $PostBody->lstProductDC[0]->TotalPrice; //This is the total amount the customer is asked to pay in the invoice. (Details can be found in "Create a Payment" endpoint)
        $str = 'X_MerchantID' . $MerchantID . 'X_UserName' . $UserName . 'X_ReferenceID' . $ref . 'X_Mobile' . $Mobile . 'X_CurrencyCode' . $CurrencyCode . 'X_Total' . $Total . '';
        return hash_hmac('sha256', $str, $APIKey);
    }

    // Generate Hash For Tap Payment To Validate the request from Tap server or not
    public function generateResponseHash(Request $q)
    {
        // Get the passed data whether from query string or from the post body
        $Tap_Ref = $q->ref;
        $Txn_Result = $q->result;
        $Txn_OrderID = $q->trackid;

        // Create a hash string from the passed data + the data that are related to you.
        $APIKey = app('settings')->payment_tap_api_key; //Your API Key Provided by Tap
        $MerchantID = app('settings')->payment_tap_merchant_id; //Your ID provided by Tap
        $toBeHashedString = 'x_account_id' . $MerchantID . 'x_ref' . $Tap_Ref . 'x_result' . $Txn_Result . 'x_referenceid' . $Txn_OrderID . '';
        return hash_hmac('sha256', $toBeHashedString, $APIKey);
    }

    // هنا يتم حفظ تعديلات المستخدم في صفحة الحجز قبل النقر على زر الدخول ليتم استرجاعها بعد تسجيل الدخول
    public function apiPostStart(Request $q)
    {
        session(['booking_' . $q->package_id => [
            'package_id' => $q->package_id,
            'check_in_date' => date('Y-m-d', strtotime(substr($q->check_in_date, 0, 10))),
            'check_out_date' => date('Y-m-d', strtotime(substr($q->check_out_date, 0, 10))),
            'selected_features' => $q->selected_features
        ]]);
    }

    // بعد النقر على المتابعة للدفع في صفحة الحجز يتم تسجيل بيانات الحجز في جلسة
    public function apiPostSetPayInfo(Request $q)
    {
        // if (!auth()->user()) {
        //     abort(403);
        // }
        $q->validate([
            'rooms' => 'required',
            'package_id' => 'required',
            'check_out_date' => 'required',
            'check_in_date' => 'required'
        ]);

        $user = User::registerOrLogin($q->package_id, $q->email, $q->phone);

        session(['booking_' . $q->package_id => [
            'name' => $q->name,
            'user_id' => $user->id,
            'phone' => $q->phone,
            'email' => $q->email,
            'country' => $q->country,
            'notes' => $q->notes,
            'package_id' => $q->package_id,
            'check_in_date' => date('Y-m-d', strtotime(substr($q->check_in_date, 0, 10))),
            'check_out_date' => date('Y-m-d', strtotime(substr($q->check_out_date, 0, 10))),
            'rooms' => $q->rooms,
            'selected_features' => $q->selected_features
        ]]);
    }

    // paytabs provider return...
    public function paytabsReturn(Request $request)
    {
        $package_id = $request->order_id;

        if ( $request->response_code !== '100' ) {
            // الرجوع الى صفحة الحجز مع تنبيه بوجود خطأ
            return redirect()
                ->route('booking', ['package_id' => $package_id, 'is_pay' => 1])
                ->withErrors(['msg' => $request->response_code]);
        }

        $payment = [
            'provider' => 'paytabs',
            'payment_transaction_no' => $request->transaction_id,
            'payment_amount' => $request->transaction_amount,
        ];

        $booking = $this->createBooking($package_id, $payment);

        // التحويل الى صفحة حجوزاتي
        return redirect()->route('account-booking', $booking->id);
    }

    // tap provider return...
    public function tapReturn(Request $request)
    {
        $package_id = explode('-', $request->trackid)[0];

        // Check if Payment Successful or not
        if ( $request->result !== 'SUCCESS' ) {
            // الرجوع الى صفحة الحجز مع تنبيه بوجود خطأ
            return redirect()
                ->route('booking', ['package_id' => $package_id, 'is_pay' => 1])
                ->withErrors(['msg' => $request->response_code]);
        }

        // Legitimate the request by comparing the hash string you computed with the one passed with the request
        if ( $this->generateResponseHash($request) !== $request->hash ) {
            return redirect()
                ->route('booking', ['package_id' => $package_id, 'is_pay' => 1])
                ->withErrors(['msg', __("master.msg.payment_error")]);
        }

        $payment = [
            'provider' => 'tab',
            'payment_transaction_no' => $request->payid,
            'payment_amount' => $request->amt,
        ];

        $booking = $this->createBooking($package_id, $payment);

        // التحويل الى صفحة حجوزاتي
        return redirect()->route('account-booking', $booking->id);
    }

    // tabby provider return...
    public function tabbyReturn(Request $request)
    {
        $payment = app('App\Http\Controllers\TabbyController')->payment($request->payment_id);
                
        $package_id = $payment['order']['reference_id'];

        if (isset($payment['captures']) && count($payment['captures'])) {
            $payment = [
                'provider' => 'tabby',
                'payment_transaction_no' => $payment['id'],
                'payment_amount' => $payment['amount'],
            ];

            $booking = $this->createBooking($package_id, $payment);

            return redirect()->route('account-booking', $booking->id);
        }

        return redirect()
            ->route('booking', ['package_id' => $package_id, 'is_pay' => 1])
            ->withErrors(['msg', __("master.msg.payment_error")]);
    }

    // create booking that has no downpayments
    public function store(Request $request, $package_id)
    {
       $booking = $this->createBooking($package_id);

        return redirect()->route('account-booking', $booking->id);
    }

    // refactored by @SeyamMs
    public function createBooking($package_id, $payment = false)
    {
        $selected_features = session('booking_' . $package_id . '.selected_features');
            
        $package = Package::where('id', $package_id)
        ->select('id', 'hotel_id', 'downpayment_amount', 'upon_arrival_amount')
        ->with(['Times' => function ($query) {
            $now = date('Y-m-d');
            $query->whereRaw("date('" . $now . "') between start_date and end_date")->limit(1)->orderBy('id', 'asc');
        }]);
        if (is_array($selected_features) && count($selected_features)) {
            $package = $package->with(['Features' => function ($query) use ($selected_features) {
                $query->whereIn('id', array_keys($selected_features));
            }]);
        }
        $package = $package->first();

        $user = User::registerOrLogin($package_id, session('booking_' . $package_id . '.email'), session('booking_' . $package_id . '.phone'));

        // Set Times
        foreach ($package->Times as $time) {
            if ($time->inventory > 0) {
                $package->inventory = $time->inventory;
            }
            $package->downpayment_amount = $time->downpayment_amount;
            $package->upon_arrival_amount = $time->upon_arrival_amount;
        }

        // generate unique invoice hash...
        do {
            $invoice_hash = str_random(10);
        } while (Booking::where('invoice_hash', $invoice_hash)->count());

        // create booking row...
        $booking = Booking::create([
            'hotel_id' => $package->hotel_id,
            'package_id' => $package->id,
            'user_id' => auth()->check() ? auth()->user()->id : $user->id,
            'name' => session('booking_' . $package_id . '.name'),
            'phone' => session('booking_' . $package_id . '.phone'),
            'email' => session('booking_' . $package_id . '.email'),
            'country' => session('booking_' . $package_id . '.country'),
            'notes' => session('booking_' . $package_id . '.notes'),
            'rooms' => session('booking_' . $package_id . '.rooms'),
            'check_in_date' => session('booking_' . $package_id . '.check_in_date'),
            'check_out_date' => session('booking_' . $package_id . '.check_out_date'),
            'is_paid' => 1,
            'downpayment_amount' => $package->downpayment_amount * session('booking_' . $package_id . '.rooms'),
            'upon_arrival_amount' => $package->upon_arrival_amount * session('booking_' . $package_id . '.rooms'),
            'invoice_hash' => $invoice_hash,
            'payment_transaction_no' => $payment['payment_transaction_no'] ?? null,
            'payment_amount' => $payment['payment_amount'] ?? null,
            'payment_currency' => 'SAR',
        ]);


        // send notifications if there was a payment...
        if ( $payment ) {
            // SMS
            $booking->load(['Package.RoomType', 'PackageFeatures']);
            switch ($payment['provider']) {
                case 'paytabs':
                    $line1 = 'تاريخ الدخول ' . date('d/m/Y', strtotime($booking->check_in_date)) . ' تاريخ المغادرة ' . date('d/m/Y', strtotime($booking->check_out_date));
                    $line2 = 'نوع الغرفة ' . $booking->Package->RoomType->name_ar . ' السعر ' . number_format($booking->downpayment_amount + $booking->upon_arrival_amount) . ' ريال ' . ' الدفعة عند الوصول' . number_format($booking->upon_arrival_amount) . ' ريال';
                    $line3 = isset($booking->Package->reservation_brn) && $booking->Package->reservation_brn != null && $booking->Package->reservation_brn != '' ? ' رقم الحجز الفندقى ' . $booking->Package->reservation_brn . '  ' : '';
                    $line4 = 'تفاصيل الحجز كاملة على ' . route('booked-details', ['hash' => $booking->invoice_hash]);
                    // if($booking->PackageFeatures->count()){
                    //     $feats = '';
                    //     foreach($booking->PackageFeatures as $Key => $feature){
                    //         $feats .= (($Key > 0) ? ', ' : '').$feature->Details->name_ar;
                    //     }
                    //     $line3 = 'يشمل ايضاً: '.$feats;
                    // }
                    $message = 'تفاصيل الحجز في حجوزات زمزم \n' . $line1 . '\n' . $line2 . '\n' . $line3 . '\n' . $line4;
                    break;
                
                case 'tab':
                    $line1 = 'تاريخ الدخول ' . date('d/m/Y', strtotime($booking->check_in_date)) . ' تاريخ المغادرة ' . date('d/m/Y', strtotime($booking->check_out_date));
                    $line2 = 'نوع الغرفة ' . $booking->Package->RoomType->name_ar . ' السعر ' . number_format($booking->downpayment_amount + $booking->upon_arrival_amount) . ' ريال ' . ' الدفعة عند الوصول' . number_format($booking->upon_arrival_amount) . ' ريال';
                    $line3 = 'تفاصيل الحجز كاملة على ' . route('booked-details', ['hash' => $booking->invoice_hash]);
                    $message = 'تفاصيل الحجز في حجوزات زمزم \n' . $line1 . '\n' . $line2 . '\n' . $line3;
                    break;

                case 'tabby':
                    $line1 = 'تاريخ الدخول ' . date('d/m/Y', strtotime($booking->check_in_date)) . ' تاريخ المغادرة ' . date('d/m/Y', strtotime($booking->check_out_date));
                    $line2 = 'نوع الغرفة ' . $booking->Package->RoomType->name_ar . ' السعر ' . number_format($booking->downpayment_amount + $booking->upon_arrival_amount) . ' ريال ' . ' الدفعة عند الوصول' . number_format($booking->upon_arrival_amount) . ' ريال';
                    $line3 = 'تفاصيل الحجز كاملة على ' . route('booked-details', ['hash' => $booking->invoice_hash]);
                    $message = 'تفاصيل الحجز في حجوزات زمزم \n' . $line1 . '\n' . $line2 . '\n' . $line3;
                    break;
            }
            // (new SMSFactory())->send($booking->phone, $message);
            Log::debug('sms was sent');
            
            // Email
            if ($booking->email) {
                // Mail::to($booking->email)->send(new BookingMail($booking));
                Log::debug('email was sent');
            }
        }
            
        if (is_array($selected_features) && count($selected_features)) {
            $insertFeatures = [];
            $extra_prices = 0;
            foreach ($package->Features as $feature) {
                if (isset($selected_features[$feature->id]) && $selected_features[$feature->id]) {
                    $insertFeatures = [
                        'booking_id' => $booking->id,
                        'feature_id' => $feature->Details->id,
                        'price' => $feature->price
                    ];
                    $extra_prices += $feature->price;
                }
            }
            BookingPackageFeature::insert($insertFeatures);
            // اضافة اسعار المميزات الاضافية للعرض الى الدفعة المقدمة
            $booking->update([
                'downpayment_amount' => ($booking->downpayment_amount + ($extra_prices * $booking->rooms))
            ]);
        }

        // الغاء الجلسة
        session()->forget('booking_' . $package_id);

        return $booking;
    }

    // صفحة لإستعراض تفاصيل الحجز وتزويدها للرابط المرسل الى العميل بعد الحجز
    public function getBookedDetails($hash, Request $q)
    {
        $Booking = Booking::where('invoice_hash', $hash)->with(['Hotel' => function ($qu) {
            $qu->select('title_' . \LaravelLocalization::getCurrentLocale() . ' as title', 'stars_count', 'map_cord', 'phone', 'id');
        }, 'Package' => function ($qu) {
            $qu->select('title_' . \LaravelLocalization::getCurrentLocale() . ' as title', 'adults', 'children', 'tax_rate', 'room_space', 'single_beds', 'double_beds', 'room_type_id', 'image', 'id', 'reservation_brn')->with(['RoomType' => function ($qu2) {
                $qu2->select('id', 'name_' . \LaravelLocalization::getCurrentLocale() . ' as name');
            }])->with('Features');
        }, 'User' => function ($qu) {
            $qu->select('id', 'type', 'code');
        }])->firstOrFail();
        $sdate = strtotime($Booking->check_in_date);
        $edate = strtotime($Booking->check_out_date);
        $datediff = $edate - $sdate;
        $calculateNights = round($datediff / (60 * 60 * 24));
        return view('site.booked-details', ['Booking' => $Booking, 'total_nights' => $calculateNights]);
    }

    public function setBookedCustomerReview($hash, $stayed, Request $q)
    {
        $Booking = Booking::where('invoice_hash', $hash)->where('is_notified', 1)->firstOrFail();
        $Booking->is_stayed = $stayed;
        $Booking->save();
        return $this->getBookedDetails($hash, $q);
    }
}
