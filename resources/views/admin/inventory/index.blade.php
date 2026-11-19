@extends('layouts.admin')

@section('title', 'Inventario FEFO & Lotes — Glamora Perú')
@section('page_title', 'Inventario FEFO, Lotes & Reorden')
@section('page_subtitle', 'Control de caducidades por orden FEFO, cuarentena sanitaria, ingresos de laboratorio y alertas de reposición.')

@section('header_actions')
    <a href="{{ route('admin.inventory.kardex') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        Ver Kardex Auditable
    </a>
    @can('inventory.locations.manage')
        <a href="{{ route('admin.inventory.locations.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
            Ubicaciones
        </a>
    @endcan
@endsection

@section('content')
    <div x-data="{ showBatchForm: false, adjustModal: null }" class="space-y-6">
        {{-- KPI Strip --}}
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Stock Disponible FEFO</p>
                <p class="mt-1 font-serif text-2xl font-bold text-emerald-800">{{ $summaryCounts['total_available'] }} uds.</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Stock Reservado</p>
                <p class="mt-1 font-serif text-2xl font-bold text-sky-800">{{ $summaryCounts['total_reserved'] }} uds.</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Lotes por Vencer &le;60d</p>
                <p class="mt-1 font-serif text-2xl font-bold text-amber-700">{{ $summaryCounts['expiring_60'] }} ({{ $summaryCounts['critical_30'] }} crít.)</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">En Cuarentena</p>
                <p class="mt-1 font-serif text-2xl font-bold text-rose-700">{{ $summaryCounts['quarantine'] }} lotes</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">SKUs en Reorden</p>
                <p class="mt-1 font-serif text-2xl font-bold text-glamora-terracotta">{{ $summaryCounts['reorder_variants'] }} SKUs</p>
            </div>
        </section>

        {{-- New Batch Form Toggle + Reorder Alerts --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            @can('inventory.batches.manage')
                <button
                    type="button"
                    @click="showBatchForm = !showBatchForm"
                    class="btn-glamora-primary py-2.5 px-5 text-xs"
                >
                    <span x-text="showBatchForm ? 'Ocultar Formulario de Ingreso' : '+ Ingresar Nuevo Lote (Entrada Kardex)'"></span>
                </button>
            @endcan
        </div>

        @can('inventory.batches.manage')
            <section x-show="showBatchForm" x-cloak class="card-glamora p-6">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    Ingreso de Nuevo Lote FEFO al Almacén
                </h2>
                <p class="mt-1 text-xs text-glamora-muted">
                    El ingreso registra automáticamente un movimiento <code class="font-mono">entrada</code> en el Kardex auditable.
                </p>

                <form action="{{ route('admin.inventory.batches.store') }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <div class="lg:col-span-2">
                        <label for="batch_variant" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Variante / SKU *</label>
                        <select id="batch_variant" name="product_variant_id" required class="input-glamora text-xs">
                            <option value="">Selecciona SKU...</option>
                            @foreach ($variants as $v)
                                <option value="{{ $v->id }}" @selected((string) old('product_variant_id') === (string) $v->id)>
                                    {{ $v->sku }} — {{ $v->product?->name }} ({{ $v->shade_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="batch_loc" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Ubicación Almacén *</label>
                        <select id="batch_loc" name="warehouse_location_id" required class="input-glamora text-xs">
                            <option value="">Selecciona ubicación...</option>
                            @foreach ($locations->where('is_active', true) as $loc)
                                <option value="{{ $loc->id }}" @selected((string) old('warehouse_location_id') === (string) $loc->id)>
                                    {{ $loc->code }} — {{ $loc->zone }} {{ $loc->temperature_controlled ? '(Climatizada)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="batch_number" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Número de Lote *</label>
                        <input id="batch_number" type="text" name="batch_number" value="{{ old('batch_number') }}" placeholder="LOT-2026-001" required class="input-glamora font-mono text-xs uppercase">
                    </div>

                    <div>
                        <label for="batch_mfg" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Fecha Fabricación</label>
                        <input id="batch_mfg" type="date" name="manufacturing_date" value="{{ old('manufacturing_date') }}" class="input-glamora text-xs">
                    </div>

                    <div>
                        <label for="batch_exp" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Fecha Vencimiento *</label>
                        <input id="batch_exp" type="date" name="expiration_date" value="{{ old('expiration_date') }}" required class="input-glamora text-xs">
                    </div>

                    <div>
                        <label for="batch_qty" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Cantidad Inicial *</label>
                        <input id="batch_qty" type="number" min="1" name="initial_quantity" value="{{ old('initial_quantity', 20) }}" required class="input-glamora text-xs">
                    </div>

                    <div>
                        <label for="batch_cost" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Costo Unitario (S/) *</label>
                        <input id="batch_cost" type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost', '15.00') }}" required class="input-glamora text-xs">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="batch_supplier" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Proveedor / Laboratorio *</label>
                        <input id="batch_supplier" type="text" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="Laboratorio Cosmético S.A.C." required class="input-glamora text-xs">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="batch_notes" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Notas de Recepción</label>
                        <input id="batch_notes" type="text" name="notes" value="{{ old('notes') }}" placeholder="Certificado de análisis verificado..." class="input-glamora text-xs">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <button type="submit" class="btn-glamora-terracotta px-7 py-2.5 text-xs">
                            Registrar Ingreso de Lote
                        </button>
                    </div>
                </form>
            </section>
        @endcan

        {{-- Reorder Alerts Strip --}}
        @if ($reorderAlerts->isNotEmpty())
            <section class="card-glamora p-5">
                <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                    Alertas de Reposición por SKU ({{ $reorderAlerts->count() }})
                </h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($reorderAlerts->take(12) as $alert)
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs',
                            'border-rose-200 bg-rose-50 text-rose-800' => $alert['is_critical'],
                            'border-amber-200 bg-amber-50 text-amber-900' => ! $alert['is_critical'],
                        ])>
                            <strong class="font-mono">{{ $alert['variant']->sku }}</strong>
                            <span>{{ $alert['variant']->shade_name }}:</span>
                            <strong>{{ $alert['available'] }} uds.</strong>
                            <span class="opacity-75">(reo {{ $alert['variant']->product?->reorder_point }})</span>
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar lote, SKU, producto o proveedor..." class="input-glamora">
            <select name="status" class="input-glamora">
                <option value="">Todos los estados</option>
                <option value="active" @selected($statusFilter === 'active')>Activo</option>
                <option value="quarantine" @selected($statusFilter === 'quarantine')>Cuarentena</option>
                <option value="depleted" @selected($statusFilter === 'depleted')>Agotado</option>
                <option value="expired" @selected($statusFilter === 'expired')>Vencido / Merma</option>
            </select>
            <select name="expiry_window" class="input-glamora">
                <option value="">Cualquier vencimiento</option>
                <option value="critical_30" @selected($expiryWindow === 'critical_30')>Crítico &le; 30 días</option>
                <option value="soon_60" @selected($expiryWindow === 'soon_60')>Próximos &le; 60 días</option>
                <option value="expired" @selected($expiryWindow === 'expired')>Ya vencidos</option>
            </select>
            <select name="location_id" class="input-glamora">
                <option value="">Todas las ubicaciones</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected((string) $loc->id === (string) $locationId)>
                        {{ $loc->code }}
                    </option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.inventory.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Batches Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">Lote / Proveedor</th>
                            <th class="px-4 py-3 text-left">SKU / Producto</th>
                            <th class="px-4 py-3 text-left">Ubicación</th>
                            <th class="px-4 py-3 text-center">Vencimiento</th>
                            <th class="px-4 py-3 text-center">Físico / Res. / Disp.</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-right">Gestión</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($batches as $batch)
                            @php
                                $daysLeft = $batch->daysUntilExpiration();
                                $statusBadge = match ($batch->status) {
                                    'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'quarantine' => 'border-amber-200 bg-amber-50 text-amber-800',
                                    'depleted' => 'border-slate-200 bg-slate-100 text-slate-600',
                                    'expired' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-4 py-3">
                                    <p class="font-mono font-bold text-glamora-charcoal">{{ $batch->batch_number }}</p>
                                    <p class="text-[11px] text-glamora-muted">{{ $batch->supplier_name ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-glamora-charcoal">{{ $batch->variant?->product?->name }}</p>
                                    <p class="text-[11px] text-glamora-muted">
                                        <span class="font-mono">{{ $batch->variant?->sku }}</span> · {{ $batch->variant?->shade_name }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($batch->warehouseLocation)
                                        <span class="font-mono font-semibold text-glamora-charcoal">{{ $batch->warehouseLocation->code }}</span>
                                        <span class="block text-[10px] text-glamora-muted">
                                            {{ $batch->warehouseLocation->aisle }}-{{ $batch->warehouseLocation->rack }}-{{ $batch->warehouseLocation->shelf }}
                                            @if ($batch->warehouseLocation->temperature_controlled)
                                                · Climatizada
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-glamora-muted">Sin asignar</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold',
                                        'border-rose-200 bg-rose-50 text-rose-700' => $daysLeft <= 30,
                                        'border-amber-200 bg-amber-50 text-amber-800' => $daysLeft > 30 && $daysLeft <= 60,
                                        'border-slate-200 bg-slate-50 text-slate-700' => $daysLeft > 60,
                                    ])>
                                        {{ $batch->expiration_date?->format('d/m/Y') }} ({{ $daysLeft }}d)
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-mono">
                                    <span class="text-glamora-muted">{{ $batch->current_quantity }}</span> /
                                    <span class="text-sky-700">{{ $batch->reserved_quantity }}</span> /
                                    <strong class="text-emerald-800">{{ $batch->availableQuantity() }}</strong>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $statusBadge }}">
                                        {{ $batch->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('admin.inventory.kardex', ['batch_id' => $batch->id]) }}" class="rounded-full border border-glamora-charcoal/15 px-2.5 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-cream">
                                            Kardex
                                        </a>
                                        @can('inventory.adjust')
                                            <button
                                                type="button"
                                                @click="adjustModal = {{ Js::from([
                                                    'id' => $batch->id,
                                                    'batch_number' => $batch->batch_number,
                                                    'sku' => $batch->variant?->sku,
                                                    'status' => $batch->status,
                                                    'location_id' => $batch->warehouse_location_id,
                                                    'action_url' => route('admin.inventory.batches.adjust', $batch),
                                                ]) }}"
                                                class="rounded-full border border-glamora-terracotta/40 bg-glamora-terracotta/10 px-2.5 py-1 text-[11px] font-semibold text-glamora-terracotta hover:bg-glamora-terracotta hover:text-white"
                                            >
                                                Ajustar / Cuarentena
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron lotes para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($batches->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $batches->links() }}
                </div>
            @endif
        </div>

        {{-- Adjust / Quarantine Modal --}}
        @can('inventory.adjust')
            <div
                x-show="adjustModal !== null"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-glamora-charcoal/60 p-4 backdrop-blur-xs"
            >
                <div @click.away="adjustModal = null" class="card-glamora w-full max-w-lg bg-white p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                Ajuste Auditable / Cuarentena
                            </h3>
                            <p class="text-xs text-glamora-muted">
                                Lote <strong class="font-mono text-glamora-charcoal" x-text="adjustModal?.batch_number"></strong> ·
                                <span class="font-mono" x-text="adjustModal?.sku"></span>
                            </p>
                        </div>
                        <button type="button" @click="adjustModal = null" class="text-lg text-glamora-muted hover:text-glamora-charcoal">&times;</button>
                    </div>

                    <form :action="adjustModal?.action_url" method="POST" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Acción Operativa *</label>
                            <select name="action" required class="input-glamora text-xs">
                                <option value="quarantine">Enviar a Cuarentena Sanitaria</option>
                                <option value="release_quarantine">Liberar de Cuarentena (a Activo)</option>
                                <option value="adjustment_in">Ajuste Positivo (+ unidades por conteo)</option>
                                <option value="adjustment_out">Ajuste Negativo (- unidades por merma/rotura)</option>
                                <option value="relocate">Reubicar en Almacén</option>
                                <option value="mark_expired">Dar de Baja por Vencimiento</option>
                            </select>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Cantidad (solo +/-)</label>
                                <input type="number" name="quantity_delta" min="1" placeholder="Ej. 5" class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Ubicación Destino</label>
                                <select name="warehouse_location_id" :value="adjustModal?.location_id" class="input-glamora text-xs">
                                    <option value="">Mantener actual</option>
                                    @foreach ($locations as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->code }} ({{ $loc->zone }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Motivo / Sustento Auditable *</label>
                            <textarea name="reason" rows="3" required placeholder="Ej. Inspección preventiva de envase o conteo físico cíclico..." class="input-glamora text-xs"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="adjustModal = null" class="btn-glamora-outline px-4 py-2 text-xs">Cancelar</button>
                            <button type="submit" class="btn-glamora-primary px-5 py-2 text-xs">Aplicar y Registrar en Kardex</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection
