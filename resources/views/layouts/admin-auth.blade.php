@php
    $title ??= null;
    $cardLabel ??= 'Connexion';
    $supportEmail = config('admin.support_email');
    $privacyUrl = config('admin.privacy_url');
    $version = config('admin.version');
    $pageTitle = filled($title ?? null) ? $title.' · Back-office Allo Vaho' : 'Back-office Allo Vaho';
@endphp

{{-- Layout of the back-office sign-in pages, reproducing the validated mockups (docs/references). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $pageTitle }}</title>

        <link rel="icon" href="{{ asset('images/logo-allo-vaho.png') }}">

        {{-- Theme applied before the first paint (same storage key as the panel). --}}
        <script>
            (function () {
                var theme = null;
                try { theme = localStorage.getItem('theme'); } catch (e) {}
                var dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        @vite(['resources/css/admin/fonts.css', 'resources/css/admin/auth-app.css', 'resources/js/admin/auth.js'])
    </head>

    <body class="av-auth-page">
        <x-auth.sprite />

        <div class="av-auth-shell">
            <aside class="av-auth-brand" aria-label="Présentation du back-office">
                <div class="av-auth-brand__panel">
                    <svg class="av-auth-brand__art" viewBox="0 0 600 600" aria-hidden="true" focusable="false">
                        <circle class="av-art-ring" cx="300" cy="290" r="258"></circle>
                        <circle class="av-art-ring" cx="300" cy="290" r="318"></circle>
                        <circle class="av-art-ring" cx="300" cy="290" r="392"></circle>
                        <path class="av-art-bubble" d="M300 80 A210 210 0 1 1 151.5 438.5 L64 524 L100.3 354.9 A210 210 0 0 1 300 80 Z"></path>
                        <circle class="av-art-core" cx="300" cy="290" r="128"></circle>
                        <circle class="av-art-accent" cx="468" cy="132" r="30"></circle>
                    </svg>
                    <div class="av-auth-brand__content">
                        <p class="av-auth-brand__eyebrow"><span class="av-auth-brand__dot"></span>Back-office Allo Vaho</p>
                        <p class="av-auth-brand__title">Tout Allo Vaho,<br>au même endroit.</p>
                        <p class="av-auth-brand__text">Abonnements, activations Camtel, paiements et support : l’espace de travail de vos équipes.</p>
                        <ul class="av-auth-brand__pillars">
                            <li><x-auth.icon name="shield" />Double authentification</li>
                            <li><x-auth.icon name="badge" />Accès selon le rôle</li>
                            <li><x-auth.icon name="scroll" />Actions journalisées</li>
                        </ul>
                    </div>
                </div>
            </aside>

            <main class="av-auth-main">
                <header class="av-auth-top">
                    <a class="av-auth-logo" href="{{ filament()->getLoginUrl() }}" aria-label="Allo Vaho — connexion"><img src="{{ asset('images/logo-allo-vaho.png') }}" alt="Allo Vaho" width="310" height="176"></a>
                    <div class="av-auth-top__actions">
                        <button type="button" class="av-theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Activer le mode sombre"><x-auth.icon name="sun" class="av-theme-toggle__sun" /><x-auth.icon name="moon" class="av-theme-toggle__moon" /></button>
                    </div>
                </header>

                <div class="av-auth-center">
                    <section class="av-auth-card" aria-label="{{ $cardLabel }}">
                        {{ $slot }}
                    </section>
                </div>

                <footer class="av-auth-footer">
                    <span>© {{ now()->year }} Allo Vaho</span>
                    @if ($privacyUrl || $supportEmail || $version)
                        <nav aria-label="Liens utiles">
                            @if ($privacyUrl)
                                <a href="{{ $privacyUrl }}">Confidentialité</a>
                            @endif
                            @if ($supportEmail)
                                <a href="mailto:{{ $supportEmail }}">Aide</a>
                            @endif
                            @if ($version)
                                <span class="av-auth-footer__version">{{ $version }}</span>
                            @endif
                        </nav>
                    @endif
                </footer>
            </main>
        </div>
    </body>
</html>
