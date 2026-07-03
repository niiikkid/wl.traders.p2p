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

                    // Apply a theme object ({type, slug, colorScheme, tokens}) to <html>.
                    // Token names/values are strictly sanitized before being inlined.
                    var apply = function(selected) {
                        if (!selected) {
                            return;
                        }

                        var isDark = selected.colorScheme === 'dark';

                        if (selected.type === 'builtin' && selected.slug) {
                            var style = document.getElementById('theme-generator-live-style');
                            if (style) {
                                style.remove();
                            }
                            root.setAttribute('data-theme', selected.slug);
                            root.classList.toggle('dark', isDark);
                            return;
                        }

                        if (selected.tokens) {
                            var css = '[data-theme="tg-live"]{color-scheme:' + (isDark ? 'dark' : 'light') + ';';
                            Object.keys(selected.tokens).forEach(function(key) {
                                if (/^--[a-z0-9-]+$/.test(key)) {
                                    css += key + ':' + String(selected.tokens[key]).replace(/[;{}<>]/g, '') + ';';
                                }
                            });
                            css += '}';

                            var el = document.getElementById('theme-generator-live-style');
                            if (!el) {
                                el = document.createElement('style');
                                el.id = 'theme-generator-live-style';
                                document.head.appendChild(el);
                            }
                            el.textContent = css;

                            root.setAttribute('data-theme', 'tg-live');
                            root.classList.toggle('dark', isDark);
                        }
                    };

                    // 1. Project-wide published theme (visible to every user).
                    var published = @json(services()->settings()->getPublishedTheme());
                    apply(published);

                    // 2. Local override for the admin who is editing in this browser.
                    var raw = window.localStorage.getItem('theme-generator:selected');
                    if (raw) {
                        apply(JSON.parse(raw));
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
