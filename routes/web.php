<?php
Route::get('/sitemap.xml', 'SiteController@sitemap')->name('sitemap');
// Auth routes
Auth::routes();
Route::get('logout', '\App\Http\Controllers\Auth\LoginController@logout');
Route::get('user-blocked', function () {
    if (!auth()->user() || auth()->user()->is_active) {
        return redirect('/');
    }
    return view('errors.user-blocked');
});

// Admin routes
Route::group(['middleware' => ['auth', 'admin'], 'prefix' => 'admin'], function () {
    Route::get('/', 'AdminController@index')->name('admin');
    // API
    Route::group(['prefix' => 'api', 'namespace' => 'Admin', 'as' => 'api.'], function () {

        /* DATATABLE ROUTES */
        Route::group(['prefix' => 'dt'], function () {
            Route::group(['middleware' => 'supervisor'], function () {
                Route::post('/pages', 'API_DatatableController@Pages');
                Route::post('/users', 'API_DatatableController@Users');
                Route::post('/bookings', 'API_DatatableController@Bookings');
                Route::post('/hotels', 'API_DatatableController@Hotels');
                Route::post('/foods', 'API_DatatableController@Foods');
                Route::post('/transport', 'API_DatatableController@Transport');
                Route::post('/company', 'API_DatatableController@Company');
                Route::post('/agent', 'API_DatatableController@Agent');
                Route::post('/parking', 'Parking\\API_ParkingController@index');
                Route::post('/parking-applications', 'Parking\\API_ParkingApplicationsController@index');
                Route::post('/applications', 'API_DatatableController@applications');
                Route::post('/animal', 'API_DatatableController@Animal');
                Route::post('/restaurant', 'API_DatatableController@Restaurant');
                Route::post('/living_company','API_DatatableController@LivingCompanies');
                Route::post('/livingRequest','API_DatatableController@livingRequest');
            });

            Route::post('/custom_offers', 'API_DatatableController@CustomOffers');
            Route::post('/invoices', 'API_DatatableController@Invoices');
            Route::get('packages', 'API_DatatableController@Packages_calener')->middleware('can:manage-packages');
            Route::post('/packages', 'API_DatatableController@Packages')->middleware('can:manage-packages');
            Route::get('offers', 'API_DatatableController@Offers_caleners')->middleware('can:manage-offers');
            Route::post('/offers', 'API_DatatableController@Offers')->middleware('can:manage-offers');

            Route::get('transport_offers', 'API_DatatableController@transport_offers_caleners')->middleware('can:manage-transports');
            Route::post('/transport_offers', 'API_DatatableController@transport_offers')->middleware('can:manage-transports');

            Route::get('company_offers', 'API_DatatableController@company_offers_caleners')->middleware('can:manage-company');
            Route::post('/company_offers', 'API_DatatableController@company_offers')->middleware('can:manage-company');

            Route::get('agent_offers', 'API_DatatableController@agent_offers_caleners')->middleware('can:manage-agent');
            Route::post('/agent_offers', 'API_DatatableController@agent_offers')->middleware('can:manage-agent');

            Route::get('animal_offers', 'API_DatatableController@animal_offers_caleners')->middleware('can:manage-animal');
            Route::post('/animal_offers', 'API_DatatableController@animal_offers')->middleware('can:manage-animal');

            Route::get('restaurant_offers', 'API_DatatableController@restaurant_offers_caleners')->middleware('can:manage-restaurant');
            Route::post('/restaurant_offers', 'API_DatatableController@restaurant_offers')->middleware('can:manage-restaurant');


            Route::post('/packages_accept', 'API_DatatableController@packages_accept')->middleware('can:manage-packages');

            Route::get('/unit-measures', 'API_SettingsController@getUnitMeasures')->middleware('can:manage-packages');
            Route::post("/livingOffer", 'API_DatatableController@livingOffer')->middleware('can:manage-living');

            // parking for operators
            Route::post('/hotel-parking', 'Parking\\API_ParkingHotelApplicationsController@index');


        });

        /* INVOICE ROUTES */
        Route::group(['prefix' => 'invoice'], function () {
            // Package
            Route::get('/show/{id}', 'API_InvoiceController@Show');
            Route::post('/save', 'API_InvoiceController@Save');
            Route::post('/send/{type}/{id}', 'API_InvoiceController@Send');
            Route::post('/delete/{id}', 'API_InvoiceController@Delete');
        });
        /* HOTEL ROUTES */
        Route::group(['prefix' => 'hotel', 'as' => 'hotels.'], function () {
            // Package

            Route::group(['middleware' => 'can:manage-packages'], function () {
                Route::get('/show-package/{package_id}', 'API_HotelController@getShowPackage');
                Route::post('/save-package', 'API_HotelController@savePackage');
                Route::post('/package-activation/{package_id}', 'API_HotelController@packageActivation');
                Route::delete('/delete-package/{package_id}', 'API_HotelController@deletePackage');
            });
            Route::get('/package-lists', 'API_HotelController@getPackageLists');
            Route::get('/hotels-list', 'API_HotelController@getHotelsList');
            Route::get('/hotels-categories-list', 'API_HotelController@hotelCategoriesList')->name('hotelCategoriesList');
            Route::post('/get-distances', 'API_HotelController@getDistance')->name('getDistance');
            Route::get('/gallery/{hotel_id}', 'API_HotelController@getHotelGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_HotelController@getShow');
                Route::post('/save', 'API_HotelController@postSave');
                Route::delete('/delete/{id}', 'API_HotelController@Delete');
                Route::post('/bulk-sms', 'API_HotelController@postBulkSMS');
            });
        });

        // wedding api calls
        Route::group(['prefix' => 'wedding'], function (){
            Route::post('/ads-settings', 'API_WeddingItemsController@settings')->name('admin.ads.api.settings');
            Route::post('/save-ad', 'API_WeddingItemsController@store')->name('admin.ads.api.add-ad');
            Route::post('/save-buy-ad', 'API_WeddingItemsController@storeBuy')->name('admin.ads.api.add-buy-ad');
            Route::post('/get-ad-data', 'API_WeddingItemsController@getAdData')->name('admin.ads.api.ad-data');
            Route::post('/bulk-sms', 'API_WeddingItemsController@postBulkSMS')->name('admin.ads.api.bulk-sms');
            Route::get('/invoice/{id}', 'API_WeddingItemsController@showInvoice')->name('admin.ads.api.invoice.show');
            Route::post('/invoice/save', 'API_WeddingItemsController@saveInvoice')->name('admin.ads.api.invoice.show');
            Route::post('/invoice/send/{type}/{id}', 'API_WeddingItemsController@Send');
            Route::delete('/invoice/delete/{id}', 'API_WeddingItemsController@deleteInvoice')->name('admin.ads.api.delete');
            Route::delete('/delete/{id}', 'API_WeddingItemsController@delete')->name('admin.ads.api.delete');
            Route::post('/wedding-gallery-via-url', 'API_UploaderController@weddingItemsGalleryViaUrl');

            Route::group(['prefix' => 'settings'], function () {
                Route::post('/show', 'API_WeddingSettingsController@index');
                Route::post('/save', 'API_WeddingSettingsController@store');
                Route::post('/delete', 'API_WeddingSettingsController@delete');
            });
        });

        // parking api
        Route::group(['prefix' => 'parking'], function () {
            Route::post('store', 'Parking\\API_ParkingController@store');
            Route::post('update', 'Parking\\API_ParkingController@update');
            Route::delete('delete/{id}', 'Parking\\API_ParkingController@delete');

            Route::post('/application/get-parkings', 'Parking\\API_ParkingApplicationsController@parkings');
            Route::post('/application-page-url', 'Parking\\API_ParkingApplicationsController@applicationPageUrl');

            Route::post('accept-reject-application', 'Parking\\API_ParkingHotelApplicationsController@action');
        });
        //=====================Start Alzubairi Updating yemen-777066658===========================
        /* Foods Routing */
        Route::group(['prefix' => 'food'], function () {
            // Package


            Route::group(['middleware' => 'can:manage-offers'], function () {
                Route::get('/show-offer/{offer_id}', 'API_FoodsCompanyController@getShowOffer');
                Route::post('/save-offer', 'API_FoodsCompanyController@saveOffer');
                Route::post('/offer-activation/{offer_id}', 'API_FoodsCompanyController@offerActivation');
            //Route::delete('/delete-offer/{offer_id}', 'API_FoodController@deleteoffer');
            });

            Route::get('/foods-list', 'API_FoodsCompanyController@getFoodsList');
            Route::get('/gallery/{food_id}', 'API_FoodsCompanyController@getFoodGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_FoodsCompanyController@getShow');
                Route::post('/save', 'API_FoodsCompanyController@postSave');
                Route::post('/delete/{id}', 'API_FoodsCompanyController@Delete');
            });
        });
        // ======================== End of Foods Routing=========================
        //==========================Start of Transport Routing ==========================
        Route::group(['prefix' => 'transport'], function () {
            // Package


            Route::group(['middleware' => 'can:manage-transports'], function () {
                Route::get('/show-offer/{offer_id}', 'API_TransportController@getShowOffer');
                Route::post('/save-offer', 'API_TransportController@saveOffer');
                Route::post('/offer-activation/{offer_id}', 'API_TransportController@offerActivation');
                Route::delete('/delete-offer/{offer_id}', 'API_TransportController@deleteoffer');
            });

            Route::get('/transport-list', 'API_TransportController@getTransportList');
            Route::get('/gallery/{transport_id}', 'API_TransportController@getTransportGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_TransportController@getShow');
                Route::post('/save', 'API_TransportController@postSave');
                Route::post('/delete/{id}', 'API_TransportController@Delete');
            });
        });

        //=========================End of Transport Routing =============================

        //==========================Start of Company  Routing ==========================
        Route::group(['prefix' => 'company'], function () {


            Route::group(['middleware' => 'can:manage-company'], function () {
                Route::get('/show-offer/{offer_id}', 'API_CompanyController@getShowOffer');
                Route::post('/save-offer', 'API_CompanyController@saveOffer');
                Route::post('/offer-activation/{offer_id}', 'API_CompanyController@offerActivation');
                Route::get('/delete-offer/{offer_id}', 'API_CompanyController@deleteoffer');
            });

            Route::get('/company-list', 'API_CompanyController@getCompanyList');
            Route::get('/gallery/{company_id}', 'API_CompanyController@getCompanyGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_CompanyController@getShow');
                Route::post('/save', 'API_CompanyController@postSave');
                Route::post('/delete/{id}', 'API_CompanyController@Delete');
            });
        });

         //==========================Start of Application  Routing ==========================
         Route::group(['prefix' => 'application'], function () {
            Route::get('/show/{id}', 'API_ApplicationController@getShow');
            Route::post('/save', 'API_ApplicationController@setApplicationOffer');
            Route::delete('/delete/{id}', 'API_ApplicationController@Delete');
            Route::post('/cancel/{id}', 'API_ApplicationController@Cancel');            
        });

        //==========================Start of Agent  Routing ==========================
        Route::group(['prefix' => 'agent'], function () {


            Route::group(['middleware' => 'can:manage-agent'], function () {
                Route::get('/show-offer/{offer_id}', 'API_AgentController@getShowOffer');
                Route::post('/save-offer', 'API_AgentController@saveOffer');
                Route::post('/offer-activation/{offer_id}', 'API_AgentController@offerActivation');
                Route::get('/delete-offer/{offer_id}', 'API_AgentController@deleteoffer');
            });

            Route::get('/agent-list', 'API_AgentController@getAgentList');
            Route::get('/gallery/{agent_id}', 'API_AgentController@getAgentGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_AgentController@getShow');
                Route::post('/save', 'API_AgentController@postSave');
                Route::post('/delete/{id}', 'API_AgentController@Delete');
            });
        });

        /**
         * ====================== start of restaurant route ===================
         */


        /**
         * ===================== end of restaurant route ===================
         */

        Route::group(['prefix' => 'restaurant'], function () {


            Route::group(['middleware' => 'can:manage-restaurant'], function () {
                Route::get('/show-offer/{offer_id}', 'API_RestaurantController@getShowOffer');
                Route::post('/save-offer', 'API_RestaurantController@saveOffer');
                Route::post('/offer-activation/{offer_id}', 'API_RestaurantController@offerActivation');
                Route::get('/delete-offer/{offer_id}', 'API_RestaurantController@deleteoffer');
            });

            Route::get('/restaurant-list', 'API_RestaurantController@getRestaurantList');
            Route::get('/gallery/{restaurant_id}', 'API_RestaurantController@getRestaurantGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_RestaurantController@getShow');
                Route::post('/save', 'API_RestaurantController@postSave');
                Route::post('/delete/{id}', 'API_RestaurantController@Delete');
            });
        });

        /**
         * ====================Start of animal route ========================
         */

        Route::group(['prefix' => 'animal'], function () {


            Route::group(['middleware' => 'can:manage-animal'], function () {
                Route::get('/show-offer/{offer_id}', 'API_AnimalController@getShowOffer');
                Route::post('/save-offer', 'API_AnimalController@saveOffer');
                Route::post('/offer-activation/{offer_id}', 'API_AnimalController@offerActivation');
                Route::get('/delete-offer/{offer_id}', 'API_AnimalController@deleteoffer');
            });

            Route::get('/animal-list', 'API_AnimalController@getAnimalList');
            Route::get('/gallery/{animal_id}', 'API_AnimalController@getAnimalGallery');

            // Hotel
            Route::group(['middleware' => 'supervisor'], function () {
                Route::get('/show/{id}', 'API_AnimalController@getShow');
                Route::post('/save', 'API_AnimalController@postSave');
                Route::post('/delete/{id}', 'API_AnimalController@Delete');
            });
        });

        /**
         * ====================End of animal route ==========================
         */
        /**Thear is route in uploudController */
        //=========================End of Company Routing =============================
        // Custom Offer
        Route::group(['prefix' => 'custom_offers'], function () {
            Route::get('/show_email/{id}', function ($id) {

                $CustomOffer = \App\CustomOffer::where('id', $id)->first();
                $CustomOffer->Hotel = \App\Hotel::first();
                $CustomOffer->value = 200;
                $CustomOffer->invoice_hash = \App\Invoice::pluck("invoice_hash")->first();
                return new App\Mail\NotifyCustomerForAddedOfferMail($CustomOffer, 'updated');
            });

            // Package
            Route::get('/show/{custom_offer_id}/{hotel_id}', 'API_CustomOfferController@Show');
            Route::get('/customoffer-lists', 'API_CustomOfferController@getLists');
            Route::post('/cancel/{custom_offer_id}/{hotel_id}', 'API_CustomOfferController@cancel');
            Route::post('/updateValue/{custom_offer_id}/{hotel_id}', 'API_CustomOfferController@updateValue');
            Route::post('/delete/{custom_offer_id}', 'API_CustomOfferController@Delete');
        });
         // Living
        Route::group(['prefix' => 'living'], function () {
            Route::group(['middleware' => 'can:manage-living'], function(){
            Route::group(['prefix' => 'livingoffer'], function(){
                // Living Offer
                Route::get('/show/{id}', 'API_LivingOfferController@Show');
                Route::post('/save', 'API_LivingOfferController@Save');
                Route::post('/send/{type}/{id}', 'API_LivingOfferController@Send');
                Route::post('/delete/{id}', 'API_LivingOfferController@Delete');
                Route::get('/gallery/{gallery_name}/{living_offer_id}', 'API_LivingOfferController@getGallery');
            });
            Route::group(['prefix' => 'livingCompany'], function(){
                Route::get('/show/{id}', 'API_LivingCompanyController@Show');
                Route::post('/save', 'API_LivingCompanyController@Save');
                Route::post('/delete/{id}', 'API_LivingCompanyController@Delete');
                Route::get('/list', 'API_LivingCompanyController@getList');
                Route::get('/gallery/{living_company_id}', 'API_LivingCompanyController@getGallery');
            });
            Route::group(['prefix' => 'livingRequest'], function(){
                Route::get('/show/{livinge_request_company_id}', 'API_LivingRequestController@Show');
                Route::get('/livingRequest-lists', 'API_LivingRequestController@getLists');
                Route::post('/cancel/{livingRequest_id}', 'API_LivingRequestController@cancel');
                Route::post('/updateOffer/{livingRequest_id}', 'API_LivingRequestController@updateOffer');
            });

            });
        });
        Route::group(['middleware' => 'supervisor'], function () {
            /* BOOKING ROUTES */
            Route::group(['prefix' => 'booking'], function () {
                Route::get('/show/{id}', 'API_BookingController@Show');
                Route::post('/UpdateDate/{id}', 'API_BookingController@UpdateDate');
                Route::delete('/delete/{id}', 'API_BookingController@Delete');
            });

            /* PAGE ROUTES */
            Route::group(['prefix' => 'page'], function () {
                Route::post('/delete', 'API_PageController@Delete');
                Route::post('/save', 'API_PageController@Save');
                Route::get('/show', 'API_PageController@Show');
            });

            /* USER ROUTES */
            Route::group(['prefix' => 'user'], function () {
                Route::get('/show', 'API_UserController@Show');
                Route::get('/list', 'API_UserController@List');
                Route::get('/list-of-all', 'API_UserController@ListByHotel');
                Route::put('/save', 'API_UserController@Save');
                Route::delete('/delete/{id}', 'API_UserController@Delete');
                Route::put('/set-action/{id}', 'API_UserController@setAction');
                Route::post('/bulk-sms', 'API_UserController@postBulkSMS');
            });

            /* SETTING ROUTES */
            Route::group(['prefix' => 'settings'], function () {
                Route::get('/show', 'API_SettingsController@Show');
                Route::put('/save', 'API_SettingsController@Save');
                Route::get('/towns-list', 'API_SettingsController@getTownsList');
                Route::get('/postiones-list', 'API_SettingsController@getPostionesList');
                Route::get('/cities-list', 'API_SettingsController@getCitiesList');
                Route::get('/rooms-types', 'API_SettingsController@getRoomsTypes');
                Route::get('/unit-measures', 'API_SettingsController@getUnitMeasures');
                Route::get('/hotel-images', 'API_SettingsController@getHotelImages');
                Route::get('/packages-types', 'API_SettingsController@getPackagesTypes');
                Route::get('/hotels-categories', 'API_SettingsController@getHotelsCategories');
                Route::get('/packages-features', 'API_SettingsController@getPackagesFeatures');
                Route::post('/save-city', 'API_SettingsController@saveCity');
                Route::post('/save-town', 'API_SettingsController@saveTown');
                Route::post('/save-room-type', 'API_SettingsController@saveRoomType');
                Route::post('/save-unit-measure', 'API_SettingsController@saveUnitMeasure');
                Route::post('/save-hotel-images', 'API_SettingsController@saveHotelImage');
                Route::post('/save-package-type', 'API_SettingsController@savePackageType');
                Route::post('/save-package-feature', 'API_SettingsController@savePackageFeature');
                Route::delete('/delete-package-type/{id}', 'API_SettingsController@deletePackageType');
                Route::delete('/delete-package-feature/{id}', 'API_SettingsController@deletePackageFeature');
                Route::delete('/delete-unit-measure/{id}', 'API_SettingsController@deleteUnitMeasure');
                Route::delete('/delete-hotel-images/{id}', 'API_SettingsController@deleteHotelImage');
                Route::delete('/delete-room-type/{id}', 'API_SettingsController@deleteRoomType');
                Route::delete('/delete-city/{id}', 'API_SettingsController@deleteCity');
            });

            /* HELPERS ROUTES */
            Route::group(['prefix' => 'helpers'], function () {
                Route::get('/sms-balance', 'API_HelpersController@getSMSBalance');
            });


            /* EXPORT */
            Route::group(['prefix' => 'export'], function () {
                Route::get('/users', 'API_ExportController@getUsers');
            });
        });

        Route::post("packages-accept/save/{id}", 'API_DatatableController@update_packages_accept');
        /* UPLOADER */
        Route::group(['prefix' => 'uploader', 'middleware' => 'auth'], function () {
            Route::post('/hotel-gallery', 'API_UploaderController@HotelGallery');
            Route::post('/wedding-gallery', 'API_UploaderController@weddingItemsGallery');
            Route::post('/hotel-gallery-via-url', 'API_UploaderController@HotelGalleryViaUrl');
            Route::post('/hotel-logo', 'API_UploaderController@HotelLogo');

            Route::post('/food-gallery', 'API_UploaderController@FoodGallery');
            Route::post('/food-logo', 'API_UploaderController@FoodLogo');

            Route::post('/transport-gallery', 'API_UploaderController@TransporGallery');
            Route::post('/transport-logo', 'API_UploaderController@TransportLogo');


            Route::post('/company-gallery', 'API_UploaderController@CompanyGallery');
            Route::post('/company-logo', 'API_UploaderController@CompanyLogo');

            Route::post('/agent-gallery', 'API_UploaderController@AgentGallery');
            Route::post('/agent-logo', 'API_UploaderController@AgentLogo');

            Route::post('/animal-gallery', 'API_UploaderController@AnimalGallery');
            Route::post('/animal-logo', 'API_UploaderController@AnimalLogo');

            Route::post('/restaurant-gallery', 'API_UploaderController@RestaurantGallery');
            Route::post('/restaurant-logo', 'API_UploaderController@RestaurantLogo');

            Route::post('/gallery/{folderName}','API_UploaderController@Gallery');
            Route::post('/logo/{folderName}','API_UploaderController@Logo');
        });
    });
});

