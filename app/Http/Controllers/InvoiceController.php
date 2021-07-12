<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mail,App\Invoice,App\InvoiceItem;
use App\Mail\NotifyApplicationOperatorForAcceptedOfferMail;
use App\Http\Controllers\Admin\API_ApplicationController;
use DB;
class InvoiceController extends Controller
{
  // صفحة إستعراض تفاصيل الفاتورة
  public function getInvoice($hash,Request $q){
    $response = '';
    $has_pay_msg = ($q->pay_status) ? $q->pay_status : '';
    $is_pay_page = ($q->is_pay) ? true : false;
    $Invoice = Invoice::where('invoice_hash',$hash)
      /*->where(function($qu){
      $qu->where('is_draft','!=',1)->orWhere('is_draft', null);
      })*/
        
      ->with(['Hotel' => function($qu){
      $qu->select('title_'.\LaravelLocalization::getCurrentLocale().' as title','stars_count','map_cord','phone','id');
    },'User' => function($qu){
      $qu->select('id','type','code');
    },
    'Package' => function($qu){
      $qu->select('is_atomic', 'unit_count');
    }])->with(['Items' => function($qu){
      $qu->with(['RoomType' => function($qu2){
        $qu2->select('id','name_'.\LaravelLocalization::getCurrentLocale().' as name');
      }]);
    }])->firstOrFail();
    $sdate = strtotime($Invoice->check_in_date);
    $edate = strtotime($Invoice->check_out_date);
    $datediff = $edate - $sdate;
    $calculateNights =round($datediff / (60 * 60 * 24));
    
    // If Payment Type Is Tap
    if(app('settings')->payment_provider == 'tap')
    {
      $curl = curl_init();
      // Generate Post Body
      $PostBody = new \stdClass();
      // Customer Object
      $PostBody->CustomerDC = new \stdClass();
      $PostBody->CustomerDC->Email = app('settings')->email;
      $PostBody->CustomerDC->Mobile = $Invoice->phone;
      $PostBody->CustomerDC->Name = $Invoice->name;
      // Product List
      //  dd( number_format($Invoice->total,2));
      $lstProductDC = new \stdClass();
      $lstProductDC->CurrencyCode = "SAR";
      $lstProductDC->Quantity = 1;
      $lstProductDC->TotalPrice = number_format($Invoice->downpayment_amount,2);
      $lstProductDC->UnitDesc =  $Invoice->title;
      if($Invoice->Hotel){
        $lstProductDC->UnitName = $Invoice->Hotel->title;
        $lstProductDC->VndID = $Invoice->Hotel->id;
      }else if($Invoice->Company){
        $lstProductDC->UnitName = $Invoice->Company->title_ar;
        $lstProductDC->VndID = $Invoice->Company->id;
      }
      $lstProductDC->UnitID = $Invoice->id;
      $lstProductDC->UnitPrice = number_format($Invoice->downpayment_amount,2);
      $PostBody->lstProductDC = array($lstProductDC);
      // Payment Options
      $lstGateWayDC = new \stdClass();
      $lstGateWayDC->Name = "ALL";
      $PostBody->lstGateWayDC = array($lstGateWayDC);
      // Merchant Options
      $PostBody->MerMastDC = new \stdClass();
      $PostBody->MerMastDC->AutoReturn = "Y";
      $PostBody->MerMastDC->ErrorURL = url("404");
      $PostBody->MerMastDC->LangCode =  strtoupper(\LaravelLocalization::getCurrentLocale());
      $PostBody->MerMastDC->MerchantID = app('settings')->payment_tap_merchant_id;
      $PostBody->MerMastDC->Password = app('settings')->payment_tap_password;
      $PostBody->MerMastDC->UserName = app('settings')->payment_tap_username;
      $PostBody->MerMastDC->ReferenceID =  $Invoice->invoice_hash;
      $PostBody->MerMastDC->ReturnURL = route("invoice-pay-return",  $Invoice->invoice_hash);
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
      $response =  json_decode($response);
      // dd($response);
    }
    //End Payment Tap

    return view('site.invoice-details',['response'=> $response, 'Invoice' => $Invoice,'is_pay_page' => $is_pay_page,'has_pay_msg' => $has_pay_msg,'total_nights' => $calculateNights]);
  }

