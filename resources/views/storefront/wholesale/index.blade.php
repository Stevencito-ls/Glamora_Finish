@extends('layouts.storefront')

@section('title', 'Programa Mayorista B2B — Glamora Perú')
@section('meta_description', 'Programa Mayorista B2B Glamora Perú: accede a escalas Bronce, Plata y Oro con hasta 30% de descuento, crédito comercial y lotes trazables FEFO.')
@section('canonical_url', route('wholesale.index'))

@push('structured_data')
    @php
        $wholesaleSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Programa Mayorista B2B — Glamora Perú',
            'description' => 'Abastecimiento mayorista B2B de maquillaje editorial y dermocosmética botánica para salones, spas y boutiques en Perú.',
            'url' => route('wholesale.index'),
            'mainEntity' => [
                '@type' => 'Service',
                'name' => 'Distribución Mayorista B2B de Alta Cosmética',
                'serviceType' => 'Abastecimiento Cosmético B2B',
                'provider' => [
                    '@type' => 'Organization',
                    'name' => 'Glamora Perú',
                    'url' => route('home'),
                ],
                'areaServed' => [
                    '@type' => 'Country',
                    'name' => 'Perú',
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">@json($wholesaleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    {{-- Hero B2B --}}
    <section class="border-b border-glamora-blush/35 bg-glamora-charcoal text-glamora-cream">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-12 lg:px-8">
            <div class="lg:col-span-7">
                <span class="inline-flex items-center gap-2 rounded-full border border-glamora-gold/45 bg-glamora-gold/10 px-3.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-glamora-gold">
                    {{ isset($wholesaleBanner) && $wholesaleBanner?->eyebrow ? $wholesaleBanner->eyebrow : 'Alianzas Comerciales B2B Perú' }}
                </span>
                <h1 class="mt-4 font-serif text-4xl font-semibold leading-tight text-white sm:text-5xl">
                    {{ isset($wholesaleBanner) && $wholesaleBanner?->title ? $wholesaleBanner->title : 'Abastece tu salón, spa o boutique con márgenes mayoristas reales' }}
                </h1>
                <p class="mt-4 max-w-2xl text-sm leading-relaxed text-glamora-cream/80">
                    {{ isset($wholesaleBanner) && $wholesaleBanner?->subtitle ? $wholesaleBanner->subtitle : 'Accede a descuentos escalonados hasta del 30%, crédito comercial a 30 días, exhibidores llave en mano y lotes trazables FEFO con registro sanitario DIGEMID.' }}
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="#solicitud-b2b" class="btn-glamora-terracotta text-xs uppercase tracking-wider">
                        {{ isset($wholesaleBanner) && $wholesaleBanner?->button_text ? $wholesaleBanner->button_text : 'Enviar Solicitud RUC' }}
                    </a>
                    <a href="#calculadora-b2b" class="rounded-full border border-white/25 px-6 py-3 text-xs font-semibold text-white transition hover:border-glamora-gold hover:text-glamora-gold">
                        Simular Ganancia B2B
                    </a>
                </div>
            </div>

            <div class="space-y-4 lg:col-span-5">
                @if (isset($wholesaleBanner) && $wholesaleBanner)
                    <div class="overflow-hidden rounded-2xl border border-glamora-gold/40 bg-white/5 p-2 shadow-xl">
                        <img
                            src="{{ $wholesaleBanner->image_url }}"
                            alt="{{ $wholesaleBanner->title }}"
                            class="h-44 w-full rounded-xl object-cover"
                        >
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="font-serif text-3xl font-bold text-glamora-gold">Hasta 30%</p>
                        <p class="mt-1 text-xs text-glamora-cream/75">Descuento directo por escala Oro y volumen</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="font-serif text-3xl font-bold text-glamora-gold">30 días</p>
                        <p class="mt-1 text-xs text-glamora-cream/75">Crédito comercial aprobado para cuentas Oro</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="font-serif text-3xl font-bold text-glamora-gold">100% FEFO</p>
                        <p class="mt-1 text-xs text-glamora-cream/75">Rotación garantizada sin merma por vencimiento</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="font-serif text-3xl font-bold text-glamora-gold">NSOC</p>
                        <p class="mt-1 text-xs text-glamora-cream/75">Certificación DIGEMID para venta formal con factura</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Niveles Bronce, Plata y Oro --}}
    <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8">
        <div class="text-center">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Escalas Mayoristas</span>
            <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                Niveles Bronce, Plata y Oro
            </h2>
            <p class="mx-auto mt-2 max-w-2xl text-sm text-glamora-muted">
                Elige la escala que mejor se adapta al volumen mensual de tu negocio. Todos los pedidos incluyen factura electrónica con IGV desagregado.
            </p>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-3">
            @foreach ($tiers as $item)
                @php
                    /** @var \App\Enums\CustomerTier $tierEnum */
                    $tierEnum = $item['tier'];
                    $isOro = $tierEnum === \App\Enums\CustomerTier::Oro;
                @endphp
                <div @class([
                    'flex flex-col rounded-3xl p-7 transition',
                    'border-2 border-glamora-gold bg-white shadow-xl' => $isOro,
                    'card-glamora' => ! $isOro,
                ])>
                    <div class="flex items-center justify-between gap-2">
                        <span class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wider {{ $tierEnum->badgeClasses() }}">
                            {{ $tierEnum->label() }}
                        </span>
                        @if ($isOro)
                            <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-terracotta">
                                Más Popular B2B
                            </span>
                        @endif
                    </div>

                    <p class="mt-4 text-xs font-medium text-glamora-muted">{{ $item['badge'] }}</p>

                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="font-serif text-4xl font-bold text-glamora-charcoal">{{ $item['discount_range'] }}</span>
                    </div>

                    <div class="mt-4 rounded-2xl bg-glamora-cream p-3.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-glamora-muted">Pedido mínimo mensual (MOQ):</span>
                            <strong class="text-glamora-charcoal">S/ {{ number_format($item['moq'], 2) }}</strong>
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-glamora-muted">Plazo de crédito comercial:</span>
                            <strong class="text-glamora-charcoal">
                                {{ $item['credit_days'] > 0 ? $item['credit_days'] . ' días (sujeto a evaluación)' : 'Pago contra pedido' }}
                            </strong>
                        </div>
                    </div>

                    <ul class="mt-5 flex-1 space-y-2.5 text-sm text-glamora-charcoal/85">
                        @foreach ($item['perks'] as $perk)
                            <li class="flex items-start gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span>{{ $perk }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a
                        href="#solicitud-b2b"
                        @class([
                            'mt-7 w-full justify-center text-xs uppercase tracking-wider',
                            'btn-glamora-primary' => $isOro,
                            'btn-glamora-outline' => ! $isOro,
                        ])
                    >
                        Postular a {{ $tierEnum->label() }}
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Calculadora Interactiva de Ganancia (Alpine.js) --}}
    <section id="calculadora-b2b" class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div
            x-data="{
                tier: 'plata',
                monthlyUnits: 60,
                avgRetailPrice: 68,
                resaleAdjustment: 0,
                tiers: {
                    bronce: { label: 'Mayorista Bronce', discount: 12, moq: 800 },
                    plata: { label: 'Mayorista Plata', discount: 20, moq: 2500 },
                    oro: { label: 'Mayorista Oro', discount: 28, moq: 6000 }
                },
                get discountPct() {
                    return this.tiers[this.tier]?.discount ?? 20;
                },
                get moqRequired() {
                    return this.tiers[this.tier]?.moq ?? 800;
                },
                get retailTotal() {
                    return Math.max(0, Number(this.monthlyUnits) * Number(this.avgRetailPrice));
                },
                get resaleUnitPrice() {
                    return Math.max(1, Number(this.avgRetailPrice) * (1 + (Number(this.resaleAdjustment) / 100)));
                },
                get resaleTotal() {
                    return Math.max(0, Number(this.monthlyUnits) * this.resaleUnitPrice);
                },
                get unitWholesaleCost() {
                    return Number(this.avgRetailPrice) * (1 - (this.discountPct / 100));
                },
                get wholesaleInvestment() {
                    return Math.max(0, Number(this.monthlyUnits) * this.unitWholesaleCost);
                },
                get wholesaleSavings() {
                    return Math.max(0, this.retailTotal - this.wholesaleInvestment);
                },
                get netProfit() {
                    return Math.max(0, this.resaleTotal - this.wholesaleInvestment);
                },
                get roiPct() {
                    if (this.wholesaleInvestment <= 0) return 0;
                    return (this.netProfit / this.wholesaleInvestment) * 100;
                },
                get meetsMoq() {
                    return this.wholesaleInvestment >= this.moqRequired;
                }
            }"
            class="card-glamora overflow-hidden"
        >
            <div class="grid lg:grid-cols-12">
                {{-- Controls --}}
                <div class="p-7 sm:p-10 lg:col-span-7">
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                        Simulador Comercial B2B
                    </span>
                    <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal">
                        Calculadora interactiva de ganancia
                    </h2>
                    <p class="mt-2 text-sm text-glamora-muted">
                        Proyecta tu costo unitario mayorista, ahorro frente a PVP y ganancia neta mensual según tu nivel B2B.
                    </p>

                    <div class="mt-7 space-y-6">
                        {{-- Selector de Nivel --}}
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                                1. Elige tu nivel B2B proyectado
                            </label>
                            <div class="grid grid-cols-3 gap-2.5">
                                <button
                                    type="button"
                                    @click="tier = 'bronce'"
                                    :class="tier === 'bronce' ? 'bg-glamora-charcoal text-glamora-cream border-glamora-charcoal' : 'bg-glamora-cream/70 text-glamora-charcoal border-glamora-blush/40 hover:bg-white'"
                                    class="rounded-2xl border px-3 py-3 text-center text-xs font-semibold transition"
                                >
                                    <span class="block">Bronce</span>
                                    <span class="mt-0.5 block text-[10px] opacity-80">~12% desc.</span>
                                </button>
                                <button
                                    type="button"
                                    @click="tier = 'plata'"
                                    :class="tier === 'plata' ? 'bg-glamora-charcoal text-glamora-cream border-glamora-charcoal' : 'bg-glamora-cream/70 text-glamora-charcoal border-glamora-blush/40 hover:bg-white'"
                                    class="rounded-2xl border px-3 py-3 text-center text-xs font-semibold transition"
                                >
                                    <span class="block">Plata</span>
                                    <span class="mt-0.5 block text-[10px] opacity-80">~20% desc.</span>
                                </button>
                                <button
                                    type="button"
                                    @click="tier = 'oro'"
                                    :class="tier === 'oro' ? 'bg-glamora-charcoal text-glamora-cream border-glamora-charcoal' : 'bg-glamora-cream/70 text-glamora-charcoal border-glamora-blush/40 hover:bg-white'"
                                    class="rounded-2xl border px-3 py-3 text-center text-xs font-semibold transition"
                                >
                                    <span class="block">Oro</span>
                                    <span class="mt-0.5 block text-[10px] opacity-80">~28% desc.</span>
                                </button>
                            </div>
                        </div>

                        {{-- Volumen Mensual --}}
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <div class="mb-2 flex items-center justify-between text-xs">
                                    <label for="calc-units" class="font-bold uppercase tracking-wider text-glamora-charcoal">
                                        2. Unidades al mes
                                    </label>
                                    <span class="font-bold text-glamora-terracotta" x-text="monthlyUnits + ' uds.'"></span>
                                </div>
                                <input
                                    id="calc-units"
                                    type="range"
                                    min="10"
                                    max="300"
                                    step="5"
                                    x-model.number="monthlyUnits"
                                    class="w-full accent-glamora-terracotta"
                                >
                                <input
                                    type="number"
                                    min="1"
                                    max="5000"
                                    x-model.number="monthlyUnits"
                                    aria-label="Cantidad mensual de unidades"
                                    class="input-glamora mt-2"
                                >
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between text-xs">
                                    <label for="calc-pvp" class="font-bold uppercase tracking-wider text-glamora-charcoal">
                                        3. Precio Lista Prom. (PVP)
                                    </label>
                                    <span class="font-bold text-glamora-terracotta" x-text="'S/ ' + Number(avgRetailPrice).toFixed(2)"></span>
                                </div>
                                <input
                                    id="calc-pvp"
                                    type="range"
                                    min="35"
                                    max="200"
                                    step="1"
                                    x-model.number="avgRetailPrice"
                                    class="w-full accent-glamora-terracotta"
                                >
                                <input
                                    type="number"
                                    min="10"
                                    max="2000"
                                    step="1"
                                    x-model.number="avgRetailPrice"
                                    aria-label="Precio lista promedio PVP"
                                    class="input-glamora mt-2"
                                >
                            </div>
                        </div>

                        {{-- Margen adicional de reventa --}}
                        <div>
                            <div class="mb-2 flex items-center justify-between text-xs">
                                <label for="calc-markup" class="font-bold uppercase tracking-wider text-glamora-charcoal">
                                    4. Ajuste sobre PVP en tu punto de venta (%)
                                </label>
                                <span class="font-semibold text-glamora-charcoal" x-text="(resaleAdjustment >= 0 ? '+' : '') + resaleAdjustment + '% sobre PVP'"></span>
                            </div>
                            <input
                                id="calc-markup"
                                type="range"
                                min="0"
                                max="25"
                                step="1"
                                x-model.number="resaleAdjustment"
                                class="w-full accent-glamora-gold"
                            >
                            <p class="mt-1 text-[11px] text-glamora-muted">
                                En 0% vendes exactamente al precio sugerido al público (PVP). Puedes aumentarlo si ofreces servicio en cabina o packs regalo.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Results Panel --}}
                <div class="flex flex-col justify-between bg-glamora-charcoal p-7 text-glamora-cream sm:p-10 lg:col-span-5">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-glamora-gold">
                            Proyección Mensual · <span x-text="tiers[tier].label"></span>
                        </p>

                        <div class="mt-6 space-y-4">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <span class="block text-xs text-glamora-cream/70">Ganancia Neta Proyectada</span>
                                <span class="mt-1 block font-serif text-4xl font-bold text-glamora-gold" x-text="'S/ ' + netProfit.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                <span class="mt-1 inline-block rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-300" x-text="'ROI sobre inversión: ' + roiPct.toFixed(1) + '%'"></span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                                    <span class="block text-glamora-cream/65">Tu inversión mayorista</span>
                                    <strong class="mt-1 block text-base text-white" x-text="'S/ ' + wholesaleInvestment.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                                    <span class="block text-glamora-cream/65">Ahorro mayorista</span>
                                    <strong class="mt-1 block text-base text-glamora-blush" x-text="'S/ ' + wholesaleSavings.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                                    <span class="block text-glamora-cream/65">Costo unitario prom.</span>
                                    <strong class="mt-1 block text-base text-white" x-text="'S/ ' + unitWholesaleCost.toFixed(2)"></strong>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                                    <span class="block text-glamora-cream/65">Facturación venta PVP</span>
                                    <strong class="mt-1 block text-base text-white" x-text="'S/ ' + resaleTotal.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border p-4 text-xs" :class="meetsMoq ? 'border-emerald-400/35 bg-emerald-950/50 text-emerald-100' : 'border-amber-400/35 bg-amber-950/40 text-amber-100'">
                        <template x-if="meetsMoq">
                            <p>
                                <strong>Califica para MOQ <span x-text="tiers[tier].label"></span>:</strong> Tu inversión proyectada supera el mínimo de S/ <span x-text="moqRequired.toLocaleString('es-PE')"></span> mensuales.
                            </p>
                        </template>
                        <template x-if="!meetsMoq">
                            <p>
                                <strong>Nota MOQ:</strong> Para mantener la escala <span x-text="tiers[tier].label"></span> se sugiere una reposición mensual desde S/ <span x-text="moqRequired.toLocaleString('es-PE')"></span>.
                            </p>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Formulario de Solicitud RUC --}}
    <section id="solicitud-b2b" class="mx-auto max-w-5xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="card-glamora p-7 sm:p-10">
            <div class="mb-8 border-b border-glamora-blush/30 pb-6">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                    Registro Comercial SUNAT
                </span>
                <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                    Solicitud de Alta Mayorista (RUC)
                </h2>
                <p class="mt-2 text-sm text-glamora-muted">
                    Completa los datos de tu empresa. Validaremos tu RUC de 11 dígitos y activaremos tu perfil B2B para acceder a precios mayoristas y matriz por tonos.
                </p>
            </div>

            @if (session('wholesale_success'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <div>
                            <p class="font-bold">¡Solicitud registrada en estado Pendiente de Aprobación!</p>
                            <p class="mt-1">{{ session('wholesale_success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-900">
                    <p class="font-bold">Por favor revisa los campos observados:</p>
                    <ul class="mt-1.5 list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('wholesale.apply') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="ruc" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            RUC (11 dígitos) *
                        </label>
                        <input
                            id="ruc"
                            type="text"
                            name="ruc"
                            maxlength="11"
                            pattern="\d{11}"
                            required
                            value="{{ old('ruc') }}"
                            placeholder="20601234567"
                            class="input-glamora font-mono"
                        >
                        @error('ruc')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="requested_tier" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Nivel Mayorista Solicitado *
                        </label>
                        <select id="requested_tier" name="requested_tier" required class="input-glamora">
                            <option value="bronce" @selected(old('requested_tier', 'bronce') === 'bronce')>Mayorista Bronce (10%-14% desc. · MOQ S/ 800)</option>
                            <option value="plata" @selected(old('requested_tier') === 'plata')>Mayorista Plata (18%-22% desc. · MOQ S/ 2,500)</option>
                            <option value="oro" @selected(old('requested_tier') === 'oro')>Mayorista Oro (25%-30% desc. · MOQ S/ 6,000 + Crédito)</option>
                        </select>
                        @error('requested_tier')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Razón Social *
                        </label>
                        <input
                            id="company_name"
                            type="text"
                            name="company_name"
                            required
                            value="{{ old('company_name') }}"
                            placeholder="Ej. Estética & Spa Belleza Andina S.A.C."
                            class="input-glamora"
                        >
                        @error('company_name')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="trade_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Nombre Comercial (Opcional)
                        </label>
                        <input
                            id="trade_name"
                            type="text"
                            name="trade_name"
                            value="{{ old('trade_name') }}"
                            placeholder="Ej. Atelier Belleza Miraflores"
                            class="input-glamora"
                        >
                        @error('trade_name')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="business_type" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Giro de Negocio *
                        </label>
                        <select id="business_type" name="business_type" required class="input-glamora">
                            <option value="">Selecciona una opción</option>
                            @foreach (['Salón de belleza y spa', 'Centro estético', 'Boutique cosmética', 'Botica / Farmacia dermocosmética', 'Distribuidor regional', 'Maquillador(a) profesional'] as $bizType)
                                <option value="{{ $bizType }}" @selected(old('business_type') === $bizType)>{{ $bizType }}</option>
                            @endforeach
                        </select>
                        @error('business_type')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="contact_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Nombre del Contacto / Titular *
                        </label>
                        <input
                            id="contact_name"
                            type="text"
                            name="contact_name"
                            required
                            value="{{ old('contact_name') }}"
                            placeholder="Nombre completo"
                            class="input-glamora"
                        >
                        @error('contact_name')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="contact_email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Correo Electrónico de Contacto *
                        </label>
                        <input
                            id="contact_email"
                            type="email"
                            name="contact_email"
                            required
                            value="{{ old('contact_email') }}"
                            placeholder="compras@tuempresa.pe"
                            class="input-glamora"
                        >
                        @error('contact_email')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="contact_phone" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Teléfono / WhatsApp *
                        </label>
                        <input
                            id="contact_phone"
                            type="text"
                            name="contact_phone"
                            required
                            value="{{ old('contact_phone') }}"
                            placeholder="+51 999 000 111"
                            class="input-glamora"
                        >
                        @error('contact_phone')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-12">
                    <div class="sm:col-span-6">
                        <label for="fiscal_address" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Dirección Fiscal / Entrega *
                        </label>
                        <input
                            id="fiscal_address"
                            type="text"
                            name="fiscal_address"
                            required
                            value="{{ old('fiscal_address') }}"
                            placeholder="Av. / Calle / Jr. y número"
                            class="input-glamora"
                        >
                        @error('fiscal_address')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label for="district" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Distrito *
                        </label>
                        <input
                            id="district"
                            type="text"
                            name="district"
                            required
                            value="{{ old('district') }}"
                            placeholder="Miraflores"
                            class="input-glamora"
                        >
                        @error('district')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label for="city" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Ciudad *
                        </label>
                        <input
                            id="city"
                            type="text"
                            name="city"
                            required
                            value="{{ old('city', 'Lima') }}"
                            placeholder="Lima"
                            class="input-glamora"
                        >
                        @error('city')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                        Comentarios / Líneas de interés (Opcional)
                    </label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        placeholder="Cuéntanos qué líneas te interesa reponer (ej. exhibidores labiales, packs de cabina Kora Derma, solares...)"
                        class="input-glamora"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/25 pt-5">
                    <p class="text-xs text-glamora-muted">
                        Se creará un registro auditado en plataforma para evaluación comercial B2B.
                    </p>
                    <button type="submit" class="btn-glamora-primary px-8 py-3.5 text-xs uppercase tracking-wider">
                        Enviar Solicitud Mayorista
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
