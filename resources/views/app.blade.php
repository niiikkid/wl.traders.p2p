<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dim">
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
                try {
                    var root = document.documentElement;
                    root.setAttribute('data-theme', 'dim');
                    root.classList.add('dark');

                    var raw = window.localStorage.getItem('theme-generator:selected');
                    if (!raw) {
                        return;
                    }

                    var selected = JSON.parse(raw);
                    var isDark = selected && selected.colorScheme === 'dark';

                    if (selected && selected.type === 'builtin' && selected.slug) {
                        root.setAttribute('data-theme', selected.slug);
                        root.classList.toggle('dark', isDark);
                        return;
                    }

                    if (selected && selected.tokens) {
                        var css = '[data-theme="tg-live"]{color-scheme:' + (isDark ? 'dark' : 'light') + ';';
                        Object.keys(selected.tokens).forEach(function(key) {
                            if (/^--[a-z0-9-]+$/.test(key)) {
                                css += key + ':' + String(selected.tokens[key]).replace(/[;{}<>]/g, '') + ';';
                            }
                        });
                        css += '}';

                        var style = document.createElement('style');
                        style.id = 'theme-generator-live-style';
                        style.textContent = css;
                        document.head.appendChild(style);

                        root.setAttribute('data-theme', 'tg-live');
                        root.classList.toggle('dark', isDark);
                    }
                } catch (e) {
                    // silent
                }
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
