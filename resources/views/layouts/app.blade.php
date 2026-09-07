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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/viewer.js', 'resources/js/upload.js'])
</head>
<body class="app-body">
    <div class="sidebar-overlay" data-sidebar-overlay></div>

    <div class="app-shell">
        <x-layout.sidebar />

        <div class="app-main app-main--with-sidebar">
            <x-layout.topbar
                :title="trim(View::yieldContent('topbar_title')) ?: 'Chililabombwe District Hospital'"
                :desktop-title="trim(View::yieldContent('topbar_desktop_title')) ?: null"
            />

            <div @class(['canvas', 'canvas--flush' => $flushCanvas ?? false])>
                @if (session('status'))
                    <div class="canvas__inner">
                        <x-alert type="success">{{ session('status') }}</x-alert>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="canvas__inner">
                        <x-alert type="error">{{ $errors->first() }}</x-alert>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
