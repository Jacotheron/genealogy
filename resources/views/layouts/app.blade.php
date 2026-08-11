<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) ?: 'en' }}"
    dir="ltr"
    x-data="tallstackui_darkTheme({ dark: true })"
    x-bind:class="{ 'dark bg-gray-900': darkTheme, 'bg-gray-100': ! darkTheme }"
>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="description" content="Genealogy Application - Manage your family tree and discover your ancestry." />

    <title>
        {{ config('app.name', 'Genealogy') }}
        @yield('title')
    </title>

    <!-- favicon -->
    <link rel="icon" type="image/png" href="{{ asset('img/favicon/favicon-96x96.png?v=2026') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/favicon/favicon-96x96.svg?v=2026') }}" />
    <link rel="shortcut icon" href="{{ asset('/img/favicon/favicon.ico?v=2026') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/img/favicon/apple-touch-icon.png?v=2026') }}" />
    <meta name="apple-mobile-web-app-title" content="Theron" />
    <link rel="manifest" href="{{ asset('/img/favicon/site.webmanifest?v=2026') }}" />

    <!-- fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" />

    <!-- scripts -->
    <tallstackui:script />

    <!-- styles -->
    @livewireStyles
    @filamentStyles
    @stack('styles')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen">
        <!-- notifications -->
        <x-ts-toast />
        <x-ts-dialog />

        <!-- offcanvas menu -->
        @include('layouts.partials.offcanvas')

        <!-- header -->
        @include('layouts.partials.header')

        <!-- main content -->
        <main>
            {{ $slot }}

            <x-ts-back-to-top square color="green" />
        </main>

        <!-- footer -->
        @include('layouts.partials.footer')
    </div>

    <!-- scripts -->
    @livewireScripts
    @filamentScripts
    @stack('scripts')
</body>
</html>
