<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $defaultSeoTitle = 'Glamora Perú — Alta Cosmética Botánica & Distribución B2B';
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

    <meta property="og:site_name" content="Glamora Perú">
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
                    position: 'top-end',
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
