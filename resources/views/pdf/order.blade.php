<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de Pedido {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 28px 34px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #2B2523;
            line-height: 1.45;
            margin: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #D4AF37;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #2B2523;
        }
        .brand-sub {
            font-size: 8.5px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #C87D72;
        }
        .order-badge {
            background-color: #2B2523;
            color: #FAF6F2;
            padding: 8px 14px;
            border-radius: 6px;
            text-align: right;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta-box {
            background-color: #FAF6F2;
            border: 1px solid #E8B4A2;
            border-radius: 6px;
            padding: 10px 12px;
            vertical-align: top;
        }
        .meta-heading {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #C87D72;
            margin: 0 0 4px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .items-table th {
            background-color: #2B2523;
            color: #FAF6F2;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            text-align: left;
            padding: 8px 8px;
        }
        .items-table td {
            border-bottom: 1px solid #EFE4DC;
            padding: 8px 8px;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .swatch-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: 1px solid #888;
            margin-right: 4px;
            vertical-align: middle;
        }
        .batch-pill {
            display: inline-block;
            background-color: #FAF6F2;
            border: 1px solid #D4AF37;
            border-radius: 4px;
            padding: 1px 5px;
            font-size: 8px;
            font-family: monospace;
            margin-top: 3px;
            margin-right: 3px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #FAF6F2;
            border: 1px solid #E8B4A2;
        }
        .totals-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #EFE4DC;
        }
        .totals-grand td {
            background-color: #2B2523;
            color: #FAF6F2;
            font-weight: bold;
            font-size: 12px;
            border-bottom: none;
            padding: 9px 10px;
        }
        .payment-box {
            background-color: #FAF6F2;
            border-left: 3px solid #C87D72;
            padding: 9px 12px;
            font-size: 9.5px;
            vertical-align: top;
        }
        .footer-note {
            margin-top: 22px;
            border-top: 1px solid #E8B4A2;
            padding-top: 10px;
            font-size: 8.5px;
            color: #6E635F;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $pdfSettings = is_array($settings ?? null) ? $settings : app(\App\Services\SettingService::class)->all();
        $storeName = trim((string) ($pdfSettings['store_name'] ?? '')) ?: 'Glamora Perú';
        $storeRuc = trim((string) ($pdfSettings['store_ruc'] ?? '')) ?: '20608945123';
        $storeEmail = trim((string) ($pdfSettings['store_email'] ?? '')) ?: 'info.glamora@gmail.com';
        $storePhone = trim((string) ($pdfSettings['store_phone'] ?? '')) ?: '+51 917 328 085';
        $storeAddress = trim((string) ($pdfSettings['store_address'] ?? '')) ?: 'Almacén Central: Lima Metropolitana, Perú (Envíos a todo el país vía Olva y Shalom)';

        $netSubtotal = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
        $taxableBase = round($netSubtotal - (float) $order->tax_igv, 2);
        $primaryPayment = $order->payments->first();
    @endphp

    <table class="header-table">
        <tr>
            <td style="width: 58%; vertical-align: middle;">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="padding-right: 10px; vertical-align: middle;">
                            <svg width="38" height="38" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="20" cy="20" r="19" fill="#2B2523" stroke="#D4AF37" stroke-width="1.5"/>
                                <text x="20" y="26" text-anchor="middle" font-size="18" font-weight="bold" fill="#D4AF37">G</text>
                            </svg>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="brand-title">GLAMORA</div>
                            <div class="brand-sub">Perú · Nota de Pedido &amp; Trazabilidad FEFO</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 42%; vertical-align: middle;">
                <div class="order-badge">
                    <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 1.4px; color: #D4AF37;">
                        Orden {{ $order->is_b2b ? 'Mayorista B2B' : 'Boutique' }} · {{ $order->status->label() }}
                    </div>
                    <div style="font-size: 13px; font-weight: bold; margin-top: 2px;">{{ $order->order_number }}</div>
                    <div style="font-size: 8.5px; color: #E8B4A2; margin-top: 2px;">
                        Tracking: {{ $order->tracking_code ?? 'Pendiente' }} · {{ $order->created_at?->format('d/m/Y H:i') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td class="meta-box" style="width: 49%;">
                <div class="meta-heading">Cliente &amp; Facturación</div>
                <strong>{{ $order->customer_name }}</strong><br>
                {{ $order->document_type }}: {{ $order->document_number ?? '—' }}<br>
                {{ $order->customer_email }} · {{ $order->customer_phone ?? '—' }}<br>
                Escala: <strong>{{ $order->customer_tier->label() }}</strong>
            </td>
            <td style="width: 2%;"></td>
            <td class="meta-box" style="width: 49%;">
                <div class="meta-heading">Despacho &amp; Entrega</div>
                {{ $order->shipping_address }}<br>
                {{ $order->shipping_district }}, {{ $order->shipping_city }}<br>
                Método: <strong>{{ $order->shipping_method->label() }}</strong><br>
                Ventana estimada: {{ $order->shipping_method->estimatedWindow() }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 14%;">SKU</th>
                <th style="width: 46%;">Producto, Tono &amp; Lotes FEFO</th>
                <th class="text-center" style="width: 9%;">Cant.</th>
                <th class="text-right" style="width: 15%;">Unitario</th>
                <th class="text-right" style="width: 16%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                @php
                    $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item->shade_hex ?? '')) === 1
                        ? (string) $item->shade_hex
                        : '#C87D72';
                    $allocations = is_array($item->batch_allocations) ? $item->batch_allocations : [];
                @endphp
                <tr>
                    <td style="font-family: monospace; font-size: 9px;">{{ $item->sku }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong><br>
                        <span style="color: #6E635F; font-size: 9.5px;">
                            <span class="swatch-dot" style="background-color: {{ $safeHex }};"></span>
                            {{ $item->variant_name }}
                        </span>
                        @if (count($allocations) > 0)
                            <div>
                                @foreach ($allocations as $alloc)
                                    <span class="batch-pill">
                                        Lote {{ $alloc['batch_number'] ?? 'FEFO' }} (x{{ $alloc['quantity'] ?? $item->quantity }}, vence {{ $alloc['expiration_date'] ?? '—' }})
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="text-center"><strong>{{ $item->quantity }}</strong></td>
                    <td class="text-right">S/ {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right"><strong>S/ {{ number_format((float) $item->subtotal, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 54%; vertical-align: top; padding-right: 16px;">
                <div class="payment-box">
                    <strong style="color: #2B2523;">Información de Pago &amp; Garantía Sanitaria:</strong><br>
                    @if ($primaryPayment)
                        Método: <strong>{{ $primaryPayment->payment_method->label() }}</strong><br>
                        Estado del pago: <strong>{{ $primaryPayment->status === 'verified' ? 'Verificado' : 'Pendiente de conciliación' }}</strong><br>
                        @if ($primaryPayment->transaction_reference)
                            Referencia: <span style="font-family: monospace;">{{ $primaryPayment->transaction_reference }}</span><br>
                        @endif
                        @if ($primaryPayment->crypto_network && $primaryPayment->crypto_tx_hash)
                            Red {{ $primaryPayment->crypto_network }} · Tx: <span style="font-family: monospace;">{{ $primaryPayment->crypto_tx_hash }}</span><br>
                        @endif
                        @if ($primaryPayment->b2b_due_date)
                            Vencimiento Crédito B2B: <strong>{{ $primaryPayment->b2b_due_date->format('d/m/Y') }}</strong><br>
                        @endif
                    @endif
                    <div style="margin-top: 5px; color: #6E635F;">
                        Todos los productos cuentan con registro sanitario NSOC DIGEMID y trazabilidad FEFO garantizada.
                    </div>
                </div>
            </td>
            <td style="width: 46%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal productos</td>
                        <td class="text-right">S/ {{ number_format((float) $order->subtotal, 2) }}</td>
                    </tr>
                    @if ((float) $order->discount_amount > 0)
                        <tr>
                            <td style="color: #2E7D32;">Descuento ({{ $order->coupon_code ?? 'Cupón' }})</td>
                            <td class="text-right" style="color: #2E7D32;">-S/ {{ number_format((float) $order->discount_amount, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Op. Gravada (Base Imponible)</td>
                        <td class="text-right">S/ {{ number_format($taxableBase, 2) }}</td>
                    </tr>
                    <tr>
                        <td>IGV (18% incluido)</td>
                        <td class="text-right">S/ {{ number_format((float) $order->tax_igv, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Costo de envío</td>
                        <td class="text-right">
                            {{ (float) $order->shipping_cost === 0.0 ? 'GRATIS' : 'S/ ' . number_format((float) $order->shipping_cost, 2) }}
                        </td>
                    </tr>
                    <tr class="totals-grand">
                        <td>TOTAL DEL PEDIDO</td>
                        <td class="text-right">S/ {{ number_format((float) $order->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Glamora Perú S.A.C. ({{ $storeName }}) · RUC {{ $storeRuc }} · {{ $storeAddress }} · {{ $storeEmail }} · {{ $storePhone }}<br>
        Consulta el estado de tu despacho en /seguimiento con el código {{ $order->order_number }}.
    </div>
</body>
</html>
