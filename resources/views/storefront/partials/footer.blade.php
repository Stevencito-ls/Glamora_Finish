@php
    $footerSettings = is_array($storeSettings ?? null) ? $storeSettings : [];
    $footerSettingService = app(\App\Services\SettingService::class);
    $footerStoreName = trim((string) ($footerSettings['store_name'] ?? '')) ?: 'Glamora Perú';
    $footerRuc = trim((string) ($footerSettings['store_ruc'] ?? '')) ?: '20608945123';
    $footerEmail = trim((string) ($footerSettings['store_email'] ?? '')) ?: 'info.glamora@gmail.com';
    $footerPhone = trim((string) ($footerSettings['store_phone'] ?? '')) ?: '+51 917 328 085';
    $footerAddress = trim((string) ($footerSettings['store_address'] ?? '')) ?: 'Almacén Central: Lima Metropolitana, Perú (Envíos a todo el país vía Olva y Shalom)';
    $footerWhatsappUrl = $whatsappUrl ?? $footerSettingService->buildWhatsappUrl("Hola, necesito asesoría en {$footerStoreName}.");

    $sanitizeSocialUrl = static function (mixed $rawUrl): ?string {
        if (! is_string($rawUrl)) {
            return null;
        }
        $clean = trim($rawUrl);
        if ($clean === '' || ! filter_var($clean, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($clean, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $clean : null;
    };

    $socialLinks = array_filter([
        'Instagram' => $sanitizeSocialUrl($footerSettings['social_instagram'] ?? 'https://instagram.com/glamora.pe'),
        'TikTok' => $sanitizeSocialUrl($footerSettings['social_tiktok'] ?? 'https://tiktok.com/@glamoracosmeticos'),
        'Facebook' => $sanitizeSocialUrl($footerSettings['social_facebook'] ?? 'https://facebook.com/glamora.pe'),
    ]);
@endphp

<footer id="newsletter-footer" class="mt-20 border-t border-glamora-blush/35 bg-glamora-charcoal text-glamora-cream">
    {{-- Newsletter Band --}}
    <div class="border-b border-white/10 bg-[#231D1B]">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-12 lg:items-center lg:px-8">
            <div class="lg:col-span-5">
                <span class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-glamora-gold">
                    <span class="h-1.5 w-1.5 rounded-full bg-glamora-gold"></span>
                    Círculo Editorial &amp; B2B
                </span>
                <h2 class="mt-2 font-serif text-3xl font-semibold text-white">
                    Recibe lanzamientos botánicos y listas mayoristas
                </h2>
                <p class="mt-2 text-sm text-glamora-cream/75">
                    Elige tu perfil para recibir guías dermatológicas, reposiciones de lotes nuevos o alertas de packs para salón.
                </p>
            </div>

            <div class="lg:col-span-7">
                @if (session('newsletter_status'))
                    <div class="mb-4 flex items-start gap-3 rounded-2xl border border-emerald-400/40 bg-emerald-950/60 px-4 py-3 text-sm text-emerald-100">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <div>
                            <p class="font-semibold">{{ session('newsletter_status') }}</p>
                            @if (session('newsletter_token_hint'))
                                <p class="mt-0.5 text-xs text-emerald-200/80">{{ session('newsletter_token_hint') }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <label for="newsletter_name" class="sr-only">Nombre o Salón</label>
                            <input
                                id="newsletter_name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="Tu nombre o salón"
                                class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-glamora-cream/55 focus:border-glamora-gold focus:outline-none focus:ring-2 focus:ring-glamora-gold/30"
                            >
                        </div>

                        <div class="sm:col-span-5">
                            <label for="newsletter_email" class="sr-only">Correo electrónico</label>
                            <input
                                id="newsletter_email"
                                type="email"
                                name="email"
                                required
                                value="{{ old('email') }}"
                                placeholder="correo@ejemplo.pe"
                                class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-glamora-cream/55 focus:border-glamora-gold focus:outline-none focus:ring-2 focus:ring-glamora-gold/30"
                            >
                        </div>

                        <div class="sm:col-span-3">
                            <label for="newsletter_segment" class="sr-only">Segmento</label>
                            <select
                                id="newsletter_segment"
                                name="segment"
                                class="w-full rounded-xl border border-white/15 bg-[#2B2523] px-3.5 py-3 text-sm text-white focus:border-glamora-gold focus:outline-none focus:ring-2 focus:ring-glamora-gold/30"
                            >
                                <option value="minorista" @selected(old('segment', 'minorista') === 'minorista')>Minorista</option>
                                <option value="mayorista" @selected(old('segment') === 'mayorista')>Mayorista B2B</option>
                            </select>
                        </div>
                    </div>

                    @if ($errors->newsletter->any())
                        <p class="text-xs font-medium text-glamora-blush">
                            {{ $errors->newsletter->first() }}
                        </p>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-[11px] text-glamora-cream/60">
                            Al suscribirte generamos tu token privado opt-in conforme a la Ley N.º 29733 de Protección de Datos.
                        </p>
                        <button type="submit" class="btn-glamora-terracotta px-6 py-2.5 text-xs uppercase tracking-wider">
                            Suscribirme ahora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Main Footer Columns --}}
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-12 lg:px-8">
        <div class="lg:col-span-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full border border-glamora-gold/60 font-serif text-xl font-semibold text-glamora-gold">
                    G
                </span>
                <div>
                    <span class="block font-serif text-2xl font-semibold tracking-[0.22em] text-white">GLAMORA</span>
                    <span class="block text-[10px] uppercase tracking-[0.24em] text-glamora-blush">Alta Cosmética Perú</span>
                </div>
            </div>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-glamora-cream/75">
                E-commerce omnicanal de maquillaje editorial, dermocosmética botánica amazónica y abastecimiento B2B para salones, spas y boutiques en todo el Perú.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <span class="rounded-full border border-glamora-gold/35 px-3 py-1 text-[11px] text-glamora-gold">
                    Cruelty-Free
                </span>
                <span class="rounded-full border border-white/15 px-3 py-1 text-[11px] text-glamora-cream/80">
                    Registro NSOC DIGEMID
                </span>
                <span class="rounded-full border border-white/15 px-3 py-1 text-[11px] text-glamora-cream/80">
                    Control FEFO
                </span>
            </div>

            @if ($socialLinks !== [] || $footerWhatsappUrl !== '')
                <div class="mt-5 flex flex-wrap items-center gap-2.5 text-xs">
                    @foreach ($socialLinks as $network => $socialHref)
                        <a
                            href="{{ $socialHref }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-full border border-white/15 px-3 py-1 text-glamora-cream/80 transition hover:border-glamora-gold hover:text-glamora-gold"
                        >
                            {{ $network }}
                        </a>
                    @endforeach
                    <a
                        href="{{ $footerWhatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-full border border-emerald-400/40 bg-emerald-500/10 px-3 py-1 font-medium text-emerald-300 transition hover:bg-emerald-500/20"
                    >
                        WhatsApp
                    </a>
                </div>
            @endif
        </div>

        <div class="grid gap-8 sm:grid-cols-3 lg:col-span-8">
            <div>
                <h3 class="font-serif text-lg font-semibold text-white">Tienda &amp; Categorías</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-glamora-cream/75">
                    <li><a href="{{ route('catalog.index') }}" class="transition hover:text-glamora-gold">Todo el Catálogo</a></li>
                    <li><a href="{{ route('catalog.category', 'maquillaje') }}" class="transition hover:text-glamora-gold">Maquillaje Editorial</a></li>
                    <li><a href="{{ route('catalog.category', 'skincare') }}" class="transition hover:text-glamora-gold">Skincare &amp; Activos</a></li>
                    <li><a href="{{ route('catalog.category', 'capilar-y-body-care') }}" class="transition hover:text-glamora-gold">Capilar &amp; Body Care</a></li>
                    <li><a href="{{ route('catalog.category', 'kits-y-profesional-b2b') }}" class="transition hover:text-glamora-gold">Kits &amp; Profesional B2B</a></li>
                </ul>
            </div>

            <div>
                <h3 class="font-serif text-lg font-semibold text-white">Alianzas &amp; Editorial</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-glamora-cream/75">
                    <li><a href="{{ route('brands.index') }}" class="transition hover:text-glamora-gold">Nuestras Marcas</a></li>
                    <li><a href="{{ route('wholesale.index') }}" class="transition hover:text-glamora-gold">Programa Mayorista B2B</a></li>
                    <li><a href="{{ route('wholesale.index') }}#calculadora-b2b" class="transition hover:text-glamora-gold">Calculadora de Margen</a></li>
                    <li><a href="{{ route('blog.index') }}" class="transition hover:text-glamora-gold">Journal Dermatológico</a></li>
                </ul>
            </div>

            <div>
                <h3 class="font-serif text-lg font-semibold text-white">Servicio &amp; Logística</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-glamora-cream/75">
                    <li><a href="{{ route('tracking.index') }}" class="transition hover:text-glamora-gold">Seguimiento de Pedido</a></li>
                    <li><a href="{{ route('tracking.index') }}#solicitud-rma" class="transition hover:text-glamora-gold">Devoluciones &amp; RMA</a></li>
                    <li>
                        <a href="{{ $footerWhatsappUrl }}" target="_blank" rel="noopener noreferrer" class="transition hover:text-glamora-gold">
                            WhatsApp Directo: {{ $footerPhone }}
                        </a>
                    </li>
                    <li class="pt-1 text-xs leading-relaxed text-glamora-cream/65">
                        {{ $footerAddress }}
                    </li>
                    <li class="text-xs text-glamora-cream/65">
                        <a href="mailto:{{ $footerEmail }}" class="transition hover:text-glamora-gold">{{ $footerEmail }}</a>
                        · Lun - Sáb: 8:30 AM a 7:30 PM
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Bottom Bar --}}
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-glamora-cream/60 sm:flex-row sm:px-6 lg:px-8">
            <p>&copy; {{ date('Y') }} Glamora Perú S.A.C. ({{ $footerStoreName }}) · RUC {{ $footerRuc }}. Todos los derechos reservados.</p>
            <p>Precios en Soles (S/) incluyen IGV 18% · Despachos FEFO trazables.</p>
        </div>
    </div>
</footer>
