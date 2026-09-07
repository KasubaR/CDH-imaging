<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Chililabombwe District Hospital')</title>
    <link rel="icon" href="{{ asset('images/Coat_of_arms_of_Zambia.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
    <div class="auth-layout">
        <div class="auth-layout__watermark" aria-hidden="true">
            <img src="{{ asset('images/Coat_of_arms_of_Zambia.svg') }}" alt="">
        </div>

        <main class="auth-panel">
            <div class="auth-panel__inner">
                <header class="auth-panel__brand">
                    <img
                        src="{{ asset('images/Coat_of_arms_of_Zambia.svg') }}"
                        alt="Chililabombwe District Hospital"
                        class="auth-panel__logo"
                    >
                    <div>
                        <p class="auth-panel__eyebrow">Medical Imaging Transfer System</p>
                        <h1 class="auth-panel__title">Chililabombwe District Hospital</h1>
                    </div>
                </header>

                <div class="auth-card bento-card">
                    @if ($errors->any())
                        <x-alert type="error">{{ $errors->first() }}</x-alert>
                    @endif

                    @yield('content')
                </div>
            </div>
        </main>
    </div>
</body>
</html>
