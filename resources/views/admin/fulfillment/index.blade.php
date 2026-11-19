@extends('layouts.admin')

@section('title', 'Fulfillment & Pagos — Glamora Perú')
@section('page_title', 'Tablero de Fulfillment & Verificación de Pagos')
@section('page_subtitle', 'Control de pedidos por estado, conciliación de vouchers Yape/Transferencia/Cripto y despacho FEFO.')

@section('content')
    <div class="space-y-6">
        {{-- Status Quick Tabs --}}
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('admin.fulfillment.index') }}"
                @class([
                    'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                    'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $statusFilter === '',
                    'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $statusFilter !== '',
                ])
            >
                Todos ({{ $statusCounts->sum() }})
            </a>

            @foreach ($orderStatuses as $statusCase)
                @php $cnt = (int) ($statusCounts[$statusCase->value] ?? 0); @endphp
                <a
                    href="{{ route('admin.fulfillment.index', ['status' => $statusCase->value]) }}"
                    @class([
                        'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $statusFilter === $statusCase->value,
                        'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $statusFilter !== $statusCase->value,
                    ])
                >
                    {{ $statusCase->label() }} ({{ $cnt }})
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.fulfillment.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input type="text" name="q" value="{{ $search }}" placeholder="Orden, tracking, cliente o DNI/RUC..." class="input-glamora text-xs">

            <select name="status" class="input-glamora text-xs">
                <option value="">Todos los estados</option>
                @foreach ($orderStatuses as $st)
                    <option value="{{ $st->value }}" @selected($statusFilter === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>

            <select name="payment_status" class="input-glamora text-xs">
                <option value="">Todos los pagos</option>
                <option value="pending" @selected($paymentStatus === 'pending')>Pago Pendiente</option>
                <option value="verified" @selected($paymentStatus === 'verified')>Pago Verificado</option>
                <option value="rejected" @selected($paymentStatus === 'rejected')>Pago Rechazado</option>
                <option value="refunded" @selected($paymentStatus === 'refunded')>Reembolsado / Revertido</option>
            </select>

            <select name="b2b" class="input-glamora text-xs">
                <option value="">Todos los canales</option>
                <option value="1" @selected($b2bFilter === '1')>Solo Mayoristas B2B</option>
                <option value="0" @selected($b2bFilter === '0')>Solo Boutique B2C</option>
            </select>

            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.fulfillment.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Orders Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Pedido / Tracking</th>
                            <th class="px-5 py-3 text-left">Cliente / Escala</th>
                            <th class="px-5 py-3 text-left">Envío</th>
                            <th class="px-5 py-3 text-left">Pago</th>
                            <th class="px-5 py-3 text-center">Estado Pedido</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Operación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($orders as $order)
                            @php $payment = $order->payments->first(); @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <p class="font-mono font-bold text-glamora-charcoal">{{ $order->order_number }}</p>
                                    <p class="font-mono text-[10px] text-glamora-muted">{{ $order->tracking_code ?? '—' }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">{{ $order->customer_name }}</p>
                                    <p class="text-[11px] text-glamora-muted">
                                        {{ $order->document_type }} {{ $order->document_number ?? '—' }} ·
                                        <span class="font-semibold {{ $order->is_b2b ? 'text-glamora-terracotta' : '' }}">{{ $order->customer_tier->label() }}</span>
                                    </p>
                                </td>
                                <td class="px-5 py-3.5 text-glamora-muted">
                                    <span class="block font-medium text-glamora-charcoal">{{ $order->shipping_district }}, {{ $order->shipping_city }}</span>
                                    <span class="text-[11px]">{{ $order->shipping_method->label() }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($payment)
                                        <span class="block font-medium text-glamora-charcoal">{{ $payment->payment_method->label() }}</span>
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                            'bg-emerald-50 text-emerald-700' => $payment->status === 'verified',
                                            'bg-amber-50 text-amber-800' => $payment->status === 'pending',
                                            'bg-rose-50 text-rose-700' => in_array($payment->status, ['rejected', 'refunded'], true),
                                        ])>
                                            {{ $payment->status }}
                                        </span>
                                    @else
                                        <span class="text-glamora-muted">Sin pago</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $order->status->badgeClasses() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $order->total, 2) }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('admin.fulfillment.orders.show', $order) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream">
                                            Detalle
                                        </a>
                                        @if ($isOpsStaff)
                                            <a href="{{ route('admin.fulfillment.orders.dispatch-guide-pdf', $order) }}" class="rounded-full border border-glamora-gold/60 bg-glamora-gold/15 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-gold/30">
                                                Guía PDF
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron pedidos con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
