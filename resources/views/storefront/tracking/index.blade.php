@extends('layouts.storefront')

@section('title', 'Seguimiento de Pedidos & Devoluciones RMA — Glamora Perú')
@section('meta_description', 'Consulta el estado en tiempo real de tu pedido Glamora Perú por número de orden o código de rastreo y registra solicitudes RMA.')
@section('canonical_url', route('tracking.index'))
@if ($searched)
    @section('meta_robots', 'noindex, nofollow')
@endif

@section('content')
    <section class="border-b border-glamora-blush/35 bg-gradient-to-r from-[#FAF6F2] via-white to-[#F7ECE4]">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                Trazabilidad Logística &amp; RMA
            </span>
            <h1 class="mt-2 font-serif text-4xl font-semibold text-glamora-charcoal sm:text-5xl">
                Seguimiento de Pedido &amp; Logística Inversa
            </h1>
            <p class="mt-2 max-w-2xl text-sm text-glamora-muted">
                Consulta en tiempo real el avance de tu pedido por número de orden o código de rastreo, revisa los lotes FEFO asignados o registra una solicitud RMA.
            </p>

            {{-- Search Form --}}
            <form action="{{ route('tracking.index') }}" method="GET" class="mt-7 max-w-2xl">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex-1">
                        <label for="tracking-codigo" class="sr-only">Número de pedido o código de seguimiento</label>
                        <input
                            id="tracking-codigo"
                            type="text"
                            name="codigo"
                            value="{{ $searchCode }}"
                            placeholder="Ingresa tu orden (ej. GLM-2026-0001 o GLM-TRK-260001)"
                            class="input-glamora h-12 font-mono uppercase"
                        >
                    </div>
                    <button type="submit" class="btn-glamora-primary h-12 px-7 text-xs uppercase tracking-wider">
                        Consultar Pedido
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-glamora-muted">
                    <span>Ejemplos de consulta rápida:</span>
                    @foreach (['GLM-2026-0001', 'GLM-2026-0003', 'GLM-2026-0004', 'GLM-2026-0005'] as $sampleCode)
                        <a
                            href="{{ route('tracking.index', ['codigo' => $sampleCode]) }}"
                            class="rounded-full border border-glamora-blush/50 bg-white px-3 py-1 font-mono text-[11px] font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta hover:text-glamora-terracotta"
                        >
                            {{ $sampleCode }}
                        </a>
                    @endforeach
                </div>
            </form>
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-12 px-4 py-12 sm:px-6 lg:px-8">
        {{-- Search Result --}}
        @if ($searched && $order === null)
            <div class="card-glamora border-amber-200 bg-amber-50/70 p-8 text-center">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    No encontramos ningún pedido con el código «{{ $searchCode }}»
                </h2>
                <p class="mt-2 text-sm text-glamora-muted">
                    Verifica que el formato sea similar a <span class="font-mono font-semibold">GLM-2026-0001</span> o <span class="font-mono font-semibold">GLM-TRK-260001</span>.
                </p>
            </div>
        @endif

        @if ($order !== null)
            @php
                $currentStep = $order->status->step();
                $isCancelled = $order->status === \App\Enums\OrderStatus::Cancelado;
            @endphp

            <section class="card-glamora overflow-hidden">
                {{-- Order Summary Header --}}
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-glamora-blush/35 bg-glamora-charcoal px-6 py-5 text-glamora-cream sm:px-8">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="font-mono text-lg font-bold text-glamora-gold">{{ $order->order_number }}</span>
                            @if ($order->tracking_code)
                                <span class="rounded-full border border-white/20 px-3 py-0.5 font-mono text-xs text-glamora-cream/80">
                                    Tracking: {{ $order->tracking_code }}
                                </span>
                            @endif
                            @if ($order->is_b2b)
                                <span class="rounded-full bg-glamora-gold px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-glamora-charcoal">
                                    Orden B2B
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-glamora-cream/75">
                            Registrado el {{ $order->created_at?->format('d/m/Y H:i') }} · {{ $order->shipping_method->label() }} ({{ $order->shipping_district }}, {{ $order->shipping_city }})
                        </p>
                    </div>

                    <div class="text-right">
                        <span class="inline-flex rounded-full border px-3.5 py-1 text-xs font-bold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                        <p class="mt-1 font-serif text-2xl font-bold text-white">
                            S/ {{ number_format((float) $order->total, 2) }}
                        </p>
                    </div>
                </div>

                {{-- 7-State Timeline --}}
                <div class="border-b border-glamora-blush/30 bg-white px-6 py-8 sm:px-8">
                    @if ($isCancelled)
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                            <strong>Pedido Cancelado:</strong> Esta orden fue anulada y no continuará su flujo de despacho.
                        </div>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                            @foreach ($timelineSteps as $stepEnum)
                                @php
                                    $stepNum = $stepEnum->step();
                                    $completed = $currentStep > $stepNum;
                                    $active = $currentStep === $stepNum;
                                @endphp
                                <div @class([
                                    'relative flex flex-col justify-between rounded-2xl border p-3.5 transition',
                                    'border-emerald-300 bg-emerald-50/70' => $completed,
                                    'border-glamora-terracotta bg-glamora-cream ring-2 ring-glamora-terracotta/25' => $active,
                                    'border-glamora-blush/30 bg-white opacity-60' => ! $completed && ! $active,
                                ])>
                                    <div class="flex items-center justify-between">
                                        <span @class([
                                            'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                                            'bg-emerald-600 text-white' => $completed,
                                            'bg-glamora-terracotta text-white' => $active,
                                            'bg-glamora-cream text-glamora-muted' => ! $completed && ! $active,
                                        ])>
                                            @if ($completed)
                                                &#10003;
                                            @else
                                                {{ $stepNum }}
                                            @endif
                                        </span>
                                        @if ($active)
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">Actual</span>
                                        @endif
                                    </div>

                                    <p class="mt-3 text-xs font-bold leading-snug text-glamora-charcoal">
                                        {{ $stepEnum->label() }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-6 text-xs text-glamora-muted">
                        <span><strong>Ventana estimada:</strong> {{ $order->shipping_method->estimatedWindow() }}</span>
                        @if ($order->fefo_dispatched_at)
                            <span><strong>Despacho FEFO:</strong> {{ $order->fefo_dispatched_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if ($order->delivered_at)
                            <span><strong>Entregado el:</strong> {{ $order->delivered_at->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Items & Payment Details --}}
                <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                            Detalle de productos del pedido
                        </h2>
                        <div class="mt-4 overflow-x-auto rounded-2xl border border-glamora-blush/30">
                            <table class="min-w-full divide-y divide-glamora-blush/25 text-left text-xs">
                                <thead class="bg-glamora-cream text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                                    <tr>
                                        <th class="px-4 py-3">Producto / Tono</th>
                                        <th class="px-4 py-3">SKU</th>
                                        <th class="px-4 py-3">Cant.</th>
                                        <th class="px-4 py-3">P. Unit.</th>
                                        <th class="px-4 py-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-glamora-blush/20 bg-white">
                                    @foreach ($order->items as $item)
                                        @php
                                            $safeShadeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $item->shade_hex) === 1
                                                ? (string) $item->shade_hex
                                                : '#C87D72';
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <span
                                                        class="h-4 w-4 shrink-0 rounded-full border border-black/15"
                                                        style="background-color: {{ $safeShadeHex }}"
                                                    ></span>
                                                    <div>
                                                        <p class="font-semibold text-glamora-charcoal">{{ $item->product_name }}</p>
                                                        <p class="text-[11px] text-glamora-muted">{{ $item->variant_name }}</p>
                                                        @if (! empty($item->batch_allocations))
                                                            <p class="mt-0.5 font-mono text-[10px] text-glamora-terracotta">
                                                                Lote FEFO: {{ $item->batch_allocations[0]['batch_number'] ?? '' }}
                                                                @if (! empty($item->batch_allocations[0]['expiration_date']))
                                                                    (Vence {{ $item->batch_allocations[0]['expiration_date'] }})
                                                                @endif
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 font-mono text-glamora-muted">{{ $item->sku }}</td>
                                            <td class="px-4 py-3 font-semibold">{{ $item->quantity }}</td>
                                            <td class="px-4 py-3">S/ {{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td class="px-4 py-3 text-right font-bold text-glamora-charcoal">
                                                S/ {{ number_format((float) $item->subtotal, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-4 lg:col-span-4">
                        @if ($privacySummary !== null)
                            <div class="rounded-2xl border border-glamora-blush/35 bg-white p-5">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">Titular &amp; Entrega</h3>
                                    <span class="rounded-full bg-glamora-cream px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-glamora-muted">
                                        Datos Protegidos
                                    </span>
                                </div>
                                <dl class="mt-3 space-y-1.5 text-xs">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-glamora-muted">Destinatario</dt>
                                        <dd class="text-right font-semibold text-glamora-charcoal">{{ $privacySummary['recipient'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-glamora-muted">Correo</dt>
                                        <dd class="text-right font-mono text-[11px] text-glamora-charcoal">{{ $privacySummary['email'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-glamora-muted">Teléfono</dt>
                                        <dd class="text-right font-mono text-[11px] text-glamora-charcoal">{{ $privacySummary['phone'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-glamora-muted">Documento</dt>
                                        <dd class="text-right font-mono text-[11px] text-glamora-charcoal">{{ $privacySummary['document'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-glamora-muted">Zona de entrega</dt>
                                        <dd class="text-right text-glamora-charcoal">{{ $privacySummary['address'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        <div class="rounded-2xl border border-glamora-blush/35 bg-glamora-cream/70 p-5">
                            <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">Resumen &amp; Pago</h3>
                            <dl class="mt-3 space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <dt class="text-glamora-muted">Subtotal</dt>
                                    <dd class="font-semibold">S/ {{ number_format((float) $order->subtotal, 2) }}</dd>
                                </div>
                                @if ((float) $order->discount_amount > 0)
                                    <div class="flex justify-between text-emerald-700">
                                        <dt>Descuento {{ $order->coupon_code ? "({$order->coupon_code})" : '' }}</dt>
                                        <dd class="font-semibold">&minus; S/ {{ number_format((float) $order->discount_amount, 2) }}</dd>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <dt class="text-glamora-muted">IGV incluido (18%)</dt>
                                    <dd>S/ {{ number_format((float) $order->tax_igv, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-glamora-muted">Envío</dt>
                                    <dd class="font-semibold">
                                        {{ (float) $order->shipping_cost > 0 ? 'S/ ' . number_format((float) $order->shipping_cost, 2) : 'Gratis' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between border-t border-glamora-blush/35 pt-2 text-sm font-bold text-glamora-charcoal">
                                    <dt>Total</dt>
                                    <dd>S/ {{ number_format((float) $order->total, 2) }}</dd>
                                </div>
                            </dl>

                            @if ($order->payments->isNotEmpty())
                                <div class="mt-4 border-t border-glamora-blush/35 pt-3 text-xs">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Método de pago</span>
                                    @foreach ($order->payments as $payment)
                                        @php
                                            $maskedRef = $privacySummary['payment_refs'][$payment->id] ?? null;
                                        @endphp
                                        <div class="mt-1.5 flex items-center justify-between">
                                            <span class="font-semibold text-glamora-charcoal">{{ $payment->payment_method->label() }}</span>
                                            <span @class([
                                                'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                                'bg-emerald-100 text-emerald-800' => $payment->status === 'verified',
                                                'bg-amber-100 text-amber-800' => $payment->status !== 'verified',
                                            ])>
                                                {{ $payment->status === 'verified' ? 'Verificado' : 'Pendiente' }}
                                            </span>
                                        </div>
                                        @if ($maskedRef)
                                            <p class="mt-0.5 font-mono text-[10px] text-glamora-muted">Ref: {{ $maskedRef }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Existing RMAs on this order --}}
                        @if ($order->returns->isNotEmpty())
                            <div class="rounded-2xl border border-glamora-blush/35 bg-white p-5">
                                <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                    Solicitudes RMA asociadas
                                </h3>
                                <div class="mt-3 space-y-2.5">
                                    @foreach ($order->returns as $ret)
                                        <div class="rounded-xl border border-glamora-blush/30 bg-glamora-cream/60 p-3 text-xs">
                                            <div class="flex items-center justify-between">
                                                <span class="font-mono font-bold text-glamora-charcoal">{{ $ret->rma_number }}</span>
                                                <span class="rounded-full bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase text-glamora-terracotta">
                                                    {{ $ret->status }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-glamora-muted">
                                                Registrada el {{ $ret->created_at?->format('d/m/Y') }} · Detalle protegido por privacidad.
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- RMA Reverse Logistics Form --}}
        <section id="solicitud-rma" class="card-glamora p-7 sm:p-10">
            <div class="mb-6 border-b border-glamora-blush/30 pb-5">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                    Logística Inversa &amp; Control Sanitario
                </span>
                <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal">
                    Solicitar Devolución / Cambio (RMA)
                </h2>
                <p class="mt-2 max-w-3xl text-sm text-glamora-muted">
                    Si necesitas devolver o cambiar un producto con precinto intacto o reportar una incidencia, valida tu pedido y correo electrónico para generar tu código RMA trazable.
                </p>
            </div>

            @if (session('rma_success'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-bold">{{ session('rma_success') }}</p>
                        </div>
                        @if (session('generated_rma_number'))
                            <span class="rounded-full bg-glamora-charcoal px-4 py-1.5 font-mono text-xs font-bold text-glamora-gold">
                                {{ session('generated_rma_number') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-900">
                    <p class="font-bold">No se pudo registrar la solicitud RMA:</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('tracking.rma') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="rma_order_number" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Código de Pedido *
                        </label>
                        <input
                            id="rma_order_number"
                            type="text"
                            name="order_number"
                            required
                            value="{{ old('order_number', $order?->order_number ?? $searchCode) }}"
                            placeholder="GLM-2026-0001"
                            class="input-glamora font-mono uppercase"
                        >
                    </div>

                    <div>
                        <label for="rma_email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Correo del Comprador *
                        </label>
                        <input
                            id="rma_email"
                            type="email"
                            name="requester_email"
                            required
                            value="{{ old('requester_email') }}"
                            placeholder="correo@ejemplo.pe"
                            class="input-glamora"
                        >
                    </div>

                    <div>
                        <label for="rma_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Nombre del Solicitante (Opcional)
                        </label>
                        <input
                            id="rma_name"
                            type="text"
                            name="requester_name"
                            value="{{ old('requester_name') }}"
                            placeholder="Nombre completo"
                            class="input-glamora"
                        >
                    </div>
                </div>

                @if ($order !== null && $order->items->isNotEmpty())
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                            Selecciona los ítems a devolver del pedido {{ $order->order_number }} *
                        </label>
                        <div class="space-y-2.5 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/55 p-4">
                            @foreach ($order->items as $orderItem)
                                @php
                                    $alreadyReturned = (int) $orderItem->returnItems
                                        ->filter(fn ($ri) => ! in_array($ri->orderReturn?->status, ['rechazado', 'cancelado'], true))
                                        ->sum('quantity');
                                    $maxReturnable = max(0, (int) $orderItem->quantity - $alreadyReturned);
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-glamora-blush/30 bg-white p-3.5 text-xs">
                                    <label class="flex flex-1 cursor-pointer items-center gap-3">
                                        <input
                                            type="checkbox"
                                            name="items[{{ $orderItem->id }}][selected]"
                                            value="1"
                                            @checked(old("items.{$orderItem->id}.selected"))
                                            @disabled($maxReturnable <= 0)
                                            class="h-4 w-4 rounded border-glamora-charcoal/25 text-glamora-terracotta focus:ring-glamora-gold"
                                        >
                                        <div>
                                            <span class="font-semibold text-glamora-charcoal">{{ $orderItem->product_name }}</span>
                                            <span class="block text-glamora-muted">
                                                {{ $orderItem->variant_name }} · <span class="font-mono">{{ $orderItem->sku }}</span> · Comprado: {{ $orderItem->quantity }} | Retornable: {{ $maxReturnable }}
                                            </span>
                                        </div>
                                    </label>

                                    <div class="flex items-center gap-2">
                                        <span class="text-glamora-muted">Cant.:</span>
                                        <input
                                            type="number"
                                            name="items[{{ $orderItem->id }}][quantity]"
                                            min="1"
                                            max="{{ max(1, $maxReturnable) }}"
                                            value="{{ old("items.{$orderItem->id}.quantity", 1) }}"
                                            @disabled($maxReturnable <= 0)
                                            class="w-20 rounded-lg border border-glamora-charcoal/20 px-2.5 py-1.5 text-center font-semibold"
                                        >
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="rma_sku" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                                SKU del producto (Opcional — o busca primero tu pedido arriba)
                            </label>
                            <input
                                id="rma_sku"
                                type="text"
                                name="sku"
                                value="{{ old('sku') }}"
                                placeholder="Ej. GLM-LB-VITC-30"
                                class="input-glamora font-mono uppercase"
                            >
                        </div>

                        <div>
                            <label for="rma_quantity" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                                Cantidad a devolver *
                            </label>
                            <input
                                id="rma_quantity"
                                type="number"
                                name="quantity"
                                min="1"
                                value="{{ old('quantity', 1) }}"
                                class="input-glamora"
                            >
                        </div>
                    </div>
                @endif

                <div>
                    <label for="rma_reason" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                        Motivo detallado de la devolución / cambio *
                    </label>
                    <textarea
                        id="rma_reason"
                        name="reason"
                        rows="3"
                        required
                        placeholder="Indica si el producto mantiene su precinto intacto, si deseas cambio de tono o reportar un daño de transporte."
                        class="input-glamora"
                    >{{ old('reason') }}</textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/25 pt-5">
                    <p class="text-xs text-glamora-muted">
                        Tip: consulta primero tu pedido arriba para seleccionar exactamente las unidades retornables.
                    </p>
                    <button type="submit" class="btn-glamora-primary px-8 py-3.5 text-xs uppercase tracking-wider">
                        Generar Solicitud RMA
                    </button>
                </div>
            </form>
        </section>
    </div>
@endsection
