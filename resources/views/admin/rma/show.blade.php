@extends('layouts.admin')

@section('title', 'Inspección ' . $orderReturn->rma_number . ' — Glamora Perú')
@section('page_title', 'Inspección RMA ' . $orderReturn->rma_number)
@section('page_subtitle', 'Pedido ' . ($orderReturn->order?->order_number ?? '—') . ' · Solicitante: ' . $orderReturn->requester_name)

@section('header_actions')
    <a href="{{ route('admin.rma.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Bandeja RMA
    </a>
@endsection

@section('content')
    @php
        $isClosed = in_array($orderReturn->status, ['resuelto', 'rechazado'], true);
    @endphp

    <div class="space-y-6">
        <section class="card-glamora grid gap-4 p-6 sm:grid-cols-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Estado RMA</p>
                <p class="mt-1 font-serif text-xl font-bold uppercase text-glamora-charcoal">
                    {{ str_replace('_', ' ', $orderReturn->status) }}
                </p>
                @if ($orderReturn->inspected_at)
                    <p class="mt-1 text-xs text-glamora-muted">
                        Inspeccionado: {{ $orderReturn->inspected_at->format('d/m/Y H:i') }}
                        ({{ $orderReturn->inspector?->name ?? 'Inspector' }})
                    </p>
                @endif
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Motivo del Cliente</p>
                <p class="mt-1 text-xs text-glamora-charcoal">{{ $orderReturn->reason }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Pedido Vinculado &amp; Reembolso</p>
                <p class="mt-1 font-mono text-xs font-bold text-glamora-charcoal">
                    {{ $orderReturn->order?->order_number }} (Total S/ {{ number_format((float) ($orderReturn->order?->total ?? 0), 2) }})
                </p>
                <p class="mt-1 text-xs text-emerald-800 font-semibold">
                    Reembolso aprobado: S/ {{ number_format((float) $orderReturn->refund_amount, 2) }}
                </p>
            </div>
        </section>

        {{-- Quick Inspection Form --}}
        <form action="{{ route('admin.rma.inspect', $orderReturn) }}" method="POST" class="card-glamora overflow-hidden">
            @csrf
            <div class="border-b border-glamora-blush/30 bg-white px-6 py-4">
                <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                    Inspección Sanitaria por Ítem (ALM-CUAR-E01)
                </h2>
                <p class="text-xs text-glamora-muted">
                    Los ítems clasificados como <strong>Apto para reventa</strong> se reintegran automáticamente al inventario FEFO (<code class="font-mono">restocked = true</code>) y generan movimiento <code class="font-mono">devolucion</code> en Kardex.
                </p>
            </div>

            <div class="divide-y divide-glamora-blush/25 bg-white p-6 space-y-5">
                @foreach ($orderReturn->items as $item)
                    <div class="grid gap-4 rounded-xl border border-glamora-blush/35 bg-glamora-cream/45 p-4 lg:grid-cols-12">
                        <div class="lg:col-span-5">
                            <p class="font-semibold text-sm text-glamora-charcoal">
                                {{ $item->orderItem?->product_name ?? $item->variant?->product?->name }}
                            </p>
                            <p class="text-xs text-glamora-muted">
                                {{ $item->orderItem?->variant_name ?? $item->variant?->shade_name }} ·
                                <span class="font-mono">{{ $item->orderItem?->sku ?? $item->variant?->sku }}</span> ·
                                Cantidad devuelta: <strong>{{ $item->quantity }} u.</strong>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if ($item->restocked)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                        Reintegrado a Lote {{ $item->batch?->batch_number ?? '#' . $item->product_batch_id }}
                                    </span>
                                @elseif ($item->condition)
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $item->condition->badgeClasses() }}">
                                        {{ $item->condition->label() }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">
                                        Pendiente de evaluación
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">
                                Condición Sanitaria *
                            </label>
                            @if ($item->restocked && ! $isClosed && auth()->user()->can('returns.inspect'))
                                <input type="hidden" name="items[{{ $item->id }}][condition]" value="{{ $item->condition?->value ?? \App\Enums\ReturnCondition::Reventa->value }}">
                            @endif
                            <select
                                name="items[{{ $item->id }}][condition]"
                                @disabled($isClosed || $item->restocked || ! auth()->user()->can('returns.inspect'))
                                required
                                class="input-glamora text-xs"
                            >
                                <option value="">Seleccionar...</option>
                                @foreach ($conditions as $cond)
                                    <option value="{{ $cond->value }}" @selected(old("items.{$item->id}.condition", $item->condition?->value) === $cond->value)>
                                        {{ $cond->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-4">
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">
                                Notas de Inspección
                            </label>
                            <input
                                type="text"
                                name="items[{{ $item->id }}][inspection_notes]"
                                value="{{ old("items.{$item->id}.inspection_notes", $item->inspection_notes) }}"
                                @disabled($isClosed || ! auth()->user()->can('returns.inspect'))
                                placeholder="Precinto intacto, lote vigente, sin alteraciones..."
                                class="input-glamora text-xs"
                            >
                        </div>
                    </div>
                @endforeach

                @if (! $isClosed && auth()->user()->can('returns.inspect'))
                    <div class="grid gap-4 border-t border-glamora-blush/30 pt-5 sm:grid-cols-3">
                        @can('returns.resolve')
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Resolución RMA
                                </label>
                                <select name="resolution_status" class="input-glamora text-xs">
                                    <option value="en_revision" @selected($orderReturn->status === 'en_revision')>Guardar Inspección (En Revisión)</option>
                                    <option value="resuelto">Aprobar y Cerrar como Resuelto</option>
                                    <option value="rechazado">Rechazar Devolución</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    Monto Reembolso / Nota Crédito (S/)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="refund_amount"
                                    value="{{ old('refund_amount', (float) $orderReturn->refund_amount > 0 ? $orderReturn->refund_amount : $suggestedRefund) }}"
                                    class="input-glamora text-xs"
                                >
                            </div>
                        @endcan

                        <div class="@cannot('returns.resolve') sm:col-span-3 @endcannot">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                Notas de Resolución / Dictamen
                            </label>
                            <input
                                type="text"
                                name="resolution_notes"
                                value="{{ old('resolution_notes', $orderReturn->resolution_notes) }}"
                                placeholder="Observación de control sanitario o reembolso..."
                                class="input-glamora text-xs"
                            >
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="btn-glamora-primary px-6 py-2.5 text-xs">
                            Registrar Inspección &amp; Ejecutar FEFO
                        </button>
                    </div>
                @endif
            </div>
        </form>

        {{-- Dedicated Resolution Box for Admin when RMA is already in_revision --}}
        @if (! $isClosed && $orderReturn->status === 'en_revision')
            @can('returns.resolve')
                <section class="card-glamora p-6">
                    <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                        Resolución Financiera Final del RMA
                    </h2>
                    <form action="{{ route('admin.rma.resolve', $orderReturn) }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-4">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Decisión *</label>
                            <select name="status" required class="input-glamora text-xs">
                                <option value="resuelto" @selected(old('status', 'resuelto') === 'resuelto')>Resuelto (Aprobar Reembolso)</option>
                                <option value="rechazado" @selected(old('status') === 'rechazado')>Rechazado</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Reembolso (S/)</label>
                            <input type="number" step="0.01" min="0" name="refund_amount" value="{{ old('refund_amount', $suggestedRefund) }}" class="input-glamora text-xs">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Sustento Final *</label>
                            <div class="flex gap-2">
                                <input type="text" name="resolution_notes" value="{{ old('resolution_notes') }}" required placeholder="Dictamen final y referencia de reembolso..." class="input-glamora text-xs">
                                <button type="submit" class="btn-glamora-terracotta shrink-0 px-5 py-2 text-xs">
                                    Finalizar RMA
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
            @endcan
        @endif
    </div>
@endsection
