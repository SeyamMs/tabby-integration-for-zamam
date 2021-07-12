@extends('layouts.app')
@section("title",$Package->title.' '.__('master.in').' '.$Package->Hotel->title)
@section("meta_description",$Package->description)
@section('content')
    <div class="page-container" ng-controller="BookingCtrl">
        <div class="container">
            @if($Package->inventory == 0 && auth()->user()->is_only_hotel_operator)
                <div class="alert alert-warning"><b>تنويه:</b> هذا العرض لا يحتوي على غرف كافية ليتم حجزها وبالتالي فإنه
                    مخفي عن الظهور للمسافرين
                </div>
            @endif
            <div class="row">
                <!-- Package Info -->
                <div class="col-sm-4">
                    <div class="panel panel-default booking-panel booking-package">
                        <div class="title">
                            <h3>{{ __('master.booking_info') }}</h3>
                            @if($Package->discount_rate)
                                <div class="discount">{{ __('master.discount') }}
                                    <span>%{{ $Package->discount_rate }}</span></div>@endif
                        </div>
                        <div class="image">
                            <img alt="" class="" src="{{ asset('/uploads/hotels/thumb_'.$Package->image) }}">
                        </div>
                        <div class="package-details">
                            <div class="text-center">
                                <h1>{{ $Package->Hotel->title }}</h1>
                                <span class="stars">
                                    @for($i = 0;$i < Illuminate\Support\Str::limit($Package->Hotel->stars_count, 1);$i++)
                                        <i class="ic-star"></i>
                                    @endfor
                                </span>
                                <h4 class="mt-2">{{ $Package->title }}</h4>
                                <div class="divider mb-4"></div>
                                <div class="d-flex text-{{ $lang_align }}">
                                    <i class="ic-map-marker m{{ $lang_align_fl_ops }}-2 text-primary"
                                       style="margin-top: 3px;"></i>
                                    <div class="text"> {{ $Package->Hotel->address }}
                                        @if($Package->Hotel->distance_from_haram)
                                            <div class="mt-1">
                                                {{ __('master.distance_from_haram') }} <b
                                                        class="text-primary mx-2">{{ $Package->Hotel->distance_from_haram.' '.__('master.meter') }}</b>
                                            </div>
                                        @endif
                                        @if($Package->Hotel->distance_from_kaaba)
                                            <div class="mt-1">
                                                {{ __('master.distance_from_kaaba') }} <b
                                                        class="text-primary mx-2">{{ $Package->Hotel->distance_from_kaaba.' '.__('master.meter') }}</b>
                                            </div>
                                        @endif
                                        @if($Package->Hotel->distance_from_jamarat)
                                            <div class="mt-1">
                                                {{ __('master.distance_from_jamarat') }} <b
                                                        class="text-primary mx-2">{{ $Package->Hotel->distance_from_jamarat.' '.__('master.meter') }}</b>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <!-- Map -->
                                <div class="map mt-3 mb-3" id="map_canvas" style="width:100%; height:200px;"></div>
                            </div>
                        </div>
                        <div class="page-view-loader"></div>
                        <div class="page-view-loaded" style="display: none;">
                            <div class="divider mt-4"></div>
                            @if($is_pay_page)
                                <div class="row">
                                    <div class="col-6">
                                        <div class="control-label font-weight-bold mb-2"><i
                                                    class="ic-calendar text-muted"></i> {{ __('master.form.checkin_date') }}
                                        </div>
                                        {{ session('booking_'.$Package->id.'.check_in_date') }}
                                    </div>
                                    @if(!$Package->is_atomic)
                                        <div class="col-6">
                                            <div class="control-label font-weight-bold mb-2"><i
                                                        class="ic-calendar text-muted"></i> {{ __('master.form.checkout_date') }}
                                            </div>
                                            {{ session('booking_'.$Package->id.'.check_out_date') }}
                                        </div>
                                    @endif
                                </div>
                            @elseif($Package->is_atomic)
                                <div class="row">
                                    <div class="col-12">
                                        <div class="control-label font-weight-bold mb-2"><i
                                                    class="ic-calendar text-muted"></i> {{ __('master.form.checkin_date') }}
                                        </div>
                                        <div class="input-icon">
                                            <i class="ic-calendar"></i>
                                            <input type="text" class="form-control mb-md-0 mb-3" required
                                                   datepicker-options="startDateOptions"
                                                   ng-class="{'focus': open_start_date}"
                                                   datepicker-append-to-body="true" uib-datepicker-popup="dd/MM/y"
                                                   ng-focus="open_start_date = true" name="start_date"
                                                   ng-model="booking.check_in_date" is-open="open_start_date"/>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light mt-2 mt-sm-4 text-center text-sm-{{ $lang_align }}">
                                    <div class="mb-sm-2 text-muted mb-1">
                                        <b>{{ __('master.package_period') }}</b>
                                    </div>
                                    <div class="d-flex feature-iconed justify-content-center justify-content-sm-start">
                                        <i class="ic-calendar text-primary"></i>
                                        <b class="ng-binding">{# (package.start_date | date:'dd/MM/yyyy - H:00 a') #}
                                            <small class="text-muted mx-1">{{ __('master.until') }}</small>
                                            {# (package.end_date | date:'dd/MM/yyyy - H:00 a') #}
                                        </b>
                                    </div>
                                </div>
                                <div class="alert alert-light mt-2 mt-sm-4 text-center text-sm-{{ $lang_align }}">
                                    <div class="d-flex feature-iconed justify-content-center justify-content-sm-start h4">
                                        <b>
                                            {{$Package->UnitMeasure}}
                                        </b>
                                    </div>
                                </div>
                            @else
                                <div class="row">
                                    <div class="col-6">
                                        <div class="control-label font-weight-bold mb-2"><i
                                                    class="ic-calendar text-muted"></i> {{ __('master.form.checkin_date') }}
                                        </div>
                                        <div class="input-icon">
                                            <i class="ic-calendar"></i>
                                            <input type="text" class="form-control mb-md-0 mb-3" required
                                                   datepicker-options="startDateOptions"
                                                   ng-class="{'focus': open_start_date}"
                                                   datepicker-append-to-body="true" uib-datepicker-popup="dd/MM/y"
                                                   ng-focus="open_start_date = true" name="start_date"
                                                   ng-model="booking.check_in_date" is-open="open_start_date"/>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="control-label font-weight-bold mb-2"><i
                                                    class="ic-calendar text-muted"></i> {{ __('master.form.checkout_date') }}
                                        </div>
                                        <div class="input-icon">
                                            <i class="ic-calendar"></i>
                                            <input type="text" class="form-control" required
                                                   datepicker-options="searchEndDateOptions"
                                                   ng-class="{'focus': open_end_date}" datepicker-append-to-body="true"
                                                   uib-datepicker-popup="dd/MM/y" ng-focus="open_end_date = true"
                                                   name="end_date" ng-model="booking.check_out_date"
                                                   is-open="open_end_date"/>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light mt-2 mt-sm-4 text-center text-sm-{{ $lang_align }}">
                                    <div class="mb-sm-2 text-muted mb-1"><b>{{ __('master.package_period') }}</b></div>
                                    <div class="d-flex feature-iconed justify-content-center justify-content-sm-start">
                                        <i class="ic-calendar text-primary"></i><b class="ng-binding">{#
                                            (package.start_date | dateF) #} <small
                                                    class="text-muted mx-1">{{ __('master.until') }}</small> {#
                                            (package.end_date | dateF) #}</b></div>
                                </div>
                            @endif
                            <div class="divider"></div>
                            <div class="row text-center">
                                <div class="col">
                                    <span ng-bind="booking.rooms" class="h4 font-weight-bold"></span>
                                    <div class="col-form-label pb-0 mt-1 mb-0">{{ __('master.rooms') }}</div>
                                </div>
                                <div class="col">
                                    @if(!empty($Package->unit_count) && $Package->unit_count > 0)
                                        <span class="h4 font-weight-bold">
                                        {{$Package->unit_count}}
                                        </span>
                                    @else
                                        <span ng-bind="calculateNights()" class="h4 font-weight-bold">
                                        </span>
                                    @endif
                                    <div class="col-form-label pb-0 mt-1 mb-0">{{ __('master.nights') }}</div>
                                </div>
                                <div class="col">
                                    <span ng-bind="package.adults*booking.rooms" class="h4 font-weight-bold"></span>
                                    <div class="col-form-label pb-0 mt-1 mb-0">{{ __('master.adults') }}</div>
                                </div>
                                <div class="col" ng-show="package.children">
                                    <span ng-bind="package.children*booking.rooms" class="h4 font-weight-bold"></span>
                                    <div class="col-form-label pb-0 mt-1 mb-0">{{ __('master.children') }}</div>
                                </div>
                            </div>
                            <div class="px-5 mt-5">
                                <a class="btn btn-primary btn-block px-3 py-1 rounded" ng-hide="show_add_rooms"
                                   ng-click="show_add_rooms = true"><i
                                            class="ic-add small mx-2"></i>{{ __('master.add_rooms') }}</a>
                                <div ng-show="show_add_rooms" class="number-picker">
                                    <h-number value="booking.rooms" singular="{{ __('master.rooms') }}"
                                              plural="{{ __('master.rooms') }}" min="1" max="{{ $Package->inventory }}"
                                              step="1"></h-number>
                                </div>
                            </div>
                            <div class="text-danger small font-weight-bold mt-3 text-center"
                                 ng-show="booking.rooms == package.inventory && show_add_rooms">{{ __('master.msg.no_more_rooms') }}</div>
                            <div class="divider"></div>
                            @if($Package->room_space || $Package->single_beds || $Package->double_beds)
                                <div class="mb-3">
                                    <div class="col-form-label">{{ __('master.room_features') }}</div>
                                    <div class="row mb-1">
                                        <div class="col-5">
                                            <span class="text-muted">{{ __('master.form.room_type') }}</span>
                                        </div>
                                        <div class="col-7">
                                            <b>{{ $Package->RoomType->name }}</b>
                                        </div>
                                    </div>
                                    @if($Package->room_space)
                                        <div class="row mb-1">
                                            <div class="col-5">
                                                <span class="text-muted">{{ __('master.booked_details.room_space') }}</span>
                                            </div>
                                            <div class="col-7">
                                                <b>{{ $Package->room_space }} <small>m2</small></b>
                                            </div>
                                        </div>
                                    @endif
                                    @if($Package->single_beds)
                                        <div class="row mb-1">
                                            <div class="col-5">
                                                <span class="text-muted">{{ __('master.booked_details.single_bed') }}</span>
                                            </div>
                                            <div class="col-7">
                                                <b>{{ $Package->single_beds.' '.__('master.booked_details.bed') }}</b>
                                            </div>
                                        </div>
                                    @endif
                                    @if($Package->double_beds)
                                        <div class="row mb-1">
                                            <div class="col-5">
                                                <span class="text-muted">{{ __('master.booked_details.double_bed') }}</span>
                                            </div>
                                            <div class="col-7">
                                                <b>{{ $Package->double_beds.' '.__('master.booked_details.bed') }}</b>
                                            </div>
                                        </div>
                                    @endif
                                    @if($Package->bathrooms_count)
                                        <div class="row mb-1">
                                            <div class="col-5">
                                                <span class="text-muted">{{ __('master.booked_details.bathrooms_count') }}</span>
                                            </div>
                                            <div class="col-7">
                                                <b>{{ $Package->bathrooms_count }}</b>
                                            </div>
                                        </div>
                                    @endif
                                    @if($Package->kitchens_count)
                                        <div class="row mb-1">
                                            <div class="col-5">
                                                <span class="text-muted">{{ __('master.booked_details.kitchens_count') }}</span>
                                            </div>
                                            <div class="col-7">
                                                <b>{{ $Package->kitchens_count }}</b>
                                            </div>
                                        </div>
                                    @endif
                                    @if($Package->room_cooler)
                                        <div class="row mb-1">
                                            <div class="col-5">
                                                <span class="text-muted">{{ __('master.booked_details.cooler_type') }}</span>
                                            </div>
                                            <div class="col-7">
                                                <b>{{ __('master.cooler_types.'.$Package->room_cooler) }}</b>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <div class="col-form-label">{{ __('master.package_features') }}</div>
                            <div class="features">
                                <div class="mt-1" ng-repeat="feature in package.features">
                                    <div class="d-flex feature-label">
                                        <i class="ic-check"></i>
                                        <div class="text">{# feature.details.name #} <span ng-if="!feature.price">({{ __('master.free') }})</span><span
                                                    ng-if="feature.price" class="feature-price">({# (feature.price | parse_float) | price #} {{ __('master.rs') }}+)</span></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if($Package->description)
                                <div class="my-1">
                                    {!! $Package->description !!}
                                </div>
                            @endif
                            <div class="divider"></div>
                            <div class="row">
                                <div class="col-6 price">
                                    <h4 class="font-weight-bold text-muted">{{ __('master.total_amount') }}</h4>
                                    <div class="amount text-primary">
                                        {# getPackagePrice('total') | price #} <small>{{ __('master.rs') }}</small>
                                    </div>
                                </div>
                                <div class="col-6 text-{{ $lang_align_ops }}">
                                    <div class="installments">
                                        <div class="mb-3">
                                            <h4>{{ __('master.downpayment_amount') }}</h4>
                                            <div class="amount">
                                                {# getPackagePrice('downpayment') | price #}
                                                <small>{{ __('master.rs') }}</small>
                                            </div>
                                        </div>
                                        <div>
                                            <h4>{{ __('master.upon_arrival_amount') }}</h4>
                                            <div class="amount">
                                                {# getPackagePrice('upon_arrival') | price #}
                                                <small>{{ __('master.rs') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Booking Info -->
                <div class="col-sm-8">
                    <div class="panel booking-panel">
                        {{--          @if(!auth()->user() && (!$Package->checkBeforePay && $is_pay_page))--}}
                        {{--            <div class="title">--}}
                        {{--              <h3>{{ __('master.guest_info') }}</h3>--}}
                        {{--            </div>--}}
                        {{--            <div class="alert p-5 text-center border">--}}
                        {{--              <h2>{{ __('master.slugs.book_package_now') }}</h2>--}}
                        {{--              <div class="d-sm-flex justify-content-center align-items-center mt-5 mb-2">--}}
                        {{--                <a class="btn btn-lg btn-primary px-5 py-2" ng-click="startBooking('login')">{{ __('master.login') }}</a>--}}
                        {{--                <span class="text-muted d-inline-block my-sm-0 mx-4 my-3">{{ __('master.or') }}</span>--}}
                        {{--                <a class="btn btn-lg btn-primary px-5 py-2" ng-click="startBooking('register')">{{ __('master.register') }}</a>--}}
                        {{--              </div>--}}
                        {{--            </div>--}}
                        @if($Package->checkBeforePay && $is_pay_page)
                            <span class="alert-success h3" style="padding: 2px;">  تم حفظ طلبكم </span><br>
                            <p> يجب التأكد ان العرض مازال متاح سوف نرسل لك رساله قريباً جداً <b>لتأكيد الحجز</b> و <b>دفع
                                    الدفعة المقدمة<b/>
                            </p>
                            <h3> للاسفسارات
                                <a href="http://iwtsp.com/966599950293" target="_blank">
                                    <i class="ic-whatsapp"
                                       style="color: green;font-size: 26px;margin-left: 5px;margin-right: 5px;"></i>
                                </a>
                            </h3>
                        @else
                            <div class="page-view-loader">
                            </div>
                            <div class="page-view-loaded" style="display: none;">
                                @if($errors->any())
                                    <div class="alert alert-danger text-center">{{ __('master.msg.payment_error') }}</div>
                                @endif
                                <!-- Info Tab -->
                                <div ng-show="current_tab == 'info'">
                                    <form name="Form" id="Form">
                                        <div class="title">
                                            <h3>{{ __('master.guest_info') }}</h3>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <div class="col-form-label">{{ __('master.form.name') }}</div>
                                                    @if($is_pay_page)
                                                        <div class="form-control-static h4">
                                                            {{ session('booking_'.$Package->id.'.name') }}
                                                        </div>
                                                    @else
                                                        <input type="text" class="form-control"
                                                               ng-model="booking.name"/>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <div class="col-form-label">{{ __('master.form.phone') }}</div>
                                                    @if($is_pay_page)
                                                        <div class="form-control-static h4 ltr">
                                                            {{ session('booking_'.$Package->id.'.phone') }}
                                                        </div>
                                                    @else
                                                        <input type="text" class="form-control"
                                                               ng-class="{'is-invalid': Form.phone.$invalid && Form.phone.$touched}"
                                                               edit-mode="true" name="phone" required
                                                               ng-model="booking.phone" ng-intl-tel-input>
                                                        <div class="invalid-feedback"
                                                             ng-class="{'d-block': Form.phone.$invalid && Form.phone.$touched}">{{ __('master.msg.incorrect_phone') }}</div>
                                                    @endif
                                                </div>
                                            </div>

                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <div class="col-form-label">{{ __('master.form.email') }}</div>
                                                    @if($is_pay_page)
                                                        <div class="form-control-static h4">
                                                            {{ session('booking_'.$Package->id.'.email') }}
                                                        </div>
                                                    @else
                                                        <input type="email" class="form-control"
                                                               ng-model="booking.email"/>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <div class="col-form-label">{{ __('master.form.country') }}</div>
                                                    @if($is_pay_page)
                                                        <div class="form-control-static h4">
                                                            {{ session('booking_'.$Package->id.'.country') }}
                                                        </div>
                                                    @else
                                                        @component('site.components.countries-input',['ngModel' => 'booking.country']) @endcomponent
                                                    @endif

                                                </div>
                                            </div>
                                        </div>
                                        @if($is_pay_page)
                                            @if(session('booking_'.$Package->id.'.notes'))
                                                <div class="form-group">
                                                    <div class="col-form-label">{{ __('master.form.notes') }}</div>
                                                    <div class="h4">
                                                        {{ session('booking_'.$Package->id.'.notes') }}
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="form-group">
                                                <div class="col-form-label">{{ __('master.form.notes') }}</div>
                                                <textarea class="form-control" ng-model="booking.notes"></textarea>
                                            </div>
                                        @endif
                                        <div class="mt-5 mb-3 text-center">
                                            @if(!$is_pay_page)
                                                <button type="submit" class="btn btn-lg btn-success px-5"
                                                        ng-click="Pay(Form.$valid)">{{ __('master.form.pay_btn') }}</button>
                                            @else
                                                @if ( $PayAmount )
                                                    @if ( is_null($tabby) )
                                                        <div class="alert alert-danger text-center">{{ __('master.invoice.tabby.load_faild') }}</div>
                                                    @else
                                                        @if ($tabby && in_array($tabby['status'], ['created', 'approved']) )
                                                        <script src="https://checkout.tabby.ai/tabby-promo.js"></script>
                                                        <div id="tabbyPromo" class="mt-2" style="display: flex;justify-content: center;"></div>
                                                        
                                                        <script>
                                                            new TabbyPromo({
                                                                selector: '#tabbyPromo',
                                                                currency: 'SAR',
                                                                price: `{{ $tabby["payment"]["amount"] }}`,
                                                            });
                                                        </script>

                                                        <div class="mt-2 mb-1 text-center">
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
                                                    
                                                    @if(app('settings')->payment_provider == 'paytabs')
                                                        <button type="submit" class="btn btn-lg btn-success px-5"
                                                                ng-click="payAgain()">{{ __('master.form.pay_btn') }}</button>
                                                    @elseif(app('settings')->payment_provider == 'tap' && $response)
                                                        <a class="btn btn-lg btn-success px-5"
                                                        href="{{$response->PaymentURL}}">{{ __('master.invoice.pay_now') }}</a>
                                                    @endif                  
                                                @endif
                                            @endif
                                        </div>
                                    </form>

                                    @if( $is_pay_page && !$PayAmount )
                                        <form action="{{ route('store-booking', $Package->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-lg btn-success px-5">{{ __('master.form.confirm_booking') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    @if($is_pay_page  && !$Package->checkBeforePay)
        @if ( $PayAmount )
            @if(app('settings')->payment_provider == 'paytabs')
                <script src="https://www.paytabs.com/express/v4/paytabs-express-checkout.js" id="paytabs-express-checkout"
                        data-secret-key="{{ app('settings')->payment_paytabs_secret_key }}"
                        data-merchant-id="{{ app('settings')->payment_paytabs_merchant_id }}"
                        data-url-redirect="{{ url('/pay-return') }}"
                        data-amount="{{ $PayAmount }}"
                        data-currency="SAR"
                        data-title="{{ ((session('booking_'.$Package->id.'.name')) ? session('booking_'.$Package->id.'.name').' - ' : '').$Package->title_ar.' - '.$Package->Hotel->title }}"
                        data-product-names="{{ $Package->title_ar }}"
                        data-order-id="{{ $Package->id }}"
                        data-customer-phone-number="{{ session('booking_'.$Package->id.'.phone') }}"
                        data-customer-email-address="{{ (session('booking_'.$Package->id.'.email')) ? session('booking_'.$Package->id.'.email') : app('settings')->email }}"
                        data-customer-country-code=""></script>
            @endif
        @endif
    @else
        <script src="{{ asset('assets/js/intlTelInput.js') }}"></script>
        <link href="{{ asset('assets/css/intlTelInput.min.css') }}" rel="stylesheet" type="text/css">
    @endif
    <script type="text/javascript">
        window.is_pay_page = {{ ($is_pay_page) ?  1 : 0 }};
        window.package = {id: {{ $Package->id }}};
        window.booking = {selected_features: {}};
        @if(auth()->user())
            window.auth = {
            name: '{{ auth()->user()->name }}',
            id: '{{ auth()->user()->id }}',
            phone: '{{ auth()->user()->phone }}',
            email: '{{ auth()->user()->email }}',
            country: '{{ auth()->user()->country }}'
        };
            @if(!$is_pay_page)
                window.booking.name = '{{ auth()->user()->name }}';
                window.booking.phone = '{{ auth()->user()->phone }}';
                window.booking.email = '{{ auth()->user()->email }}';
                window.booking.country = '{{ auth()->user()->country }}';
            @endif
        @endif
        @if(request()->sdate && request()->edate)
            window.booking.check_in_date = '{{ request()->sdate }}';
            window.booking.check_out_date = '{{ request()->edate }}';
        @endif
        @if(request()->sfeatures)
            @foreach(explode('-',request()->sfeatures) as $v)
                window.booking.selected_features[{{ $v }}] = true;
            @endforeach
        @endif
        @if(session()->has('booking_'.$Package->id) && !request()->sdate)
            @if($is_pay_page)
                window.booking.rooms = '{{ session('booking_'.$Package->id.'.rooms') }}';
            @endif
            window.booking.check_in_date = '{{ session('booking_'.$Package->id.'.check_in_date') }}';
            window.booking.check_out_date = '{{ session('booking_'.$Package->id.'.check_out_date') }}';
            @if(is_array(session('booking_'.$Package->id.'.selected_features')))
                @foreach(session('booking_'.$Package->id.'.selected_features') as $k => $v)
                    window.booking.selected_features[{{ $k }}] = {{ var_export(($v) ? true : false) }};
                @endforeach
            @endif
        @endif
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBV0UMZc_WxY9_7p0hMjL6r4xa8GF3Iv48&libraries=geometry&sensor=false"></script>
    @if($Package->Hotel->map_cord)
        <script type="text/javascript">
            var mapOptions = {
                zoom: 13,
                disableDefaultUI: true,
                zoomControl: true,
                fullscreenControl: true,
                center: new google.maps.LatLng({{ $Package->Hotel->map_cord }})
            };
            window.hotel = {id: {{ $Package->Hotel->id }}, map_cord: [{{ $Package->Hotel->map_cord }}]};
            var map = new google.maps.Map(document.getElementById('map_canvas'),
                mapOptions);
            var marker = new google.maps.Marker({
                position: map.getCenter(),
                map: map,
                icon: '/assets/images/map-marker.png'
            });
        </script>
    @endif
@endsection
