  <head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>YAHER | @yield('tab_tittle') </title>
    <!-- CSS files -->
    <link href="{{ asset('css/tabler.min.css')}}" rel="stylesheet"/>
    <link href="{{ asset('css/tabler-flags.min.css')}}" rel="stylesheet"/>
    <link href="{{ asset('css/tabler-payments.min.css')}}" rel="stylesheet"/>
    <link href="{{ asset('css/tabler-vendors.min.css')}}" rel="stylesheet"/>
    <link href="{{ asset('css/demo.min.css')}}" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
      @import url('https://rsms.me/inter/inter.css');
      :root {
      	--tblr-font-sans-serif: 'Inter Var', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif;
      }
      body {
      	font-feature-settings: "cv03", "cv04", "cv11";
      }
    </style>

    @yield('styles')

  </head>
