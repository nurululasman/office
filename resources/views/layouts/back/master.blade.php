<!doctype html>
<!--
* Tabler - Premium and Open Source dashboard template with responsive and high quality UI.
* @version 1.0.0-beta20
* @link https://tabler.io
* Copyright 2018-2023 The Tabler Authors
* Copyright 2018-2023 codecalm.net Paweł Kuna
* Licensed under MIT (https://github.com/tabler/tabler/blob/master/LICENSE)
-->
<html lang="en">
    @include('layouts.back.header')
    <body class=" layout-fluid">
        <div class="page">
            @include('layouts.back.top')
            @include('layouts.back.menu')
            <div class="page-wrapper">
                @yield('content')
                @include('layouts.back.footer')
            </div>
            @yield('modal')
        </div>
        @include('layouts.back.script')
        @yield('script')
    </body>
</html>
