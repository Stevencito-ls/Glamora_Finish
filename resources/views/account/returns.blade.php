@extends('layouts.storefront')

@section('title', 'Mis Devoluciones RMA — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @component('account.partials.shell')
        {{-- Quick RMA Form --}}
        <div
            x-data="{
                selectedOrder: @js(old('order_number', data_get($eligibleOrders->first(), 'order.order_number', ''))),
                ordersData: @js($eligibleOrders->mapWithKeys(fn ($e) => [$e['order']->order_number => $e['returnable_items']])->all()),
                get currentItems() {
                    return this.ordersData[this.selectedOrder] ?? [];
                }
            }"
            class="card-glamora p-6"
        >
            <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                Solicitar Devolución RMA
            </h2>
            <p class="mt-1 text-xs text-glamora-muted">
                Solo los pedidos confirmados o entregados con unidades disponibles califican para inspección sanitaria en zona ALM-CUAR-E01.
            </p>

            @if ($eligibleOrders->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-glamora-blush/60 bg-glamora-cream/60 p-5 text-sm text-glamora-muted">
                    No tienes pedidos elegibles con unidades pendientes para devolución en este momento.
                </div>
            @else
                <form action="{{ route('account.returns.store') }}" method="POST" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="rma_order_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Pedido elegible *
                        </label>
                        <select
                            id="rma_order_number"
                            name="order_number"
                            x-model="selectedOrder"
                            class="input-glamora font-mono"
                        >
                            @foreach ($eligibleOrders as $entry)
                                <option value="{{ $entry['order']->order_number }}">
                                    {{ $entry['order']->order_number }} — {{ $entry['order']->status->label() }} (S/ {{ number_format((float) $entry['order']->total, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Productos a devolver:
                        </p>
                        <div class="space-y-2.5">
                            <template x-for="item in currentItems" :key="item.id">
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-glamora-blush/40 bg-glamora-cream/55 px-4 py-3 text-sm">
                                    <label class="flex items-center gap-2.5">
                                        <input
                                            type="checkbox"
                                            :name="'items[' + item.id + '][selected]'"
                                            value="1"
                                            class="accent-glamora-terracotta"
                                        >
                                        <span>
                                            <strong class="text-glamora-charcoal" x-text="item.product_name"></strong>
                                            <span class="block text-xs text-glamora-muted">
                                                <span x-text="item.variant_name"></span> · <span class="font-mono" x-text="item.sku"></span> (máx. <span x-text="item.max_returnable"></span>)
                                            </span>
                                        </span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-glamora-muted">Cant.:</span>
                                        <input
                                            type="number"
                                            :name="'items[' + item.id + '][quantity]'"
                                            value="1"
                                            min="1"
                                            :max="item.max_returnable"
                                            class="w-18 rounded-lg border border-glamora-charcoal/20 bg-white px-2.5 py-1 text-center text-sm font-semibold"
                                        >
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label for="rma_reason" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Motivo detallado de la devolución *
                        </label>
                        <textarea
                            id="rma_reason"
                            name="reason"
                            rows="3"
                            required
                            placeholder="Ej. Cambio de tono solicitado, caja sellada con precinto intacto..."
                            class="input-glamora"
                        >{{ old('reason') }}</textarea>
                    </div>

                    <button type="submit" class="btn-glamora-primary">
                        Registrar Solicitud RMA &rarr;
                    </button>
                </form>
            @endif
        </div>

        {{-- RMA History --}}
        <div class="card-glamora overflow-hidden">
            <div class="border-b border-glamora-blush/30 bg-white px-6 py-4">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    Historial de Solicitudes RMA
                </h2>
            </div>

            @if ($returns->isEmpty())
                <div class="p-8 text-center text-sm text-glamora-muted">
                    No registras devoluciones RMA hasta el momento.
                </div>
            @else
                <div class="divide-y divide-glamora-blush/25 bg-white">
                    @foreach ($returns as $rma)
                        <div class="p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="font-mono text-base font-bold text-glamora-charcoal">{{ $rma->rma_number }}</span>
                                    <span class="rounded-full border border-glamora-blush/50 bg-glamora-cream px-3 py-0.5 text-[11px] font-bold uppercase text-glamora-charcoal">
                                        {{ str_replace('_', ' ', $rma->status) }}
                                    </span>
                                </div>
                                <span class="text-xs text-glamora-muted">
                                    Pedido: <strong class="font-mono text-glamora-charcoal">{{ $rma->order?->order_number }}</strong> · {{ $rma->created_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-glamora-charcoal">{{ $rma->reason }}</p>

                            @if ($rma->items->count() > 0)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($rma->items as $rItem)
                                        <span class="rounded-full border border-glamora-blush/40 bg-glamora-cream px-3 py-1 text-xs text-glamora-charcoal">
                                            {{ $rItem->orderItem?->sku ?? 'SKU' }} &times; {{ $rItem->quantity }}
                                            @if ($rItem->condition)
                                                · {{ $rItem->condition->label() }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($rma->resolution_notes)
                                <p class="mt-3 rounded-xl bg-emerald-50 px-3.5 py-2 text-xs text-emerald-900">
                                    <strong>Resolución sanitaria:</strong> {{ $rma->resolution_notes }}
                                    @if ((float) $rma->refund_amount > 0)
                                        · Reembolso: S/ {{ number_format((float) $rma->refund_amount, 2) }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endcomponent
@endsection
