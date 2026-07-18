<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name') }}</title>
    <link href="{{ asset('css/tabler.min.css') }}" rel="stylesheet">
</head>
<body class="d-flex flex-column bg-light">
    <main class="page page-center">
        <div class="container-tight py-4 text-center">
            <img src="{{ asset('static/jblu.png') }}" width="80" height="80" alt="JBLU" class="mb-4" style="object-fit: contain">
            <div class="display-1 text-secondary">@yield('code')</div>
            <h1 class="h2 mb-3">@yield('title')</h1>
            <p class="text-secondary mb-4">@yield('message')</p>
            <a href="{{ auth()->check() ? route('office.home') : route('welcome') }}" class="btn btn-primary">Kembali</a>
        </div>
    </main>
</body>
</html>
