@php
    $themeLight = (string) (env('VITE_THEME_LIGHT') ?: 'winter') ?: 'winter';
    $themeDark = (string) (env('VITE_THEME_DARK') ?: 'dim') ?: 'dim';
    $themeStorageKey = (string) (env('VITE_THEME_STORAGE_KEY') ?: 'color-theme-payment') ?: 'color-theme-payment';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="{{ $themeDark }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            (function() {
                var THEME_LIGHT = @json($themeLight);
                var THEME_DARK = @json($themeDark);
                var THEME_KEY = @json($themeStorageKey);
                try {
                    var saved = localStorage.getItem(THEME_KEY) || localStorage.getItem('theme');
                    var theme = saved;
                    if (!theme) {
                        theme = THEME_DARK;
                    }
                    document.documentElement.setAttribute('data-theme', theme);
                    if (theme === THEME_DARK) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {
                    // silent
                }
            })();
        </script>

        <!-- Scripts -->
        @routes
        <!-- добавил это, чтобы локально HERD локально шеррил, ссылку нормально, публичую -->
        @if (app()->isLocal())
            {{ Vite::useHotFile(storage_path('vite.hot'))->withEntryPoints([

                'resources/js/app.js',

                "resources/js/Pages/{$page['component']}.vue",

            ]) }}
        @else
            @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
