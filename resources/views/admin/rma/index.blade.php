@extends('layouts.admin')

@section('title', 'Devoluciones RMA — Glamora Perú')
@section('page_title', 'Bandeja de Devoluciones RMA & Control Sanitario')
@section('page_subtitle', 'Inspección de ítems devueltos en zona ALM-CUAR-E01 y reintegro automático FEFO para unidades aptas.')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('admin.rma.index') }}"
                @class([
                    'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                    'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $statusFilter === '',
                    'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $statusFilter !== '',
                ])
            >
                Todos ({{ $statusCounts->sum() }})
            </a>

            @foreach (['solicitado' => 'Solicitados', 'en_revision' => 'En Revisión', 'resuelto' => 'Resueltos', 'rechazado' => 'Rechazados'] as $key => $label)
                <a
                    href="{{ route('admin.rma.index', ['status' => $key]) }}"
                    @class([
                        'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $statusFilter === $key,
                        'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $statusFilter !== $key,
                    ])
                >
                    {{ $label }} ({{ (int) ($statusCounts[$key] ?? 0) }})
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.rma.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-3">
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar RMA, pedido, cliente o correo..." class="input-glamora text-xs">
            <select name="status" class="input-glamora text-xs">
                <option value="">Todos los estados</option>
                <option value="solicitado" @selected($statusFilter === 'solicitado')>Solicitado</option>
                <option value="en_revision" @selected($statusFilter === 'en_revision')>En revisión</option>
                <option value="resuelto" @selected($statusFilter === 'resuelto')>Resuelto</option>
                <option value="rechazado" @selected($statusFilter === 'rechazado')>Rechazado</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.rma.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">RMA / Pedido</th>
                            <th class="px-5 py-3 text-left">Solicitante</th>
                            <th class="px-5 py-3 text-left">Motivo &amp; Ítems</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Reembolso</th>
                            <th class="px-5 py-3 text-right">Inspección</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($returns as $rma)
                            @php
                                $badge = match ($rma->status) {
                                    'solicitado' => 'border-amber-200 bg-amber-50 text-amber-800',
                                    'en_revision' => 'border-sky-200 bg-sky-50 text-sky-800',
                                    'resuelto' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'rechazado' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <p class="font-mono font-bold text-glamora-charcoal">{{ $rma->rma_number }}</p>
                                    <p class="font-mono text-[11px] text-glamora-muted">
                                        {{ $rma->order?->order_number ?? '—' }} · {{ $rma->created_at?->format('d/m/Y') }}
                                    </p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">{{ $rma->requester_name }}</p>
                                    <p class="text-[11px] text-glamora-muted">{{ $rma->requester_email }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="line-clamp-2 max-w-md text-glamora-charcoal">{{ $rma->reason }}</p>
                                    <p class="mt-0.5 text-[11px] text-glamora-muted">{{ $rma->items->count() }} ítem(s)</p>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $badge }}">
                                        {{ str_replace('_', ' ', $rma->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $rma->refund_amount, 2) }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('admin.rma.show', $rma) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream">
                                        Inspeccionar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No hay solicitudes RMA registradas para estos criterios.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($returns->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $returns->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