Route::group(['middleware' => 'is-blocked'], function () {

    Route::post('/pay-return', 'BookingController@postPayReturn')->name("pay-return");
    Route::get('/pay-return', 'BookingController@postPayTapReturn')->name("pay-return");
    Route::post('/parking/pay-return', 'Parking\\ParkingInvoiceController@returnedResponseFromTap')->name("parking.pay-return");
    Route::get('/parking/pay-return', 'Parking\\ParkingInvoiceController@returnedResponseFromTap')->name("parking.pay-return");

    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath']
        ],
        function () {
            $lang = \LaravelLocalization::getCurrentLocale();
            view()->share(['lang_align' => (($lang == 'ar') ? 'right' : 'left'), 'lang_align_ops' => (($lang == 'ar') ? 'left' : 'right'), 'lang_align_fl' => (($lang == 'ar') ? 'r' : 'l'), 'lang_align_fl_ops' => (($lang == 'ar') ? 'l' : 'r')]);

            // Website API for javascript or angularjs requests
            Route::group(['prefix' => 'api'], function () {
                Route::get('/me', 'AccountController@me');
                // account
                Route::group(['prefix' => 'account'], function () {
                    Route::group(['middleware' => 'auth'], function () {
                        Route::post('/save-settings', 'AccountController@postSaveSettings');
                    });

                    Route::group(['prefix' => 'custom-offer'], function () {

                        Route::get('/custom-offer-lists', 'CustomOfferController@getCustomOfferLists');
                        Route::get("/show/{customOffer}", "CustomOfferController@show")->name('custom-offer-modal');
                        Route::post('/UpdateDate/{id}', 'CustomOfferController@UpdateDate');
                        Route::delete('/delete/{id}', 'CustomOfferController@Delete');
                        Route::post('/save', 'CustomOfferController@Save')->name('save-custom-offer');
                        Route::post('/store', 'CustomOfferController@store');
                        Route::post('/accept_offer', 'CustomOfferController@AcceptOffer');
                    });


                    Route::group(['prefix' => 'living-request'], function(){
                        Route::get('/living-request-lists','LivingRequestController@Lists');
                        Route::get("/show/{livingRequest}", "LivingRequestController@show");
                        Route::post('/UpdateDate/{id}','LivingRequestController@UpdateDate');
                        Route::delete('/delete/{id}','LivingRequestController@Delete');
                        Route::post('/save','LivingRequestController@Save');
                        Route::post('/store','LivingRequestController@store');
                        Route::post('/accept_offer','LivingRequestController@AcceptOffer');
                    });

                    Route::group(['prefix' => 'application'], function () {
                        Route::get('/get-companies/{company_type}', 'ApplicationController@getCompanies');
                        Route::post('/save-bus-request', 'ApplicationController@saveBusRequest');                      
                        Route::post('/save-embassy-request', 'ApplicationController@saveEmbassyRequest');    
                        Route::post('/save-message-request', 'ApplicationController@saveMessageRequest');
                        Route::get('/showOffers/{token}/', 'ApplicationController@getShow');
                        Route::get('/showOffers/{token}/{id}', 'ApplicationController@AcceptOffer')->name("application-accept-offer");
  
                    });

                    Route::post('/register', 'AccountController@postRegister');
                    Route::post('/reset', 'AccountController@postResetPassword');
                    Route::post('/send-phone-verification-code', 'AccountController@postSendPhoneVerificationCode');
                    Route::post('/check-phone-verification-code', 'AccountController@postCheckPhoneVerificationCode');
                    Route::post('/send-phone-verification-code-search','AccountController@postSendPhoneVerificationCodeSearch');
                    Route::post('/show-request-search','AccountController@show');
                    Route::post('/login', 'AccountController@postLogin');
                });

               
                // Hotel
                Route::group(['prefix' => 'hotel'], function () {
                    Route::get('/packages/{hotel_id}', 'HotelController@apiHotelPackages');
                    Route::get('/package/{id}', 'HotelController@apiPackage');
                });

                Route::group(['prefix' => 'livingCompany'],function(){
                    Route::get('/livingOffers/{living_company_id}','LivingCompaniesController@apiLivingOffers');
                    Route::get('/package/{id}','LivingCompaniesController@apiPackage');
                    Route::post('/set-pay-info', 'LivingCompaniesController@apiPostSetPayInfo');

                });

                // Booking
                Route::group(['prefix' => 'booking'], function () {
                    Route::post('/start', 'BookingController@apiPostStart');
                    Route::post('/set-pay-info', 'BookingController@apiPostSetPayInfo');
                });


                // Contact
                Route::post('/contact-us', 'SiteController@postContact');
                Route::post('/custom-offer-guest', 'SiteController@storeCustomOffer');


                Route::post('/custom-offer-search', 'CustomOfferSearchController@postSearchCustomOffers');
                Route::post('/custom-offer-verify-code', 'CustomOfferSearchController@verifyCode');
                Route::post('/living-request-guest', 'SiteController@storeLivingRequest');

                // parking api calls
                Route::group(['prefix' => 'parking'], function (){
                    Route::post('/application', 'Parking\\ParkingApplicationController@store');
                    Route::post('/parking-items', 'Parking\\ParkingApplicationController@parkingItems');
                    Route::post('/parking-packages', 'Parking\\ParkingApplicationController@parkingPackages');
                    Route::post('/applications-search', 'Parking\\ParkingApplicationController@search');
                });
                
                //Uloader Files
                Route::group(['prefix' => 'uploader'], function(){
                    Route::post('/gallery/{folderName}','APIv1\UploaderController@Gallery');
                    Route::post('/logo/{folderName}','APIv1\UploaderController@Logo');
                });
                

            });

            // Site routes
            // Route::get('/test', 'SiteController@test')->name('test');
            Route::get('/home', function () {
                return redirect('/');
            });
            Route::get('/test', 'SiteController@indexNew');
            Route::get('/', 'SiteController@index')->name('landing');
            Route::get('/b/{hash}', 'BookingController@getBookedDetails')->name('booked-details');
            Route::get('/b/{hash}/{stayed}', 'BookingController@setBookedCustomerReview')->name('booked-customer-review');
            Route::get('/i/{hash}', 'InvoiceController@getInvoice')->name('invoice-details');
            // Route::post('/i/{hash}/pay-return', 'InvoiceController@postPayReturn');
            Route::get('/i/{hash}/pay-return', 'InvoiceController@postPayTapReturn')->name("invoice-pay-return");

            // account routes
            Route::group(['prefix' => 'account', 'middleware' => 'auth'], function () {
                Route::get('/bookings', 'AccountController@Bookings')->name('account-bookings');
                Route::get('/custom-offer', 'CustomOfferController@index')->name('account-customOffers');
                Route::post('/custom-offers', 'CustomOfferController@CustomOffers');
                Route::get('/living-request', 'LivingRequestController@index')->name('account-livingrequest');
                Route::post('/living-requests', 'LivingRequestController@LivingRequests');
                Route::get('/bookings/{id}', 'AccountController@showBooking')->name('account-booking');
                Route::get('/settings', 'AccountController@settings')->name('account-settings');

            });

            Route::get('/booking/{package_id}', 'BookingController@index')->name('booking');
            Route::get('parking/invoice/{application}/{parking}/pay', 'Parking\\ParkingInvoiceController@update')->name('parking.invoice.update');
            Route::get('parking/invoice/{application}', 'Parking\\ParkingInvoiceController@show')->name('parking.invoice.show');
            Route::get('parking/application/{application}', 'Parking\\ParkingApplicationController@show')->name('parking.application.show');

            Route::get(LaravelLocalization::transRoute('routes.packages'), 'HotelController@packagesList')->name('packages');
            Route::get(LaravelLocalization::transRoute('routes.hotels'), 'HotelController@hotelsList')->name('hotels');
            Route::get(LaravelLocalization::transRoute('routes.show_hotel'), 'HotelController@showHotel')->name('show_hotel');
            Route::get(LaravelLocalization::transRoute('routes.show_package'), 'HotelController@showPackage')->name('show_package');

            Route::get(LaravelLocalization::transRoute('routes.living_companies'), 'LivingCompaniesController@livingsList')->name('living_companies');
            Route::get(LaravelLocalization::transRoute('routes.show_living_companies') . '/{slug}/{id}', 'LivingCompaniesController@showLivingCompany')->name('show_living_companies');
            Route::get('/livingbooking/{livingOffer_id}', 'LivingCompaniesController@showOffer')->name('livingbooking');


            Route::get('/{slug}', 'SiteController@page')->name('page');

        });


});