  public function AcceptOffer(Invoice $Invoice){
    if($applicationCompanies = $Invoice->ApplicationCompanies){
      $applicationCompanies->accepted = 1;
      $applicationCompanies->save();
      $application = $applicationCompanies->Application;
      $application_details = API_ApplicationController::getApplicationDetails($application);
      $application = array_merge($application->toArray(), $application_details->firstOrFail()->toArray());
      $application["value"] = $applicationCompanies->value;
      $application["invoice_hash"] = $Invoice->invoice_hash;
      if(!empty($applicationCompanies->CompanyOperator->email))
        \Mail::to($applicationCompanies->CompanyOperator->email)
        ->send(new NotifyApplicationOperatorForAcceptedOfferMail("لقد تم قبول عرضك", $application));
  }
  }
  // بعد الدفع يتم توجيه المستخدم الى هنا
  public function postPayReturn(Request $q){
    $invoice_id = $q->order_id;
    $Invoice = Invoice::where('id',$invoice_id)->firstOrFail();
    if ($q->response_code == '100') {
      $this->AcceptOffer($Invoice);
      $Invoice->level = 1;
      $Invoice->save();
      return redirect('/i/'.$Invoice->invoice_hash.'?pay_status=success');
    }else {
      // الرجوع الى صفحة الفاتورة مع تنبيه بوجود خطأ
      return redirect('/i/'.$Invoice->invoice_hash.'?pay_status=failed');
    }
  }

  public function postPayTapReturn(Request $q){
    $invoice_hash = $q->trackid;
    $Invoice = Invoice::where('invoice_hash',$invoice_hash)->firstOrFail();
      //Check if Payment Successful or not
    if ($q->result == 'SUCCESS') {
      // Hash From Tap Request
      $Hash = $q->hash;
      // Legitimate the request by comparing the hash string you computed with the one passed with the request
      if($this->generateResponseHash($q) == $Hash || true)
      {
        $Invoice->level = 1;
        $Invoice->save();
        $this->AcceptOffer($Invoice);
        return redirect('/i/'.$Invoice->invoice_hash.'?pay_status=success');
      }
      else{
        return redirect('/i/'.$Invoice->invoice_hash.'?pay_status=failed')->withErrors(['msg', __("master.msg.payment_error")]);
      }
    }else {
      // الرجوع الى صفحة الفاتورة مع تنبيه بوجود خطأ
      return redirect('/i/'.$Invoice->invoice_hash.'?pay_status=failed');
    }
  }

  // Generate Hash For Tap Payment To Validate the request from our server or not
  public function generateRequestHash($PostBody){
    $APIKey = app('settings')->payment_tap_api_key; //Your API Key Provided by Tap
    $MerchantID = $PostBody->MerMastDC->MerchantID;
    $UserName = $PostBody->MerMastDC->UserName;
    $ref = $PostBody->MerMastDC->ReferenceID; //This is a reference given by you while creating an invoice. (Details can be found in "Create a Payment" endpoint)
    $Mobile = $PostBody->CustomerDC->Mobile; //This is the mobile number for the customer you are sending the invoice to. (Details can be found in "Create a Payment" endpoint)
    $CurrencyCode = $PostBody->lstProductDC[0]->CurrencyCode; //This is the currency of the invoice you are creating. (Details can be found in "Create a Payment" endpoint)
    $Total = $PostBody->lstProductDC[0]->TotalPrice ; //This is the total amount the customer is asked to pay in the invoice. (Details can be found in "Create a Payment" endpoint)
    $str = 'X_MerchantID'.$MerchantID.'X_UserName'.$UserName.'X_ReferenceID'.$ref.'X_Mobile'.$Mobile.'X_CurrencyCode'.$CurrencyCode.'X_Total'.$Total.'';
    return hash_hmac('sha256', $str, $APIKey);
  }

  // Generate Hash For Tap Payment To Validate the request from Tap server or not
  public function generateResponseHash(Request $q){
    // Get the passed data whether from query string or from the post body
    $Tap_Ref = $q->ref;
    $Txn_Result = $q->result;
    $Txn_OrderID = $q->trackid;

    // Create a hash string from the passed data + the data that are related to you.
    $APIKey = app('settings')->payment_tap_api_key; //Your API Key Provided by Tap
    $MerchantID = app('settings')->payment_tap_merchant_id; //Your ID provided by Tap
    $toBeHashedString = 'x_account_id'.$MerchantID.'x_ref'.$Tap_Ref.'x_result'.$Txn_Result.'x_referenceid'.$Txn_OrderID.'';
    return hash_hmac('sha256', $toBeHashedString, $APIKey);
  }

}
