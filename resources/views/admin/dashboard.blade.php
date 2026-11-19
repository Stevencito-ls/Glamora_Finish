@extends('layouts.admin')

@section('title', 'Dashboard Principal — Glamora Perú')
@section('page_title', 'Dashboard Principal & Indicadores Operativos')
@section('page_subtitle', 'Resumen ejecutivo de ventas, margen bruto, control de lotes FEFO y atención de pedidos.')

@section('content')
    <div class="space-y-6">
        {{-- Top AdminLTE Small-Box KPI Cards --}}
        <section class="row g-3">
            @can('reports.view')
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="small-box bg-dark text-white h-100 mb-0">
                        <div class="inner p-4">
                            <span class="badge bg-warning text-dark mb-2">
                                {{ $financialKpis['monthly_orders_count'] }} pedidos este mes
                            </span>
                            <h3 class="font-serif text-3xl font-bold text-white mb-1">
                                S/ {{ number_format($financialKpis['monthly_sales'], 2) }}
                            </h3>
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider text-warning">
                                Ventas Verificadas (Mes)
                            </p>
                            <small class="text-white-50 text-xs">
                                Acumulado: <strong class="text-white">S/ {{ number_format($financialKpis['total_sales'], 2) }}</strong>
                                ({{ $financialKpis['verified_orders_count'] }} órdenes)
                            </small>
                        </div>
                        <div class="icon text-white-50">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="small-box bg-success text-white h-100 mb-0">
                        <div class="inner p-4">
                            <span class="badge bg-light text-success mb-2">
                                Rentabilidad {{ number_format($financialKpis['margin_percent'], 1) }}%
                            </span>
                            <h3 class="font-serif text-3xl font-bold text-white mb-1">
                                S/ {{ number_format($financialKpis['estimated_margin'], 2) }}
                            </h3>
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider text-white">
                                Margen Bruto Estimado
                            </p>
                            <small class="text-white-50 text-xs">
                                Ventas netas menos costo unitario de lotes.
                            </small>
                        </div>
                        <div class="icon text-white-50">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            @endcan

            @can('inventory.view')
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="small-box bg-info text-dark h-100 mb-0">
                        <div class="inner p-4">
                            <span class="badge bg-dark text-white mb-2">Últimos 30 días</span>
                            <h3 class="font-serif text-3xl font-bold text-dark mb-1">
                                {{ number_format($inventoryKpis['turnover_ratio'], 2) }}x
                            </h3>
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider text-dark">
                                Rotación de Inventario
                            </p>
                            <small class="text-dark/75 text-xs">
                                Salidas: <strong>{{ $inventoryKpis['units_out_30d'] }} uds.</strong> /
                                Stock prom.: <strong>{{ number_format($inventoryKpis['average_stock'], 0) }} uds.</strong>
                            </small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="small-box {{ $inventoryKpis['critical_30_count'] > 0 ? 'bg-danger text-white' : 'bg-warning text-dark' }} h-100 mb-0">
                        <div class="inner p-4">
                            @if ($inventoryKpis['critical_30_count'] > 0)
                                <span class="badge bg-light text-danger mb-2">
                                    {{ $inventoryKpis['critical_30_count'] }} críticos &le;30d
                                </span>
                            @else
                                <span class="badge bg-dark text-white mb-2">Sin vencimiento crítico</span>
                            @endif
                            <h3 class="font-serif text-3xl font-bold mb-1">
                                {{ $inventoryKpis['expiring_60_count'] }} lotes
                            </h3>
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider">
                                Alertas de Vencimiento &amp; Stock
                            </p>
                            <small class="text-xs">
                                Vencen &le;60 días · <strong>{{ $inventoryKpis['reorder_alert_count'] }}</strong> prod. por reabastecer.
                            </small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>
            @endcan
        </section>

        {{-- Operational Queue Strip (AdminLTE Info-Boxes) --}}
        <section class="row g-3">
            @can('orders.view')
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="{{ route('admin.fulfillment.index', ['status' => \App\Enums\OrderStatus::PendientePago->value]) }}" class="info-box mb-0 border border-glamora-blush/40 bg-white shadow-2xs transition hover:border-glamora-terracotta">
                        <span class="info-box-icon bg-warning text-dark elevation-1">
                            <i class="fas fa-receipt"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-xs font-bold uppercase text-glamora-muted">Por Verificar Pago</span>
                            <span class="info-box-number font-serif text-2xl font-bold text-amber-700">{{ $pendingVerificationCount }}</span>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="{{ route('admin.fulfillment.index', ['status' => \App\Enums\OrderStatus::PagoVerificado->value]) }}" class="info-box mb-0 border border-glamora-blush/40 bg-white shadow-2xs transition hover:border-glamora-terracotta">
                        <span class="info-box-icon bg-info text-white elevation-1">
                            <i class="fas fa-truck-fast"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-xs font-bold uppercase text-glamora-muted">Por Despachar FEFO</span>
                            <span class="info-box-number font-serif text-2xl font-bold text-sky-700">{{ $toDispatchCount }}</span>
                        </div>
                    </a>
                </div>
            @endcan

            @can('returns.view')
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="{{ route('admin.rma.index', ['status' => 'solicitado']) }}" class="info-box mb-0 border border-glamora-blush/40 bg-white shadow-2xs transition hover:border-glamora-terracotta">
                        <span class="info-box-icon bg-danger text-white elevation-1">
                            <i class="fas fa-undo-alt"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-xs font-bold uppercase text-glamora-muted">RMAs Pendientes</span>
                            <span class="info-box-number font-serif text-2xl font-bold text-glamora-terracotta">{{ $pendingRmasCount }}</span>
                        </div>
                    </a>
                </div>
            @endcan

            @can('wholesale.view')
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="{{ route('admin.wholesale.index', ['status' => 'pending']) }}" class="info-box mb-0 border border-glamora-blush/40 bg-white shadow-2xs transition hover:border-glamora-terracotta">
                        <span class="info-box-icon bg-dark text-warning elevation-1">
                            <i class="fas fa-building"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-xs font-bold uppercase text-glamora-muted">Postulantes B2B</span>
                            <span class="info-box-number font-serif text-2xl font-bold text-glamora-charcoal">{{ $pendingWholesaleCount }}</span>
                        </div>
                    </a>
                </div>
            @endcan
        </section>

        {{-- Alerts & Queues Detail Grid --}}
        <div class="grid gap-6 xl:grid-cols-2">
            @can('inventory.view')
                {{-- FEFO Expiring Batches --}}
                <section class="card card-outline card-warning mb-0 overflow-hidden">
                    <div class="card-header flex items-center justify-between bg-white px-5 py-3.5">
                        <div>
                            <h2 class="card-title m-0 font-serif text-xl font-semibold text-glamora-charcoal">
                                <i class="fas fa-calendar-check mr-1.5 text-warning"></i>
                                Lotes Próximos a Vencer (FEFO &le; 60 días)
                            </h2>
                            <p class="mt-0.5 mb-0 text-xs text-glamora-muted">Prioriza el despacho o campaña promocional para evitar mermas.</p>
                        </div>
                        <a href="{{ route('admin.inventory.index', ['expiry_window' => 'soon_60']) }}" class="btn btn-sm btn-outline-dark rounded-pill px-3 text-xs font-semibold">
                            Ver todos &rarr;
                        </a>
                    </div>

                    <div class="card-body p-0 overflow-x-auto bg-white">
                        @if ($expiringBatches->isEmpty())
                            <div class="p-6 text-center text-sm text-glamora-muted">
                                Sin lotes activos por vencer en los próximos 60 días.
                            </div>
                        @else
                            <table class="table table-bordered table-hover mb-0 min-w-full text-xs">
                                <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Lote / Ubicación</th>
                                        <th class="px-4 py-3 text-left">Producto / Tono</th>
                                        <th class="px-4 py-3 text-center">Disp.</th>
                                        <th class="px-4 py-3 text-right">Vencimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($expiringBatches as $batch)
                                        @php
                                            $daysLeft = $batch->daysUntilExpiration();
                                            $isCrit = $daysLeft <= 30;
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">
                                                <span class="font-mono font-bold text-glamora-charcoal">{{ $batch->batch_number }}</span>
                                                <span class="block text-[11px] text-glamora-muted">{{ $batch->warehouseLocation?->code ?? 'Sin ubicar' }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="font-semibold text-glamora-charcoal">{{ $batch->variant?->product?->name }}</span>
                                                <span class="block text-[11px] text-glamora-muted">{{ $batch->variant?->shade_name }} · {{ $batch->variant?->sku }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-center font-bold text-glamora-charcoal">
                                                {{ $batch->availableQuantity() }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <span @class([
                                                    'inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold',
                                                    'border-rose-200 bg-rose-50 text-rose-700' => $isCrit,
                                                    'border-amber-200 bg-amber-50 text-amber-800' => ! $isCrit,
                                                ])>
                                                    {{ $batch->expiration_date?->format('d/m/Y') }} ({{ $daysLeft }}d)
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </section>

                {{-- Low Stock / Reorder Point Products --}}
                <section class="card card-outline card-danger mb-0 overflow-hidden">
                    <div class="card-header flex items-center justify-between bg-white px-5 py-3.5">
                        <div>
                            <h2 class="card-title m-0 font-serif text-xl font-semibold text-glamora-charcoal">
                                <i class="fas fa-triangle-exclamation mr-1.5 text-danger"></i>
                                Productos bajo Punto de Reorden / Stock Mínimo
                            </h2>
                            <p class="mt-0.5 mb-0 text-xs text-glamora-muted">Stock disponible actual vs umbrales configurados.</p>
                        </div>
                        <a href="{{ route('admin.inventory.index') }}" class="btn btn-sm btn-outline-dark rounded-pill px-3 text-xs font-semibold">
                            Reabastecer &rarr;
                        </a>
                    </div>

                    <div class="card-body p-0 overflow-x-auto bg-white">
                        @if ($lowStockAlerts->isEmpty())
                            <div class="p-6 text-center text-sm text-glamora-muted">
                                Todos los productos activos superan su punto de reorden.
                            </div>
                        @else
                            <table class="table table-bordered table-hover mb-0 min-w-full text-xs">
                                <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Producto</th>
                                        <th class="px-4 py-3 text-center">Stock Disp.</th>
                                        <th class="px-4 py-3 text-center">Mín. / Reorden</th>
                                        <th class="px-4 py-3 text-right">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lowStockAlerts as $row)
                                        @php
                                            /** @var \App\Models\Product $prod */
                                            $prod = $row['product'];
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">
                                                <span class="font-semibold text-glamora-charcoal">{{ $prod->name }}</span>
                                                <span class="block text-[11px] text-glamora-muted">{{ $prod->brand?->name }} · {{ $prod->category?->name }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-center font-bold text-glamora-charcoal">
                                                {{ $row['available_stock'] }} uds.
                                            </td>
                                            <td class="px-4 py-3 text-center text-glamora-muted">
                                                {{ $prod->min_stock_alert }} / {{ $prod->reorder_point }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                @if ($row['is_critical'])
                                                    <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">
                                                        Crítico
                                                    </span>
                                                @else
                                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">
                                                        Reordenar
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </section>
            @endcan
        </div>

        {{-- Actionable Orders Queue --}}
        @can('orders.view')
            <section class="card card-outline card-primary mb-0 overflow-hidden">
                <div class="card-header flex items-center justify-between bg-white px-5 py-3.5">
                    <div>
                        <h2 class="card-title m-0 font-serif text-xl font-semibold text-glamora-charcoal">
                            <i class="fas fa-shopping-bag mr-1.5 text-glamora-terracotta"></i>
                            Pedidos Activos en Cola Operativa
                        </h2>
                        <p class="mt-0.5 mb-0 text-xs text-glamora-muted">Órdenes pendientes de conciliación de pago o en alistamiento FEFO.</p>
                    </div>
                    <a href="{{ route('admin.fulfillment.index') }}" class="btn btn-sm btn-outline-dark rounded-pill px-3 text-xs font-semibold">
                        Ver Todos los Pedidos &rarr;
                    </a>
                </div>

                <div class="card-body p-3 overflow-x-auto bg-white">
                    @if ($recentActionableOrders->isEmpty())
                        <div class="p-6 text-center text-sm text-glamora-muted">
                            No hay pedidos pendientes en cola en este momento.
                        </div>
                    @else
                        <table class="table table-bordered table-hover datatable min-w-full text-xs">
                            <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                                <tr>
                                    <th class="px-4 py-3 text-left">Pedido</th>
                                    <th class="px-4 py-3 text-left">Cliente / Canal</th>
                                    <th class="px-4 py-3 text-left">Pago</th>
                                    <th class="px-4 py-3 text-left">Estado</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentActionableOrders as $order)
                                    @php $payment = $order->payments->first(); @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="font-mono font-bold text-glamora-charcoal">{{ $order->order_number }}</span>
                                            <span class="block text-[11px] text-glamora-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-glamora-charcoal">{{ $order->customer_name }}</span>
                                            <span class="block text-[11px] text-glamora-muted">
                                                {{ $order->is_b2b ? 'Canal B2B' : 'Boutique' }} · {{ $order->customer_tier->label() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($payment)
                                                <span class="font-medium text-glamora-charcoal">{{ $payment->payment_method->label() }}</span>
                                                <span class="block text-[11px] {{ $payment->status === 'verified' ? 'text-emerald-700' : 'text-amber-700' }}">
                                                    {{ ucfirst($payment->status) }}
                                                </span>
                                            @else
                                                <span class="text-glamora-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $order->status->badgeClasses() }}">
                                                {{ $order->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-glamora-charcoal">
                                            S/ {{ number_format((float) $order->total, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('admin.fulfillment.orders.show', $order) }}" class="btn btn-sm btn-outline-dark rounded-pill px-3 text-[11px] font-semibold">
                                                Gestionar
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        @endcan
    </div>
@endsection
