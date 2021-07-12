<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingPackageFeature;
use App\Helpers\Factories\SMSFactory;
use App\Invoice;
use App\InvoiceItem;
use App\Mail\BookingMail;
use App\Mail\NotificationBookingMail;
use App\Package;
use App\PackageAccept;
use App\User;
use DB;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Mail;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class BookingController extends Controller
{
    use AuthenticatesUsers;

    // صفحة الحجز الرئيسية
    public function index($package_id, Request $q)
    {
        $Package = Package::where('id', $package_id)->cardSelect()->
        addSelect(DB::raw('description_' . \LaravelLocalization::getCurrentLocale() . ' as description'),
            'title_ar', 'inventory', 'downpayment_amount', 'single_beds', 'double_beds', 'room_space', 'room_cooler',
            'is_atomic', 'unit_count', 'checkBeforePay', 'unit_measure', 'user_id', 'upon_arrival_amount',
            'bathrooms_count', 'kitchens_count', 'room_type_id')->
        with(['Type' => function ($qu) {
            $qu->select('id', DB::raw('name_' . \LaravelLocalization::getCurrentLocale() . ' as name'));
        }])->with(['RoomType' => function ($qu) {
            $qu->select('id', DB::raw('name_' . \LaravelLocalization::getCurrentLocale() . ' as name'));
        }])->with(['Hotel' => function ($qu) {
            $qu->select('id', 'stars_count', DB::raw('title_' . \LaravelLocalization::getCurrentLocale() . ' as title'), DB::raw('address_' . \LaravelLocalization::getCurrentLocale() . ' as address'), 'distance_from_haram', 'distance_from_kaaba', 'distance_from_jamarat', 'title_ar', 'map_cord');
        }])->with(['UnitMeasure' => function ($qu) {
            $qu->select('id', DB::raw('name_' . \LaravelLocalization::getCurrentLocale() . ' as name'));
        }])->with(['Times' => function ($qu) {
            $now = date('Y-m-d');
            $qu->whereRaw("date('" . $now . "') between start_date and end_date")->limit(1)->orderBy('id', 'asc');
        }]);

        if (!auth()->user() || !auth()->user()->is_only_hotel_operator) {
            $Package = $Package->onlyActive();
        }

        $is_pay_page = ($q->is_pay && session()->has('booking_' . $package_id . '.phone')) ? true : false;
        // حساب مجموع الدفع
        $selected_features = session('booking_' . $package_id . '.selected_features');
        $PayAmount = 0;
        if ($is_pay_page && is_array($selected_features) && count($selected_features)) {
            $Package = $Package->with(['Features' => function ($qu) use ($selected_features) {
                $qu->whereIn('id', array_keys($selected_features));
            }]);
        }
        $Package = $Package->firstOrFail();
        // Set Times
        foreach ($Package->Times as $time) {
            if ($time->inventory > 0)
                $Package->inventory = $time->inventory;

            $Package->downpayment_amount = $time->downpayment_amount;
            $Package->upon_arrival_amount = $time->upon_arrival_amount;
        }
        $response = '';
        $Invoice = new Invoice;
        if ($is_pay_page) {
            if ($Package->checkBeforePay == 1) {
                $invoice = Invoice::where("package_id", $Package->id)->where("traveller_id", auth()->check() ? auth()->User()->id : session('booking_' . $package_id . '.user_id'))->first();
                if (!$invoice) {
                    $selected_features = session('booking_' . $package_id . '.selected_features');
                    if (is_array($selected_features) && count($selected_features)) {
                        $Package = $Package->with(['Features' => function ($qu) use ($selected_features) {
                            $qu->whereIn('id', array_keys($selected_features));
                        }]);
                    }

                    $rooms = session('booking_' . $package_id . '.rooms');
                    $Invoice = new Invoice;
                    $Invoice->invoice_hash = str_random(12);
                    $Invoice->name = session('booking_' . $package_id . '.name');
                    $Invoice->phone = session('booking_' . $package_id . '.phone');
                    $Invoice->email = session('booking_' . $package_id . '.email');
                    $Invoice->check_in_date = session('booking_' . $package_id . '.check_in_date');
                    $Invoice->check_out_date = session('booking_' . $package_id . '.check_out_date');
                    $Invoice->payment_method = 'online';
                    $Invoice->user_id = $Package->User->id;
                    $Invoice->hotel_id = $Package->hotel_id;
                    $Invoice->notes = session('booking_' . $package_id . '.notes');
                    $Invoice->created_by_name = $Package->User->name;
                    $Invoice->created_by_phone = $Package->User->phone;
                    $Invoice->vat_tax = 0;
                    $Invoice->municipal_tax = 0;
                    $Invoice->admin_tax = 0;
                    $Invoice->package_id = $Package->id;
                    $Invoice->traveller_id = auth()->check() ? auth()->User()->id : session('booking_' . $package_id . '.user_id');
                    $Invoice->upon_arrival_amount = $Package->upon_arrival_amount * $rooms;
                    $Invoice->downpayment_amount = $Package->downpayment_amount * $rooms;
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
                        foreach ($Package->Features as $Feature) {
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
                        'image' => $Package->Hotel->Image->path,
                        'invoice_id' => $Invoice->id,
                        'room_type_id' => $Package->room_type_id,
                        'price' => $Invoice->upon_arrival_amount + $Invoice->downpayment_amount,
                        'quantity' => $rooms,
                        'notes' => '',
                        'amount' => $subtotal
                    ];
                    InvoiceItem::insert($insertItems);
                    Invoice::where('id', $Invoice->id)->update(['subtotal' => $subtotal, 'total' => $subtotal]);

                    // // Update total values
                    // $vat_tax_amount = ($q->vat_tax) ? $subtotal*($q->vat_tax*0.01) : 0;
                    // $municipal_tax_amount = ($q->municipal_tax) ? $subtotal*($q->municipal_tax*0.01) : 0;
                    // $admin_tax_amount = ($q->admin_tax) ? $subtotal*($q->admin_tax*0.01) : 0;

                    // $total = $subtotal+$vat_tax_amount+$municipal_tax_amount+$admin_tax_amount;

                    $line1 = route('invoice-details', ['hash' => $Invoice->invoice_hash]);
                    $message = 'الرجاء التأكيد علي هذا الحجز \n' . $line1;
                    (new SMSFactory())->send($Package->User->phone, $message);

                    $Invoice->package_name = $Package->title_ar;
                    // Email
                    if ($Package->User->email) {
                        \Mail::to($Package->User->email)->send(new NotificationBookingMail($Invoice));
                    }
                }
            }
            $sdate = strtotime(session('booking_' . $package_id . '.check_in_date'));
            $edate = strtotime(session('booking_' . $package_id . '.check_out_date'));
            $datediff = $edate - $sdate;
            $calculateNights = round($datediff / (60 * 60 * 24));

            $PayAmount = $Package->downpayment_amount;
            if ($Package->Features->count()) {
                foreach ($Package->Features as $Feature) {
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
                $PostBody->CustomerDC->Email = session('booking_' . $package_id . '.email');
                $PostBody->CustomerDC->Mobile = session('booking_' . $package_id . '.phone');
                $PostBody->CustomerDC->Name = session('booking_' . $package_id . '.name');
                // Product List
                $lstProductDC = new \stdClass();
                $lstProductDC->CurrencyCode = "SAR";
                $lstProductDC->ImgUrl = asset('/uploads/hotels/thumb_' . $Package->image);
                $lstProductDC->Quantity = session('booking_' . $package_id . '.rooms');
                $lstProductDC->TotalPrice = ($PayAmount * $calculateNights) * session('booking_' . $package_id . '.rooms');
                $lstProductDC->UnitDesc = $Package->title;
                $lstProductDC->UnitName = $Package->Hotel->title;
                $lstProductDC->UnitID = $Package->id;
                $lstProductDC->UnitPrice = $PayAmount;
                $lstProductDC->VndID = $Package->Hotel->id;
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
                $PostBody->MerMastDC->ReferenceID = $Package->id . '-' . rand(111111, 999999);
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

            $PayAmount = ($PayAmount * $calculateNights) * session('booking_' . $package_id . '.rooms');
        }
        return view('site.booking', ['response' => $response, 'Package' => $Package,
            'is_pay_page' => $is_pay_page, 'PayAmount' => $PayAmount]);
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
//      if (!auth()->user()) {
//        abort(403);
//      }
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

    // بعد الدفع يتم توجيه المستخدم الى هنا
    public function postPayReturn(Request $q)
    {
        $package_id = $q->order_id;
        if ($q->response_code == '100') {
            $selected_features = session('booking_' . $package_id . '.selected_features');
            $Package = Package::where('id', $package_id)->select('id', 'hotel_id', 'downpayment_amount', 'upon_arrival_amount')
                ->with(['Times' => function ($qu) {
                    $now = date('Y-m-d');
                    $qu->whereRaw("date('" . $now . "') between start_date and end_date")->limit(1)->orderBy('id', 'asc');
                }]);

            if (is_array($selected_features) && count($selected_features)) {
                $Package = $Package->with(['Features' => function ($qu) use ($selected_features) {
                    $qu->whereIn('id', array_keys($selected_features));
                }]);
            }

            $Package = $Package->first();
            // Set Times
            foreach ($Package->Times as $time) {
                if ($time->inventory > 0)
                    $Package->inventory = $time->inventory;

                $Package->downpayment_amount = $time->downpayment_amount;
                $Package->upon_arrival_amount = $time->upon_arrival_amount;
            }

            $Booking = new Booking;
            $Booking->hotel_id = $Package->hotel_id;
            $Booking->package_id = $Package->id;
            $Booking->user_id = auth()->check() ? auth()->user()->id : $user->id;
            $Booking->name = session('booking_' . $package_id . '.name');
            $Booking->phone = session('booking_' . $package_id . '.phone');
            $Booking->email = session('booking_' . $package_id . '.email');
            $Booking->country = session('booking_' . $package_id . '.country');
            $Booking->notes = session('booking_' . $package_id . '.notes');
            $Booking->rooms = session('booking_' . $package_id . '.rooms');
            $Booking->is_paid = 1;
            $Booking->check_in_date = session('booking_' . $package_id . '.check_in_date');
            $Booking->check_out_date = session('booking_' . $package_id . '.check_out_date');
            $Booking->downpayment_amount = $Package->downpayment_amount * $Booking->rooms;
            $Booking->upon_arrival_amount = $Package->upon_arrival_amount * $Booking->rooms;
            $Booking->payment_transaction_no = $q->transaction_id;
            $Booking->payment_amount = $q->transaction_amount;
            $Booking->payment_currency = $q->transaction_currency;
            $Booking->invoice_hash = str_random(10);
            $Booking->save();

            if (is_array($selected_features) && count($selected_features)) {
                $insertFeatures = [];
                $extra_prices = 0;
                foreach ($Package->Features as $Feature) {
                    if (isset($selected_features[$Feature->id]) && $selected_features[$Feature->id]) {
                        $insertFeatures = [
                            'booking_id' => $Booking->id,
                            'feature_id' => $Feature->Details->id,
                            'price' => $Feature->price
                        ];
                        $extra_prices += $Feature->price;
                    }
                }
                BookingPackageFeature::insert($insertFeatures);
                // اضافة اسعار المميزات الاضافية للعرض الى الدفعة المقدمة
                $updateBooking = Booking::where('id', $Booking->id)->update(['downpayment_amount' => ($Booking->downpayment_amount + ($extra_prices * $Booking->rooms))]);
            }


            // ارسال اشعار لصاحب الحجز
            $Booking = Booking::where('id', $Booking->id)->with(['Package' => function ($qu) {
                return $qu->select('id', 'title_ar', 'room_type_id', 'reservation_brn')->with('RoomType');
            }, 'Hotel' => function ($qu) {
                return $qu->select('id', 'title_ar', 'stars_count');
            }])->with('PackageFeatures')->first();

            // SMS
            $line1 = 'تاريخ الدخول ' . date('d/m/Y', strtotime($Booking->check_in_date)) . ' تاريخ المغادرة ' . date('d/m/Y', strtotime($Booking->check_out_date));
            $line2 = 'نوع الغرفة ' . $Booking->Package->RoomType->name_ar . ' السعر ' . number_format($Booking->downpayment_amount + $Booking->upon_arrival_amount) . ' ريال ' . ' الدفعة عند الوصول' . number_format($Booking->upon_arrival_amount) . ' ريال';
            $line3 = isset($Booking->Package->reservation_brn) && $Booking->Package->reservation_brn != null && $Booking->Package->reservation_brn != '' ? ' رقم الحجز الفندقى ' . $Booking->Package->reservation_brn . '  ' : '';
            $line4 = 'تفاصيل الحجز كاملة على ' . route('booked-details', ['hash' => $Booking->invoice_hash]);
            // if($Booking->PackageFeatures->count()){
            //   $feats = '';
            //   foreach($Booking->PackageFeatures as $Key => $Feature){
            //     $feats .= (($Key > 0) ? ', ' : '').$Feature->Details->name_ar;
            //   }
            //   $line3 = 'يشمل ايضاً: '.$feats;
            // }
            $message = 'تفاصيل الحجز في حجوزات زمزم \n' . $line1 . '\n' . $line2 . '\n' . $line3 . '\n' . $line4;
            (new SMSFactory())->send($Booking->phone, $message);

            // Email
            if ($Booking->email) {
                \Mail::to($Booking->email)->send(new BookingMail($Booking));
            }

            // الغاء الجلسة
            session()->forget('booking_' . $package_id);

            // التحويل الى صفحة حجوزاتي
            return redirect('/account/bookings/' . $Booking->id);
        } else {
            // الرجوع الى صفحة الحجز مع تنبيه بوجود خطأ
            return redirect('/booking/' . $package_id . '?is_pay=1')->withErrors(['msg', $q->response_code]);
        }
    }

    // For Tap Call Back Payment
    public function postPayTapReturn(Request $q)
    {
        $package_id = explode('-', $q->trackid)[0];
        //Check if Payment Successful or not
        if ($q->result == 'SUCCESS') {
            $selected_features = session('booking_' . $package_id . '.selected_features');
            $Package = Package::where('id', $package_id)->select('id', 'hotel_id', 'downpayment_amount', 'upon_arrival_amount')
                ->with(['Times' => function ($qu) {
                    $now = date('Y-m-d');
                    $qu->whereRaw("date('" . $now . "') between start_date and end_date")->limit(1)->orderBy('id', 'asc');
                }]);
            if (is_array($selected_features) && count($selected_features)) {
                $Package = $Package->with(['Features' => function ($qu) use ($selected_features) {
                    $qu->whereIn('id', array_keys($selected_features));
                }]);
            }

            // Hash From Tap Request
            $Hash = $q->hash;
            // Legitimate the request by comparing the hash string you computed with the one passed with the request
            if ($this->generateResponseHash($q) == $Hash) {
                $Package = $Package->first();

                $user = User::registerOrLogin($package_id);

                // Set Times
                foreach ($Package->Times as $time) {
                    if ($time->inventory > 0)
                        $Package->inventory = $time->inventory;

                    $Package->downpayment_amount = $time->downpayment_amount;
                    $Package->upon_arrival_amount = $time->upon_arrival_amount;
                }
                $Booking = new Booking;
                $Booking->hotel_id = $Package->hotel_id;
                $Booking->package_id = $Package->id;
                $Booking->user_id = auth()->check() ? auth()->user()->id : $user->id;
                $Booking->name = session('booking_' . $package_id . '.name');
                $Booking->phone = session('booking_' . $package_id . '.phone');
                $Booking->email = session('booking_' . $package_id . '.email');
                $Booking->country = session('booking_' . $package_id . '.country');
                $Booking->notes = session('booking_' . $package_id . '.notes');
                $Booking->rooms = session('booking_' . $package_id . '.rooms');
                $Booking->is_paid = 1;
                $Booking->check_in_date = session('booking_' . $package_id . '.check_in_date');
                $Booking->check_out_date = session('booking_' . $package_id . '.check_out_date');
                $Booking->downpayment_amount = $Package->downpayment_amount * $Booking->rooms;
                $Booking->upon_arrival_amount = $Package->upon_arrival_amount * $Booking->rooms;
                $Booking->payment_transaction_no = $q->payid;
                $Booking->payment_amount = $q->amt;
                $Booking->payment_currency = "SAR";
                $Booking->invoice_hash = str_random(10);
                $Booking->save();

                if (is_array($selected_features) && count($selected_features)) {
                    $insertFeatures = [];
                    $extra_prices = 0;
                    foreach ($Package->Features as $Feature) {
                        if (isset($selected_features[$Feature->id]) && $selected_features[$Feature->id]) {
                            $insertFeatures = [
                                'booking_id' => $Booking->id,
                                'feature_id' => $Feature->Details->id,
                                'price' => $Feature->price
                            ];
                            $extra_prices += $Feature->price;
                        }
                    }
                    BookingPackageFeature::insert($insertFeatures);
                    // اضافة اسعار المميزات الاضافية للعرض الى الدفعة المقدمة
                    $updateBooking = Booking::where('id', $Booking->id)->update(['downpayment_amount' => ($Booking->downpayment_amount + ($extra_prices * $Booking->rooms))]);
                }

                // ارسال اشعار لصاحب الحجز
                $Booking = Booking::where('id', $Booking->id)->with(['Package' => function ($qu) {
                    return $qu->select('id', 'title_ar', 'room_type_id')->with('RoomType');
                }, 'Hotel' => function ($qu) {
                    return $qu->select('id', 'title_ar', 'stars_count');
                }])->with('PackageFeatures')->first();

                // SMS
                $line1 = 'تاريخ الدخول ' . date('d/m/Y', strtotime($Booking->check_in_date)) . ' تاريخ المغادرة ' . date('d/m/Y', strtotime($Booking->check_out_date));
                $line2 = 'نوع الغرفة ' . $Booking->Package->RoomType->name_ar . ' السعر ' . number_format($Booking->downpayment_amount + $Booking->upon_arrival_amount) . ' ريال ' . ' الدفعة عند الوصول' . number_format($Booking->upon_arrival_amount) . ' ريال';
                $line3 = 'تفاصيل الحجز كاملة على ' . route('booked-details', ['hash' => $Booking->invoice_hash]);

                $message = 'تفاصيل الحجز في حجوزات زمزم \n' . $line1 . '\n' . $line2 . '\n' . $line3;
                (new SMSFactory())->send($Booking->phone, $message);

                // Email
                if ($Booking->email) {
                    \Mail::to($Booking->email)->send(new BookingMail($Booking));
                }

                // الغاء الجلسة
                session()->forget('booking_' . $package_id);

                // التحويل الى صفحة حجوزاتي
                return redirect('/account/bookings/' . $Booking->id);
            } else {
                return redirect('/booking/' . $package_id . '?is_pay=1')->withErrors(['msg', __("master.msg.payment_error")]);
            }
        } else {
            // الرجوع الى صفحة الحجز مع تنبيه بوجود خطأ
            return redirect('/booking/' . $package_id . '?is_pay=1')->withErrors(['msg', $q->response_code]);
        }
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
