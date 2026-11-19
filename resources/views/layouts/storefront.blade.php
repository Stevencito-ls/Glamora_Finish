<!DOCTYPE html>
<html lang="es" data-theme="{{ $activeTheme['key'] ?? 'glamora_nude' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $brandName = $storeSettings?->store_name ?: 'Glamora Perú';
        $defaultSeoTitle = "{$brandName} — Alta Cosmética Botánica & Distribución B2B";
        $defaultSeoDescription = $storeSettings?->footer_about ?: 'Tienda boutique de maquillaje, dermocosmética botánica y abastecimiento mayorista B2B en Perú con trazabilidad FEFO y registro sanitario DIGEMID.';
        $defaultSeoImage = \App\Support\CosmeticImageResolver::resolve(null, 'product', 'labial-liquido-velvet-matte');

        $resolveSeoSection = static function (string $section, string $fallback) use ($__env): string {
            $raw = trim((string) $__env->yieldContent($section));
            if ($raw === '') {
                return $fallback;
            }

            return html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        $seoTitle = $resolveSeoSection('title', $defaultSeoTitle);
        $seoDescription = $resolveSeoSection('meta_description', $defaultSeoDescription);
        $seoRobots = $resolveSeoSection('meta_robots', 'index, follow');
        $seoCanonical = $resolveSeoSection('canonical_url', url()->current());
        $seoOgType = $resolveSeoSection('og_type', 'website');
        $seoOgImage = $resolveSeoSection('og_image', $defaultSeoImage);
        $seoTwitterCard = $resolveSeoSection('twitter_card', 'summary_large_image');

        $whitelistedTheme = \App\Services\SettingService::themeFor($activeTheme['key'] ?? $storeSettings?->active_theme);
        $themeKey = $whitelistedTheme['key'];
        $themePalette = $whitelistedTheme['palette'];
        $heroGradient = match ($themeKey) {
            'noir_gold' => ['#F7F4EF', '#EFE7D8', '#E5D7BC'],
            'botanical_skincare' => ['#F4F7F4', '#EAF1EC', '#DCE8E0'],
            'corporate_pyme' => ['#F4F6F9', '#EBF0F6', '#DFE7F0'],
            default => ['#FAF6F2', '#F7EDE5', '#EFE0D5'],
        };
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoCanonical }}">

    @if ($storeSettings?->favicon_url)
        <link rel="icon" href="{{ $storeSettings->favicon_url }}">
    @endif

    <meta property="og:site_name" content="{{ $brandName }}">
    <meta property="og:locale" content="es_PE">
    <meta property="og:type" content="{{ $seoOgType }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoOgImage }}">

    <meta name="twitter:card" content="{{ $seoTwitterCard }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoOgImage }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style id="glamora-dynamic-theme">
        :root {
            --color-glamora-cream: {{ $themePalette['cream'] ?? '#FAF6F2' }};
            --color-glamora-card: {{ $themePalette['surface'] ?? '#FFFFFF' }};
            --color-glamora-terracotta: {{ $themePalette['terracotta'] ?? '#C87D72' }};
            --color-glamora-blush: {{ $themePalette['blush'] ?? '#E8C5B9' }};
            --color-glamora-gold: {{ $themePalette['gold'] ?? '#D4AF37' }};
            --color-glamora-charcoal: {{ $themePalette['charcoal'] ?? '#2B2523' }};
            --color-glamora-muted: {{ $themePalette['muted'] ?? '#6E635F' }};
            --glamora-hero-from: {{ $heroGradient[0] }};
            --glamora-hero-via: {{ $heroGradient[1] }};
            --glamora-hero-to: {{ $heroGradient[2] }};
        }
    </style>
    @livewireStyles
    @stack('styles')
    @stack('structured_data')
