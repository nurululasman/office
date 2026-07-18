<!doctype html>
<html lang="id">
    @include('layouts.back.header')
    <body class="layout-fluid">
        <div class="page">
            @include('layouts.back.top')
            @include('layouts.back.menu')
            <main class="page-wrapper">
                @if(session('status'))
                    <div class="container-xl mt-3">
                        <div class="alert alert-success" role="status">{{ session('status') }}</div>
                    </div>
                @endif
                @if($errors->any())
                    <div class="container-xl mt-3">
                        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                    </div>
                @endif
                @yield('content')
                @include('layouts.back.footer')
            </main>
        </div>
        @include('layouts.back.script')
        @stack('scripts')
    </body>
</html>
