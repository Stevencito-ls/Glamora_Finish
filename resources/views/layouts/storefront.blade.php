@php
    $settingService = app(\App\Services\SettingService::class);
    $availableThemes = $settingService->themes();
    $rawThemeKey = (string) ($activeThemeKey ?? $settingService->activeThemeKey());
    $resolvedThemeKey = array_key_exists($rawThemeKey, $availableThemes) ? $rawThemeKey : 'glamora_nude';
    $resolvedTheme = $availableThemes[$resolvedThemeKey];
    $sanitizeHex = static fn (mixed $hex, string $fallback): string => is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($hex)) === 1
        ? trim($hex)
        : $fallback;
    $storeDisplayName = (string) ($storeSettings['store_name'] ?? 'Glamora Perú');
    $resolvedFavicon = $storeFaviconUrl ?? $settingService->faviconUrl();
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="{{ $resolvedThemeKey }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (! empty($resolvedFavicon))
        <link rel="icon" href="{{ $resolvedFavicon }}">
        <link rel="shortcut icon" href="{{ $resolvedFavicon }}">
    @endif
    @php
        $defaultSeoTitle = "{$storeDisplayName} — Alta Cosmética Botánica & Distribución B2B";
        $defaultSeoDescription = 'Tienda boutique de maquillaje, dermocosmética botánica y abastecimiento mayorista B2B en Perú con trazabilidad FEFO y registro sanitario DIGEMID.';
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
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoCanonical }}">

    <meta property="og:site_name" content="{{ $storeDisplayName }}">
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
            --color-glamora-cream: {{ $sanitizeHex($resolvedTheme['cream'] ?? null, '#FAF6F2') }};
            --color-glamora-card: {{ $sanitizeHex($resolvedTheme['card'] ?? null, '#FFFFFF') }};
            --color-glamora-terracotta: {{ $sanitizeHex($resolvedTheme['terracotta'] ?? null, '#C87D72') }};
            --color-glamora-blush: {{ $sanitizeHex($resolvedTheme['blush'] ?? null, '#E8B4A2') }};
            --color-glamora-gold: {{ $sanitizeHex($resolvedTheme['gold'] ?? null, '#D4AF37') }};
            --color-glamora-charcoal: {{ $sanitizeHex($resolvedTheme['charcoal'] ?? null, '#2B2523') }};
            --color-glamora-muted: {{ $sanitizeHex($resolvedTheme['muted'] ?? null, '#6E635F') }};
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
        $showFloatingWa = (bool) ($whatsappFloatingEnabled ?? true);
        $rawWaMessage = trim((string) $__env->yieldContent('whatsapp_message'));
        $decodedWaMessage = $rawWaMessage !== ''
            ? html_entity_decode($rawWaMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : "Hola, necesito asesoría en {$storeDisplayName}.";
        $floatingWaUrl = $settingService->buildWhatsappUrl($decodedWaMessage);
    @endphp
    @if ($showFloatingWa)
        <a
            href="{{ $floatingWaUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Asesoría por WhatsApp"
            class="fixed bottom-20 md:bottom-5 right-5 z-40 inline-flex items-center gap-2.5 rounded-full bg-[#25D366] px-4 py-3 text-xs font-bold text-white shadow-xl ring-2 ring-white/80 transition hover:-translate-y-0.5 hover:bg-[#1EB958] focus:outline-none focus:ring-2 focus:ring-[#25D366]/60"
        >
            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.14 1.6 5.95L0 24l6.33-1.66a11.86 11.86 0 0 0 5.73 1.46h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.17-3.45-8.42Zm-8.46 18.3h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.76.98 1-3.66-.24-.38a9.87 9.87 0 0 1-1.51-5.24c0-5.45 4.44-9.89 9.9-9.89 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.9 7c0 5.45-4.44 9.88-9.88 9.88Zm5.42-7.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.22 3.08.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z"/>
            </svg>
            <span class="hidden sm:inline">Asesoría WhatsApp</span>
        </a>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            const cartIndexUrl = @json(route('cart.index'));

            window.glamoraToast = function (payload) {
                if (typeof window.Swal === 'undefined') {
                    return;
                }

                const data = payload || {};
                const Toast = window.Swal.mixin({
                    toast: true,
                    position: 'bottom-start',
                    showConfirmButton: data.showConfirmButton !== false,
                    confirmButtonText: data.confirmButtonText || 'Ir a la Bolsa',
                    confirmButtonColor: '#C87D72',
                    background: '#2B2523',
                    color: '#FAF6F2',
                    iconColor: '#D4AF37',
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