</head>
<body class="min-h-screen flex flex-col bg-glamora-cream text-glamora-charcoal antialiased">
    @include('storefront.partials.topbar')
    @include('storefront.partials.navbar')

    @if (session('cart_success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            class="border-b border-emerald-200 bg-emerald-50"
        >
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm text-emerald-900 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2.5">
                    <svg class="h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span class="font-medium">{{ session('cart_success') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="$dispatch('open-cart-drawer')"
                        class="rounded-full bg-glamora-charcoal px-3.5 py-1 text-xs font-semibold text-glamora-cream transition hover:bg-glamora-terracotta"
                    >
                        Ver Bolsa
                    </button>
                    <button type="button" @click="show = false" aria-label="Cerrar aviso" class="text-emerald-700 hover:text-emerald-950">
                        &times;
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if (session('cart_error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            class="border-b border-rose-200 bg-rose-50"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 text-sm text-rose-900 sm:px-6 lg:px-8">
                <span class="font-medium">{{ session('cart_error') }}</span>
                <button type="button" @click="show = false" aria-label="Cerrar error" class="text-rose-700">&times;</button>
            </div>
        </div>
    @endif

    <main class="flex-1">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @include('storefront.partials.footer')

    @php
        $waDigits = $storeSettings?->cleanWhatsappDigits() ?? '51987654321';
        $waDefaultMessage = "Hola {$brandName}, deseo asesoría sobre sus productos y disponibilidad.";
        $waYieldedMessage = trim((string) $__env->yieldContent('whatsapp_message'));
        $waFinalMessage = html_entity_decode($waYieldedMessage !== '' ? $waYieldedMessage : $waDefaultMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $waFloatingHref = "https://wa.me/{$waDigits}?text=" . rawurlencode($waFinalMessage);
    @endphp
    <a
        href="{{ $waFloatingHref }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Asesoría por WhatsApp"
        class="fixed right-5 bottom-20 z-40 inline-flex items-center gap-2.5 rounded-full border border-emerald-400/40 bg-[#25D366] px-4 py-3 text-xs font-bold text-white shadow-xl transition hover:scale-105 hover:bg-[#1ebe5d] md:bottom-5"
    >
        <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.47 0 .1 5.37.1 11.96c0 2.11.55 4.17 1.6 5.98L0 24l6.23-1.63a11.93 11.93 0 0 0 5.83 1.49h.01c6.59 0 11.96-5.37 11.96-11.96 0-3.19-1.24-6.19-3.51-8.42Zm-8.46 18.36h-.01a9.9 9.9 0 0 1-5.05-1.38l-.36-.22-3.7.97.99-3.61-.24-.37a9.92 9.92 0 0 1-1.52-5.27c0-5.48 4.46-9.94 9.95-9.94 2.65 0 5.15 1.03 7.02 2.91a9.88 9.88 0 0 1 2.91 7.03c0 5.49-4.46 9.95-9.94 9.95Zm5.45-7.45c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.49s1.07 2.89 1.22 3.09c.15.2 2.11 3.22 5.11 4.52.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z"/>
        </svg>
        <span class="hidden sm:inline">WhatsApp Asesoría</span>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            const cartIndexUrl = @json(route('cart.index'));
            const activePalette = @json($themePalette);

            window.glamoraToast = function (payload) {
                if (typeof window.Swal === 'undefined') {
                    return;
                }

                const data = payload || {};
                const Toast = window.Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: data.showConfirmButton !== false,
                    confirmButtonText: data.confirmButtonText || 'Ir a la Bolsa',
                    confirmButtonColor: activePalette.terracotta || '#C87D72',
                    background: activePalette.charcoal || '#2B2523',
                    color: activePalette.cream || '#FAF6F2',
                    iconColor: activePalette.gold || '#D4AF37',
                    timer: data.timer || 3800,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', window.Swal.stopTimer);
                        toast.addEventListener('mouseleave', window.Swal.resumeTimer);
                    }
                });

                Toast.fire({
                    icon: data.icon || 'success',
                    title: data.title || 'Tu Bolsa fue actualizada.',
                    text: data.text || ''
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = data.redirectUrl || cartIndexUrl;
                    }
                });
            };

            window.addEventListener('swal:toast', function (event) {
                const detail = Array.isArray(event.detail) ? (event.detail[0] || {}) : (event.detail || {});
                window.glamoraToast(detail);
            });

            document.addEventListener('DOMContentLoaded', function () {
                @if (session('cart_success'))
                    window.glamoraToast({
                        icon: 'success',
                        title: @json(session('cart_success'))
                    });
                @endif

                @if (session('cart_error'))
                    window.glamoraToast({
                        icon: 'warning',
                        title: @json(session('cart_error'))
                    });
                @endif
            });
        })();
    </script>

    @livewireScripts(['url' => asset(config('app.debug') ? 'livewire/livewire.js' : 'livewire/livewire.min.js')])
    @stack('scripts')
</body>
</html>
