<!DOCTYPE html>
<html lang="{{ LaravelLocalization::getCurrentLocale() }}" dir="{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'rtl' : 'ltr' }}" ng-app="Zamzam" ng-controller="MainCtrl">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ __('master.invoice.details') }}</title>
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Cairo:300,400,600,700,900&amp;subset=arabic" rel="stylesheet">
  <!-- Styles -->
  <link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/invoice.css?v=9') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/booked.css?v=9') }}" rel="stylesheet">
  @if(LaravelLocalization::getCurrentLocale() == 'en')
  <link href="{{ asset('assets/css/booked_ltr.css?v=9') }}" rel="stylesheet">
  @endif
  <link rel="icon" href="{{ asset('assets/images/favicon.ico?v=1') }}" sizes="16x16 32x32 48x48 64x64" type="image/vnd.microsoft.icon" />
</head>
<body>
  <div @if($Invoice->level == 0) class="invoice-padding" @endif>
    <div class="container">
      <div class="book-header">
        <a href="{{ url('/'.((LaravelLocalization::getCurrentLocale() == 'ar') ? 'en' : 'ar').'/i/'.$Invoice->invoice_hash) }}" class="lang">{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'English' : 'عربي' }}</a>
        <div class="row align-items-sm-center">
          <div class="col-sm-8 col-6 pt-1 text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'right' : 'left' }}">
            <a class="logo" href="{{ url('/'.LaravelLocalization::getCurrentLocale()) }}"><img src="{{ asset('assets/images/logo.png') }}" alt=""></a>
          </div>
          <div class="col-sm-4 col-6 text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }}">
            <div class="row mb-2">
              <div class="col-sm-6">
                <b class="text-muted">{{ __('master.invoice.date_of_create') }}</b>
              </div>
              <div class="col-sm-6">
                <h5 class="mt-sm-0 mt-1">{{ $Invoice->created_at->format('d/m/Y') }}</h5>
              </div>
            </div>
            <div class="row mt-sm-0 mt-2">
              <div class="col-sm-6">
                <b class="text-muted">{{ __('master.invoice.invoice_no') }}</b>
              </div>
              <div class="col-sm-6">
                <h5 class="mt-sm-0 mt-1">{{ $Invoice->id }}</h5>
              </div>
            </div>

          </div>
        </div>
      </div>
      @if($has_pay_msg)
        @if($has_pay_msg == 'success')
          <div class="alert alert-success text-center">{{ __('master.invoice.pay_success_msg') }}</div>
        @else
          <div class="alert alert-danger text-center">{{ __('master.invoice.pay_failed_msg') }}</div>
        @endif
      @endif
      
      <div class="book-check-wrapper mb-5">
        <div class="row align-items-center">
          @if($Invoice->Hotel)
          <div class="col-sm-3 text-primary text-center">
            <i class="ic-calendar"></i>
            <h3>
              @if(!empty($Invoice->package->unit_count) && $Invoice->package->unit_count > 0)
                {{$Invoice->package->unit_count}}
              @else 
                  {{ $total_nights }}
              @endif
                  {{ __('master.nights') }}</h3>
              
          </div>
          @endif
          <div class="col-sm-9">
            <div class="row mt-md-0 text-md-auto mt-4 text-center">
              <div class="col-6">
                <h5>{{ __('master.form.checkin_date') }}</h5>
                <h2 class="text-primary">{{ date('d/m/Y',strtotime($Invoice->check_in_date)) }}</h2>
              </div>
              <div class="col-6">
                <h5>{{ __('master.form.checkout_date') }}</h5>
                @if(!empty($Invoice->package->unit_count) && $Invoice->package->unit_count > 0)
                  <h2 class="text-primary">{{ date('d/m/Y',strtotime($Invoice->check_in_date . ' + '. $Invoice->package->unit_count .' days')) }}</h2>
                @else
                  <h2 class="text-primary">{{ date('d/m/Y',strtotime($Invoice->check_out_date)) }}</h2>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="alert alert-light p-4">
        <div class="row">
          <div class="col-12 col-sm-4 pb-md-0 pb-3">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.invoice.customer_name') }}</b>
            </div>
            <b>{{ $Invoice->name }}</b>
          </div>
          <div class="col-6 col-sm-4 ltr">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.form.phone') }}</b>
            </div>
            <div class="ltr"><b>{{ $Invoice->phone }}</b></div>
          </div>
          <div class="col-6 col-sm-4">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.invoice.payment_method') }}</b>
            </div>
            <b>{{ __('master.invoice.payment_types.'.$Invoice->payment_method) }}</b>
          </div>
          <div class="col-6 col-sm-4">
            <div class="mt-3 mb-1">
              <b class="text-muted">{{ __('master.invoice.created_by_name') }}</b>
            </div>
            <b>{{ $Invoice->created_by_name }}</b>
          </div>
          <div class="col-6 col-sm-4">
            <div class="mt-3 mb-1">
              <b class="text-muted">{{ __('master.invoice.created_by_phone') }}</b>
            </div>
            <div class="ltr"><b>{{ $Invoice->created_by_phone }}</b></div>
          </div>
        </div>
      </div>
      @if($Invoice->Items->count())
      <div class="invoice-items">
        @foreach($Invoice->Items as $Key => $Item)
        <div class="row book-package text-center text-md-auto {{ ($Key == 0) ? 'bt-0' : '' }}">
          <div class="col-sm-3">
            <div class="image">
              @if($Item->image)
              <img src="{{ asset('uploads/hotels/thumb_'.$Item->image) }}" alt="">
              @endif
            </div>
          </div>
          <div class="col-sm-9 px-sm-4">
            <div class="mt-sm-1">
              <a href="{{ route('show_hotel',['slug' => $Invoice->Hotel->title,'id' => $Invoice->Hotel->id]) }}" target="_blank" class="text-primary h3 d-inline-block mb-2">{{ $Invoice->Hotel->title }}</a>
              <div class="stars mb-3">
                @for($i = 0;$i < Illuminate\Support\Str::limit($Invoice->Hotel->stars_count, 1);$i++)
                <i class="ic-star"></i>
                @endfor
              </div>
            </div>
            <div class="d-flex justify-content-center justify-content-md-start font-weight-bold"><div class="h4 mb-1">{{ $Item->RoomType->name }}</div><span class="h4 mx-2">x</span><span class="h4">{{ $Item->quantity }} {{ __('master.rooms') }}</span></div>
            <div class="description mb-sm-4">
              {{ $Item->notes }}
            </div>
            <div class="price">
              {{ number_format($Item->amount,0).' '.__('master.rs') }}
            </div>
          </div>
        </div>
        @endforeach
      </div>
      @endif
      @if($Invoice->LivingCompany && $Invoice->LivingCompany->meal_type)
        <div class="invoice-items">
          <div class="row book-package text-md-auto bt-0 text-center">
            <div class="col-sm-3">
              <div class="image">
                @if($Invoice->LivingCompany->logo)
                <img src="{{ asset('uploads/livingCompanies/').$Invoice->LivingCompany->logo }}" alt="">
                @endif
              </div>
            </div>
            <div class="col-sm-9 px-sm-4">
              <div class="mt-sm-1">
                <a href="#" 
                  class="text-primary h3 d-inline-block mb-2">{{ $Invoice->LivingCompany->title_ar }}
                </a>
              </div>
              <div class="price">
                @php
                     $meal_types_list = [
                        ["id" => 0, "title_ar" => 'وجبة كاملة: فطور + غداء+ عشاء', "title_en" => 'full meal: breakfast, lunch, dinner'],
                        ["id" => 1, "title_ar" => 'فطور فقط', "title_en" => 'Breakfast only'],
                        ["id" => 2, "title_ar" => 'غداء فقط', "title_en" => 'Lunch only'],
                        ["id" => 3, "title_ar" => 'عشاء فقط', "title_en" => 'Dinner only'],
                        ["id" => 4, "title_ar" => 'فطور + غدا', "title_en" => 'Breakfast + Lunch'],
                        ["id" => 5, "title_ar" => 'غداء+ عشاء', "title_en" => 'Lunch + Dinner'],
                        ["id" => 6, "title_ar" => 'وجبة افطار صائم', "title_en" => 'Iftar Meal(Brakfast Meal for fasting)']
                    ];
                @endphp
                {{  $meal_types_list[$Invoice->LivingRequest->meal_type]["title_ar"] }}
              </div>
            </div>
          </div>
        </div>
      @endif
      <div class="book-totals">
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center mb-2">
          <div class="col-7 col-md-10"><b class="text-muted">{{ __('master.invoice.subtotal') }}</b></div>
          <div class="col-5 col-md-2">
            <h6 class="font-weight-bold">{{ __('master.rs') }} {{ number_format($Invoice->subtotal,0) }}</h6>
          </div>
        </div>
        @if($Invoice->vat_tax)
        <hr>
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center mb-2">
          <div class="col-7 col-md-10"><b class="text-muted">{{ __('master.invoice.vat_tax') }} ({{ $Invoice->vat_tax }}%)</b></div>
          <div class="col-5 col-md-2">
            <h6 class="font-weight-bold"><small>{{ __('master.rs') }}</small> {{ number_format($Invoice->vat_tax_amount,0) }}</h6>
          </div>
        </div>
        @endif
        @if($Invoice->municipal_tax)
        <hr>
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center mb-2">
          <div class="col-7 col-md-10"><b class="text-muted">{{ __('master.invoice.municipal_tax') }} ({{ $Invoice->municipal_tax }}%)</b></div>
          <div class="col-5 col-md-2">
            <h6 class="font-weight-bold"><small>{{ __('master.rs') }}</small> {{ number_format($Invoice->municipal_tax_amount,0) }}</h6>
          </div>
        </div>
        @endif
        @if($Invoice->admin_tax)
        <hr>
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center mb-2">
          <div class="col-7 col-md-10"><b class="text-muted">{{ __('master.invoice.admin_tax') }} ({{ $Invoice->admin_tax }}%)</b></div>
          <div class="col-5 col-md-2">
            <h6 class="font-weight-bold"><small>{{ __('master.rs') }}</small> {{ number_format($Invoice->admin_tax_amount,0) }}</h6>
          </div>
        </div>
        @endif
        <hr>
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center">
          <div class="col-7 col-md-10"><b class="text-muted">{{ __('master.invoice.total') }}</b></div>
          <div class="col-5 col-md-2">
            <h5 class="font-weight-bold text-primary"><small>{{ __('master.rs') }}</small> {{ number_format($Invoice->total,0) }}</h5>
          </div>
        </div>
        <hr>
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center mb-2">
          <div class="col-7 col-md-10"><b class="text-muted">@lang('master.downpayment_amount')</b></div>
          <div class="col-5 col-md-2">
            <h6 class="font-weight-bold"><small>{{ __('master.rs') }}</small> {{ number_format($Invoice->downpayment_amount,0) }}</h6>
          </div>
        </div>
        <hr>
        <div class="row text-{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'left' : 'right' }} align-items-center mb-2">
          <div class="col-7 col-md-10"><b class="text-muted">@lang('master.upon_arrival_amount')</b></div>
          <div class="col-5 col-md-2">
            <h6 class="font-weight-bold"><small>{{ __('master.rs') }}</small> {{ number_format($Invoice->upon_arrival_amount,0) }}</h6>
          </div>
        </div>
      </div>

      
      @if ( is_null($tabby) )
        <div class="alert alert-danger text-center">{{ __('master.invoice.tabby.load_faild') }}</div>
      @else
        @if ($tabby && in_array($tabby['status'], ['created', 'approved']) )
          <script src="https://checkout.tabby.ai/tabby-promo.js"></script>
          <div id="tabbyPromo" style="display: flex;justify-content: center;"></div>
          
          <script>
            new TabbyPromo({
              selector: '#tabbyPromo',
              currency: 'SAR',
              price: {{ $Invoice->total }}
            });
          </script>

          <div class="mt-2 text-center">
            @if ( array_key_exists('installments', $tabby['configuration']['available_products']) )
              <a target="_blank" class="btn btn-primary btn-lg mb-1" href="{{ $tabby['configuration']['available_products']['installments'][0]['web_url'] }}">
                {{ __('master.invoice.tabby.installments') }}
              </a>
            @endif
            <br>
            @if ( array_key_exists('pay_later', $tabby['configuration']['available_products']) )
              <a target="_blank" class="btn btn-success btn-lg" href="{{ $tabby['configuration']['available_products']['pay_later'][0]['web_url'] }}">
                {{ __('master.invoice.tabby.pay_later') }}
              </a>
            @endif
          </div>
        @endif
      @endif
      
      
      @if($Invoice->notes)
      <div class="mb-3">
        <div class="text-muted mb-2">
          <b>{{ __('master.invoice.notes') }}</b>
        </div>
        {!! nl2br($Invoice->notes) !!}
      </div>
      @endif
      @if($Invoice->payment_method && $Invoice->payment_method == 'bank_transfer')
      <!-- Bank transfer information -->
      <div class="alert alert-light p-4 mt-5 mb-0">
        <h4 class="font-weight-bold mb-3">{{ __('master.invoice.payment_bank_transfer.title') }}</h4>
        <div class="row">
          <div class="col-12 col-sm-6 pb-md-0 pb-3">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.invoice.payment_bank_transfer.bank_name') }}</b>
            </div>
            <b>{{ app('settings')->bank_name }}</b>
          </div>
          <div class="col-12 col-sm-6 pb-3">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.invoice.payment_bank_transfer.bank_account_name') }}</b>
            </div>
            <b>{{ app('settings')->bank_account_name }}</b>
          </div>
          <div class="col-12 col-sm-6 ltr pb-3">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.invoice.payment_bank_transfer.bank_account_no') }}</b>
            </div>
            <b>{{ app('settings')->bank_account_no }}</b>
          </div>
          <div class="col-12 col-sm-6 ltr">
            <div class="mb-1">
              <b class="text-muted">{{ __('master.invoice.payment_bank_transfer.bank_iban') }}</b>
            </div>
            <b>{{ app('settings')->bank_iban }}</b>
          </div>
        </div>
      </div>
      @endif
      @if($Invoice->level == 0 && $Invoice->payment_method == 'online')
      <div class="payment-bar">
        <div class="container">
          <div class="row align-items-center">
            <div class="col">
              <h3>{!! __('master.invoice.waitting_payment_slug',['amount' => number_format($Invoice->total,0),'amount2' => number_format($Invoice->downpayment_amount,0) .' '.__('master.rs')]) !!}</h3>
            </div>
            <div class="col-auto mr-auto">
              @if(app('settings')->payment_provider == 'paytabs')
                <a href="{{ url()->current().'?is_pay=1' }}" class="btn btn-primary btn-lg">{{ __('master.invoice.pay_now') }}</a>
              @elseif(app('settings')->payment_provider == 'tap')
                <a class="btn btn-success btn-lg" href="{{empty($response->PaymentURL) ? '':$response->PaymentURL}}">{{ __('master.invoice.pay_now') }}</a>
              @endif
            </div>
          </div>
        </div>
      </div>
      @endif
    </div>
  </div>
  @if($is_pay_page)
    @if(app('settings')->payment_provider == 'paytabs')
      <script  src="https://www.paytabs.com/express/v4/paytabs-express-checkout.js" id="paytabs-express-checkout"
      data-secret-key="{{ app('settings')->payment_paytabs_secret_key }}"
      data-merchant-id="{{ app('settings')->payment_paytabs_merchant_id }}"
      data-url-redirect="{{ url('/i/'.$Invoice->invoice_hash.'/pay-return') }}"
      data-amount="{{ $Invoice->downpayment_amount }}"
      data-currency="SAR"
      @if($Invoice->Hotel)
        data-title="فاتورة #{{ $Invoice->id }} {{ $Invoice->Hotel->title }}"
      @elseif($Invoice->LivingRequest && $Invoice->LivingRequest->living_request_number)
        data-title="رقم الطلب #{{ $Invoice->LivingRequest->living_request_number }} .' فاتورة #'.{{ $Invoice->Hotel->title }}"
      @else
        data-title="رقم الطلب #{{ $Invoice->LivingCompany->search_id }} .' فاتورة #'"
      @endif
      data-product-names="فاتورة #{{ $Invoice->id }}"
      data-order-id="{{ $Invoice->id }}"
      data-customer-phone-number="{{ $Invoice->phone }}"
      data-customer-email-address="{{ app('settings')->email }}"
      data-customer-country-code="">
      </script>
      <script type="text/javascript">
        Paytabs.openPaymentPage();
      </script>
    @endif
  @endif
  @if($Invoice->Hotel && $Invoice->Hotel->map_cord)
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBV0UMZc_WxY9_7p0hMjL6r4xa8GF3Iv48&libraries=geometry&sensor=false"></script>
  <script type="text/javascript">
    var mapOptions = {
      zoom: 13,
      center: new google.maps.LatLng({{ $Invoice->Hotel->map_cord }})
    };
    window.hotel = {id: {{ $Invoice->Hotel->id }},map_cord: [{{ $Invoice->Hotel->map_cord }}]};
    var map = new google.maps.Map(document.getElementById('map_canvas'),
    mapOptions);
    var marker = new google.maps.Marker({
      position: map.getCenter(),
      map: map,
      icon: '/assets/images/map-marker.png'
    });


  </script>
  @endif
</body>
</html>
