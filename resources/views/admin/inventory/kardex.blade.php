@extends('layouts.admin')

@section('title', 'Kardex Auditable — Glamora Perú')
@section('page_title', 'Kardex Auditable de Movimientos')
@section('page_subtitle', 'Historial cronológico inmutable de entradas, reservas FEFO, salidas, liberaciones, devoluciones y ajustes.')

@section('header_actions')
    <a href="{{ route('admin.inventory.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Inventario &amp; Lotes
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <form method="GET" action="{{ route('admin.inventory.kardex') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input type="text" name="q" value="{{ $search }}" placeholder="SKU, lote o nota..." class="input-glamora text-xs">

            <select name="variant_id" class="input-glamora text-xs">
                <option value="">Todas las variantes</option>
                @foreach ($variants as $v)
                    <option value="{{ $v->id }}" @selected((string) $v->id === (string) $variantId)>
                        {{ $v->sku }} — {{ $v->shade_name }}
                    </option>
                @endforeach
            </select>

            <select name="batch_id" class="input-glamora text-xs">
                <option value="">Todos los lotes</option>
                @foreach ($batches as $b)
                    <option value="{{ $b->id }}" @selected((string) $b->id === (string) $batchId)>
                        {{ $b->batch_number }}
                    </option>
                @endforeach
            </select>

            <select name="movement_type" class="input-glamora text-xs">
                <option value="">Todos los tipos</option>
                @foreach ($movementTypes as $type)
                    <option value="{{ $type }}" @selected($movementType === $type)>
                        {{ strtoupper($type) }}
                    </option>
                @endforeach
            </select>

            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="date_from" value="{{ $dateFrom }}" title="Desde" class="input-glamora text-xs">
                <input type="date" name="date_to" value="{{ $dateTo }}" title="Hasta" class="input-glamora text-xs">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.inventory.kardex') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">Fecha / ID</th>
                            <th class="px-4 py-3 text-left">Tipo</th>
                            <th class="px-4 py-3 text-left">SKU / Producto</th>
                            <th class="px-4 py-3 text-left">Lote / Ubicación</th>
                            <th class="px-4 py-3 text-center">Mov.</th>
                            <th class="px-4 py-3 text-center">Saldo Disp.</th>
                            <th class="px-4 py-3 text-left">Responsable &amp; Notas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($movements as $mov)
                            @php
                                $typeClass = match ($mov->movement_type) {
                                    'entrada', 'devolucion', 'liberacion' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'salida' => 'border-sky-200 bg-sky-50 text-sky-800',
                                    'reserva' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
                                    'cuarentena', 'merma' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    default => 'border-amber-200 bg-amber-50 text-amber-800',
                                };
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-semibold text-glamora-charcoal">{{ $mov->created_at?->format('d/m/Y H:i') }}</span>
                                    <span class="block font-mono text-[10px] text-glamora-muted">#{{ $mov->id }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $typeClass }}">
                                        {{ $mov->movement_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono font-bold text-glamora-charcoal">{{ $mov->variant?->sku }}</span>
                                    <span class="block text-[11px] text-glamora-muted">{{ $mov->variant?->product?->name }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-glamora-charcoal">{{ $mov->batch?->batch_number ?? '—' }}</span>
                                    <span class="block text-[11px] text-glamora-muted">{{ $mov->warehouseLocation?->code ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center font-mono font-bold {{ $mov->quantity > 0 ? 'text-emerald-700' : ($mov->quantity < 0 ? 'text-rose-700' : 'text-glamora-muted') }}">
                                    {{ $mov->quantity > 0 ? '+' . $mov->quantity : $mov->quantity }}
                                </td>
                                <td class="px-4 py-3 text-center font-mono font-semibold text-glamora-charcoal">
                                    {{ $mov->balance_after }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-glamora-charcoal">{{ $mov->notes }}</span>
                                    <span class="block text-[10px] text-glamora-muted">
                                        Operador: {{ $mov->user?->name ?? 'Sistema' }} · Costo: S/ {{ number_format((float) $mov->unit_cost, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-glamora-muted">
                                    No se registran movimientos de inventario con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($movements->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $movements->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
