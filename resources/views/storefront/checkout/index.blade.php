@extends('layouts.storefront')

@section('title', 'Finalizar Compra — Checkout Multi-Método Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @php
        $prefillName = old('customer_name', $defaultAddress?->recipient_name ?? $user?->name ?? '');
        $prefillEmail = old('customer_email', $user?->email ?? '');
        $prefillPhone = old('customer_phone', $defaultAddress?->phone ?? $user?->phone ?? '');
        $prefillDocType = old('document_type', $user?->document_type ?? 'DNI');
        $prefillDocNumber = old('document_number', $user?->document_number ?? '');
        $prefillAddress = old('shipping_address', $defaultAddress?->address_line ?? $user?->default_address ?? '');
        $prefillDistrict = old('shipping_district', $defaultAddress?->district ?? $user?->district ?? '');
        $prefillCity = old('shipping_city', $defaultAddress?->city ?? $user?->city ?? 'Lima');
        $initialShipping = old('shipping_method', $summary['shipping_method']->value);
        $initialPayment = old('payment_method', 'tarjeta');
    @endphp

    <section class="border-b border-glamora-blush/35 bg-gradient-to-r from-[#FAF6F2] via-white to-[#F7ECE4]">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="badge-glamora-gold">Checkout Seguro · Stock FEFO en Tiempo Real</span>
                    <h1 class="mt-3 font-serif text-4xl font-semibold text-glamora-charcoal">
                        Finalizar Pedido
                    </h1>
                </div>
                <a href="{{ route('cart.index') }}" class="btn-glamora-outline px-5 py-2 text-xs uppercase tracking-wider">
                    &larr; Volver a la Bolsa
                </a>
            </div>
        </div>
    </section>

    <div
        x-data="{
            shippingMethod: @js($initialShipping),
            paymentMethod: @js($initialPayment),
            quotes: @js($shippingQuotes),
            netSubtotal: {{ (float) $summary['net_subtotal'] }},
            availableB2bCredit: {{ (float) $availableB2bCredit }},
            hasApprovedB2b: {{ $wholesaleProfile !== null ? 'true' : 'false' }},
            customerName: @js($prefillName),
            customerPhone: @js($prefillPhone),
            shippingAddress: @js($prefillAddress),
            shippingDistrict: @js($prefillDistrict),
            shippingCity: @js($prefillCity),
            cardLast4: @js(old('card_last4', '')),
            get currentQuote() {
                return this.quotes[this.shippingMethod] ?? Object.values(this.quotes)[0];
            },
            get shippingCost() {
                return this.currentQuote ? Number(this.currentQuote.cost.amount) : 0;
            },
            get grandTotal() {
                return Number((this.netSubtotal + this.shippingCost).toFixed(2));
            },
            get b2bEnough() {
                return this.hasApprovedB2b && this.availableB2bCredit >= this.grandTotal;
            },
            fillAddress(addr) {
                this.customerName = addr.recipient_name;
                this.customerPhone = addr.phone;
                this.shippingAddress = addr.address_line;
                this.shippingDistrict = addr.district;
                this.shippingCity = addr.city;
            },
            syncCardDigits(raw) {
                const digits = String(raw || '').replace(/\D+/g, '');
                if (digits.length >= 4) {
                    this.cardLast4 = digits.slice(-4);
                }
            }
        }"
        class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8"
    >
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                <p class="font-bold">Revisa los campos marcados antes de confirmar tu pedido:</p>
                <ul class="mt-2 list-disc pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('checkout.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid gap-8 lg:grid-cols-12">
                {{-- Left Column: 3-Step Checkout --}}
                <div class="space-y-8 lg:col-span-8">
                    {{-- PASO 1: Datos de Cliente & Facturación --}}
                    <section class="card-glamora p-6 sm:p-8">
                        <div class="flex items-center gap-3 border-b border-glamora-blush/30 pb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-glamora-charcoal text-xs font-bold text-glamora-gold">
                                1
                            </span>
                            <div>
                                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                    Datos de Cliente, Facturación &amp; Entrega
                                </h2>
                                <p class="text-xs text-glamora-muted">
                                    Completa tu DNI/RUC/CE y la dirección exacta de entrega.
                                </p>
                            </div>
                        </div>

                        @if ($addresses->count() > 0)
                            <div class="mt-5">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-glamora-muted">
                                    Direcciones guardadas en tu cuenta:
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($addresses as $addr)
                                        <button
                                            type="button"
                                            @click="fillAddress(@js([
                                                'recipient_name' => $addr->recipient_name,
                                                'phone' => $addr->phone,
                                                'address_line' => $addr->address_line,
                                                'district' => $addr->district,
                                                'city' => $addr->city,
                                            ]))"
                                            class="rounded-full border border-glamora-blush/60 bg-glamora-cream px-3.5 py-1.5 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta hover:bg-white"
                                        >
                                            {{ $addr->label }} · {{ $addr->district }}
                                            @if ($addr->is_default)
                                                <span class="text-glamora-terracotta">(Predet.)</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="customer_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Nombre completo / Razón Social *
                                </label>
                                <input
                                    id="customer_name"
                                    type="text"
                                    name="customer_name"
                                    x-model="customerName"
                                    required
                                    class="input-glamora"
                                >
                            </div>

                            <div>
                                <label for="customer_email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Correo electrónico <span x-show="paymentMethod !== 'whatsapp'">*</span>
                                </label>
                                <input
                                    id="customer_email"
                                    type="email"
                                    name="customer_email"
                                    value="{{ $prefillEmail }}"
                                    :required="paymentMethod !== 'whatsapp'"
                                    class="input-glamora"
                                >
                            </div>

                            <div>
                                <label for="customer_phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Teléfono / WhatsApp *
                                </label>
                                <input
                                    id="customer_phone"
                                    type="text"
                                    name="customer_phone"
                                    x-model="customerPhone"
                                    required
                                    placeholder="+51 917 328 085"
                                    class="input-glamora"
                                >
                            </div>

                            <div class="grid grid-cols-3 gap-2.5">
                                <div>
                                    <label for="document_type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                        Tipo Doc. *
                                    </label>
                                    <select id="document_type" name="document_type" class="input-glamora">
                                        <option value="DNI" @selected($prefillDocType === 'DNI')>DNI</option>
                                        <option value="RUC" @selected($prefillDocType === 'RUC')>RUC</option>
                                        <option value="CE" @selected($prefillDocType === 'CE')>CE</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label for="document_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                        N.º Documento <span x-show="paymentMethod !== 'whatsapp'">*</span>
                                    </label>
                                    <input
                                        id="document_number"
                                        type="text"
                                        name="document_number"
                                        value="{{ $prefillDocNumber }}"
                                        :required="paymentMethod !== 'whatsapp'"
                                        placeholder="72456789 / 2060..."
                                        class="input-glamora font-mono"
                                    >
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="shipping_address" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Dirección exacta de entrega <span x-show="paymentMethod !== 'whatsapp'">*</span>
                                </label>
                                <input
                                    id="shipping_address"
                                    type="text"
                                    name="shipping_address"
                                    x-model="shippingAddress"
                                    :required="paymentMethod !== 'whatsapp'"
                                    placeholder="Av. Larco 1150, Dpto 401"
                                    class="input-glamora"
                                >
                            </div>

                            <div>
                                <label for="shipping_district" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Distrito <span x-show="paymentMethod !== 'whatsapp'">*</span>
                                </label>
                                <input
                                    id="shipping_district"
                                    type="text"
                                    name="shipping_district"
                                    x-model="shippingDistrict"
                                    :required="paymentMethod !== 'whatsapp'"
                                    placeholder="Miraflores"
                                    class="input-glamora"
                                >
                            </div>

                            <div>
                                <label for="shipping_city" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Ciudad / Departamento <span x-show="paymentMethod !== 'whatsapp'">*</span>
                                </label>
                                <input
                                    id="shipping_city"
                                    type="text"
                                    name="shipping_city"
                                    x-model="shippingCity"
                                    :required="paymentMethod !== 'whatsapp'"
                                    placeholder="Lima"
                                    class="input-glamora"
                                >
                            </div>

                            <div class="sm:col-span-2">
                                <label for="notes" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Notas de despacho o referencia (opcional)
                                </label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="2"
                                    class="input-glamora"
                                    placeholder="Horario preferido, puerta de recepción B2B, referencia del edificio..."
                                >{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </section>

                    {{-- PASO 2: Selección de Envío --}}
                    <section class="card-glamora p-6 sm:p-8">
                        <div class="flex items-center gap-3 border-b border-glamora-blush/30 pb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-glamora-charcoal text-xs font-bold text-glamora-gold">
                                2
                            </span>
                            <div>
                                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                    Método de Envío
                                </h2>
                                <p class="text-xs text-glamora-muted">
                                    Envío Estándar gratuito desde S/ {{ number_format((float) $summary['free_shipping_threshold'], 2) }}.
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3.5 sm:grid-cols-3">
                            @foreach ($shippingMethods as $method)
                                @php
                                    $quoteData = $shippingQuotes[$method->value];
                                    $isFree = (bool) $quoteData['is_free'];
                                @endphp
                                <label
                                    @click="shippingMethod = '{{ $method->value }}'"
                                    :class="shippingMethod === '{{ $method->value }}' ? 'border-glamora-terracotta bg-glamora-cream ring-2 ring-glamora-terracotta/25' : 'border-glamora-blush/40 bg-white hover:border-glamora-gold'"
                                    class="relative flex cursor-pointer flex-col justify-between rounded-2xl border p-4 transition"
                                >
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <input
                                                type="radio"
                                                name="shipping_method"
                                                value="{{ $method->value }}"
                                                x-model="shippingMethod"
                                                class="accent-glamora-terracotta"
                                            >
                                            <span class="font-serif text-lg font-bold {{ $isFree ? 'text-emerald-700' : 'text-glamora-charcoal' }}">
                                                {{ $isFree ? 'GRATIS' : $quoteData['cost']['formatted'] }}
                                            </span>
                                        </div>
                                        <p class="mt-2.5 text-sm font-bold text-glamora-charcoal">
                                            {{ $method->label() }}
                                        </p>
                                    </div>
                                    <p class="mt-2 text-xs text-glamora-muted">
                                        {{ $method->estimatedWindow() }}
                                    </p>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- PASO 3: Pasarela Multi-Método --}}
                    <section class="card-glamora p-6 sm:p-8">
                        <div class="flex items-center gap-3 border-b border-glamora-blush/30 pb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-glamora-charcoal text-xs font-bold text-glamora-gold">
                                3
                            </span>
                            <div>
                                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                    Pasarela de Pago Multi-Método
                                </h2>
                                <p class="text-xs text-glamora-muted">
                                    Elige entre confirmación instantánea (Tarjeta / Crédito B2B) o validación por comprobante (Yape/Plin, Transferencia, Cripto).
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($paymentMethods as $pm)
                                @php
                                    $isB2bOption = $pm === \App\Enums\PaymentMethod::CreditoB2b;
                                    $b2bAvailable = $wholesaleProfile !== null && $availableB2bCredit > 0;
                                @endphp
                                <label
                                    @if ($isB2bOption && ! $b2bAvailable)
                                        title="Requiere perfil mayorista B2B aprobado con saldo disponible"
                                    @endif
                                    :class="paymentMethod === '{{ $pm->value }}' ? 'border-glamora-charcoal bg-glamora-cream ring-2 ring-glamora-charcoal/20' : 'border-glamora-blush/40 bg-white hover:border-glamora-gold'"
                                    class="flex cursor-pointer flex-col justify-between rounded-2xl border p-4 transition {{ $isB2bOption && ! $b2bAvailable ? 'opacity-55' : '' }}"
                                >
                                    <div class="flex items-center justify-between">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="{{ $pm->value }}"
                                            x-model="paymentMethod"
                                            @if ($isB2bOption && ! $b2bAvailable) disabled @endif
                                            class="accent-glamora-charcoal"
                                        >
                                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $pm->isInstant() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                                            {{ $pm->isInstant() ? 'Confirmación Inmediata' : 'Verificación Manual' }}
                                        </span>
                                    </div>
                                    <p class="mt-2.5 text-sm font-bold text-glamora-charcoal">
                                        {{ $pm->label() }}
                                    </p>
                                </label>
                            @endforeach
                        </div>

                        {{-- Dynamic Method Details --}}
                        <div class="mt-6 rounded-2xl border border-glamora-blush/40 bg-glamora-cream/65 p-5">
                            {{-- TARJETA --}}
                            <div x-show="paymentMethod === 'tarjeta'" x-cloak class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                        Pago con Tarjeta (Pasarela Simulada 3D Secure)
                                    </h3>
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold text-emerald-800">
                                        PagoVerificado inmediato
                                    </span>
                                </div>
                                <p class="text-xs text-glamora-muted">
                                    Por seguridad PCI-DSS, nunca almacenamos el número completo de tarjeta ni el CVV: solo registramos los últimos 4 dígitos y el token 3D Secure.
                                </p>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="card_holder" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Titular de la tarjeta *
                                        </label>
                                        <input
                                            id="card_holder"
                                            type="text"
                                            name="card_holder"
                                            value="{{ old('card_holder', $prefillName) }}"
                                            :disabled="paymentMethod !== 'tarjeta'"
                                            placeholder="Como figura en el plástico"
                                            class="input-glamora"
                                        >
                                    </div>
                                    <div>
                                        <label for="card_simulated_pan" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Número simulado (autocompleta 4 dígitos)
                                        </label>
                                        <input
                                            id="card_simulated_pan"
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="19"
                                            placeholder="4532 •••• •••• 4242"
                                            @input="syncCardDigits($event.target.value)"
                                            class="input-glamora font-mono"
                                        >
                                    </div>
                                    <div>
                                        <label for="card_last4" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Últimos 4 dígitos *
                                        </label>
                                        <input
                                            id="card_last4"
                                            type="text"
                                            name="card_last4"
                                            x-model="cardLast4"
                                            maxlength="4"
                                            :disabled="paymentMethod !== 'tarjeta'"
                                            placeholder="4242"
                                            class="input-glamora font-mono"
                                        >
                                    </div>
                                    <div>
                                        <label for="three_ds_code" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Código de autorización 3DS (simulado)
                                        </label>
                                        <input
                                            id="three_ds_code"
                                            type="text"
                                            name="three_ds_code"
                                            value="{{ old('three_ds_code', '3DS-AUTH-2026') }}"
                                            :disabled="paymentMethod !== 'tarjeta'"
                                            class="input-glamora font-mono"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- YAPE / PLIN --}}
                            <div x-show="paymentMethod === 'yape_plin'" x-cloak class="space-y-4">
                                <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                    Yape / Plin — Glamora Perú S.A.C.
                                </h3>
                                <p class="text-xs text-glamora-muted">
                                    {{ \App\Enums\PaymentMethod::YapePlin->instructions() }}
                                </p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="yape_ref" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Número de operación Yape / Plin *
                                        </label>
                                        <input
                                            id="yape_ref"
                                            type="text"
                                            name="transaction_reference"
                                            value="{{ old('transaction_reference') }}"
                                            :disabled="paymentMethod !== 'yape_plin'"
                                            placeholder="Ej. YAPE-884920"
                                            class="input-glamora font-mono"
                                        >
                                    </div>
                                    <div>
                                        <label for="yape_voucher" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Captura / Comprobante obligatorio *
                                        </label>
                                        <input
                                            id="yape_voucher"
                                            type="file"
                                            name="voucher"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                                            :disabled="paymentMethod !== 'yape_plin'"
                                            class="input-glamora text-xs"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- TRANSFERENCIA --}}
                            <div x-show="paymentMethod === 'transferencia'" x-cloak class="space-y-4">
                                <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                    Transferencia Bancaria (BCP / Interbank)
                                </h3>
                                <p class="text-xs text-glamora-muted">
                                    {{ \App\Enums\PaymentMethod::Transferencia->instructions() }}
                                </p>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label for="bank_name" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Banco origen
                                        </label>
                                        <select
                                            id="bank_name"
                                            name="bank_name"
                                            :disabled="paymentMethod !== 'transferencia'"
                                            class="input-glamora"
                                        >
                                            <option value="BCP" @selected(old('bank_name') === 'BCP')>BCP</option>
                                            <option value="Interbank" @selected(old('bank_name') === 'Interbank')>Interbank</option>
                                            <option value="BBVA" @selected(old('bank_name') === 'BBVA')>BBVA</option>
                                            <option value="Scotiabank" @selected(old('bank_name') === 'Scotiabank')>Scotiabank</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="bank_ref" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            N.º de operación / referencia *
                                        </label>
                                        <input
                                            id="bank_ref"
                                            type="text"
                                            name="transaction_reference"
                                            value="{{ old('transaction_reference') }}"
                                            :disabled="paymentMethod !== 'transferencia'"
                                            placeholder="OP-0094812"
                                            class="input-glamora font-mono"
                                        >
                                    </div>
                                    <div>
                                        <label for="bank_voucher" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Voucher PDF o imagen *
                                        </label>
                                        <input
                                            id="bank_voucher"
                                            type="file"
                                            name="voucher"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                                            :disabled="paymentMethod !== 'transferencia'"
                                            class="input-glamora text-xs"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- CRIPTO --}}
                            <div x-show="paymentMethod === 'cripto'" x-cloak class="space-y-4">
                                <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                    Criptomonedas (USDT / USDC)
                                </h3>
                                <p class="text-xs text-glamora-muted">
                                    {{ \App\Enums\PaymentMethod::Cripto->instructions() }}
                                </p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="crypto_network" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Red Blockchain *
                                        </label>
                                        <select
                                            id="crypto_network"
                                            name="crypto_network"
                                            :disabled="paymentMethod !== 'cripto'"
                                            class="input-glamora"
                                        >
                                            <option value="TRC20" @selected(old('crypto_network') === 'TRC20')>TRON (TRC20)</option>
                                            <option value="Polygon" @selected(old('crypto_network') === 'Polygon')>Polygon (POS)</option>
                                            <option value="ERC20" @selected(old('crypto_network') === 'ERC20')>Ethereum (ERC20)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="crypto_voucher" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Captura de wallet (opcional)
                                        </label>
                                        <input
                                            id="crypto_voucher"
                                            type="file"
                                            name="voucher"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                                            :disabled="paymentMethod !== 'cripto'"
                                            class="input-glamora text-xs"
                                        >
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="crypto_tx_hash" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                            Transaction Hash (TxID) *
                                        </label>
                                        <input
                                            id="crypto_tx_hash"
                                            type="text"
                                            name="crypto_tx_hash"
                                            value="{{ old('crypto_tx_hash') }}"
                                            :disabled="paymentMethod !== 'cripto'"
                                            placeholder="0x... o hash TRC20 completo"
                                            class="input-glamora font-mono"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- CRÉDITO B2B --}}
                            <div x-show="paymentMethod === 'credito_b2b'" x-cloak class="space-y-3">
                                <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                    Línea de Crédito Mayorista B2B
                                </h3>
                                @if ($wholesaleProfile)
                                    <div class="grid gap-3 rounded-xl border border-glamora-gold/50 bg-white p-4 text-xs sm:grid-cols-3">
                                        <div>
                                            <span class="block text-glamora-muted">Empresa aprobada</span>
                                            <strong class="text-glamora-charcoal">{{ $wholesaleProfile->company_name }}</strong>
                                        </div>
                                        <div>
                                            <span class="block text-glamora-muted">Saldo disponible</span>
                                            <strong class="text-emerald-700">S/ {{ number_format($availableB2bCredit, 2) }}</strong>
                                        </div>
                                        <div>
                                            <span class="block text-glamora-muted">Plazo de pago</span>
                                            <strong class="text-glamora-charcoal">{{ $wholesaleProfile->payment_terms_days ?: 30 }} días calendario</strong>
                                        </div>
                                    </div>
                                    <p x-show="!b2bEnough" class="text-xs font-semibold text-rose-700">
                                        Tu saldo de crédito B2B disponible es menor al total de este pedido. Selecciona otro método de pago o reduce cantidades.
                                    </p>
                                @else
                                    <p class="text-xs text-glamora-muted">
                                        Este método es exclusivo para cuentas mayoristas B2B aprobadas.
                                    </p>
                                @endif
                            </div>

                            {{-- WHATSAPP --}}
                            <div x-show="paymentMethod === 'whatsapp'" x-cloak class="space-y-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                        Pedido Asistido por WhatsApp
                                    </h3>
                                    <span class="rounded-full bg-[#25D366]/15 px-3 py-1 text-[11px] font-bold text-[#128C7E]">
                                        Reserva FEFO inmediata
                                    </span>
                                </div>
                                <p class="text-xs text-glamora-muted">
                                    {{ \App\Enums\PaymentMethod::Whatsapp->instructions() }}
                                </p>
                                <p class="text-[11px] text-glamora-charcoal/80">
                                    Solo necesitas tu <strong>Nombre</strong> y <strong>Teléfono / WhatsApp</strong>; al confirmar obtendrás el enlace directo con el resumen de tu orden para coordinar pago y entrega.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Right Column: Order Summary --}}
                <aside class="lg:col-span-4">
                    <div class="card-glamora sticky top-24 p-6">
                        <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                            Detalle de Orden
                        </h2>

                        <div class="mt-4 max-h-72 space-y-3 overflow-y-auto border-b border-glamora-blush/30 pb-4 pr-1">
                            @foreach ($summary['items'] as $item)
                                @php
                                    $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item['hex_color'] ?? '')) === 1
                                        ? (string) $item['hex_color']
                                        : '#C87D72';
                                @endphp
                                <div class="flex items-start justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-glamora-charcoal">{{ $item['product_name'] }}</p>
                                        <p class="mt-0.5 flex items-center gap-1.5 text-glamora-muted">
                                            <span class="inline-block h-2.5 w-2.5 rounded-full border border-black/15" style="background-color: {{ $safeHex }}"></span>
                                            <span class="truncate">{{ $item['shade_name'] }} &times; {{ (int) $item['quantity'] }}</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 font-semibold text-glamora-charcoal">
                                        S/ {{ number_format((float) $item['subtotal'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <dl class="mt-4 space-y-2.5 border-b border-glamora-blush/30 pb-4 text-sm">
                            <div class="flex justify-between text-glamora-charcoal">
                                <dt>Subtotal productos</dt>
                                <dd>S/ {{ number_format((float) $summary['subtotal'], 2) }}</dd>
                            </div>
                            @if ((float) $summary['coupon_discount'] > 0)
                                <div class="flex justify-between font-medium text-emerald-700">
                                    <dt>Cupón ({{ $summary['coupon_code'] }})</dt>
                                    <dd>-S/ {{ number_format((float) $summary['coupon_discount'], 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between text-xs text-glamora-muted">
                                <dt>Op. Gravada</dt>
                                <dd>S/ {{ number_format((float) $summary['taxable_base'], 2) }}</dd>
                            </div>
                            <div class="flex justify-between text-xs text-glamora-muted">
                                <dt>IGV (18% incluido)</dt>
                                <dd>S/ {{ number_format((float) $summary['tax_igv'], 2) }}</dd>
                            </div>
                            <div class="flex justify-between text-glamora-charcoal">
                                <dt>Envío seleccionado</dt>
                                <dd class="font-semibold" x-text="shippingCost === 0 ? 'GRATIS' : 'S/ ' + shippingCost.toFixed(2)"></dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex items-baseline justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-glamora-muted">Total a Pagar</span>
                            <span class="font-serif text-3xl font-bold text-glamora-charcoal" x-text="'S/ ' + grandTotal.toFixed(2)"></span>
                        </div>

                        <button
                            type="submit"
                            :disabled="paymentMethod === 'credito_b2b' && !b2bEnough"
                            class="btn-glamora-primary mt-6 w-full justify-center"
                        >
                            Confirmar Pedido &rarr;
                        </button>

                        @if ($whatsappCheckoutEnabled ?? false)
                            <button
                                type="submit"
                                name="whatsapp_quick"
                                value="1"
                                @mousedown="paymentMethod = 'whatsapp'"
                                @click="paymentMethod = 'whatsapp'"
                                class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full border border-[#25D366] bg-[#25D366]/10 px-6 py-3 text-xs font-bold uppercase tracking-wider text-[#128C7E] transition hover:bg-[#25D366] hover:text-white"
                            >
                                Enviar Pedido por WhatsApp
                            </button>
                        @endif

                        <p class="mt-3 text-center text-[11px] text-glamora-muted">
                            Al confirmar, asignaremos automáticamente tus lotes FEFO con fecha de vencimiento más próxima vigente.
                        </p>
                    </div>
                </aside>
            </div>
        </form>
    </div>
@endsection
