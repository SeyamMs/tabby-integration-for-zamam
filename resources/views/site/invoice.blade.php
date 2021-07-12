<!DOCTYPE html>
<html lang="{{ LaravelLocalization::getCurrentLocale() }}" dir="{{ (LaravelLocalization::getCurrentLocale() == 'ar') ? 'rtl' : 'ltr' }}" ng-app="Zamzam" ng-controller="MainCtrl">
   <head>
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <!-- CSRF Token -->
      <meta name="csrf-token" content="{{ csrf_token() }}">
      <title>{{ $Booking->Package->title }}</title>
      @if (trim($__env->yieldContent('meta_description')))
      <meta name="description" content="@yield('meta_description')" />
      @endif
      @if (trim($__env->yieldContent('meta_keywords')))
      <meta name="keywords" content="@yield('meta_keywords')" />
      @endif
      <!-- Fonts -->
      <link href="https://fonts.googleapis.com/css?family=Cairo:300,400,600,700,900&amp;subset=arabic" rel="stylesheet">
      <!-- Styles -->
      <link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">
      <link href="{{ asset('assets/css/invoice.css?v=8') }}" rel="stylesheet">
      <link rel="icon" href="{{ asset('assets/images/favicon.ico?v=1') }}" sizes="16x16 32x32 48x48 64x64" type="image/vnd.microsoft.icon" />
   </head>
   <body>
      <flash-message duration="2000" show-close="true" on-dismiss="myCallback(flash)"></flash-message>

   </body>
   <!-- Scripts -->
   <script src="{{ asset('assets/js/main.js?v=8') }}" type="text/javascript"></script>
   <script type="text/javascript">
     window.current_lang = '{{ LaravelLocalization::getCurrentLocale() }}';
   </script>
</html>
