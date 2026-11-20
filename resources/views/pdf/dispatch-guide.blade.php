<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guía de Picking FEFO {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 26px 32px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #2B2523;
            line-height: 1.45;
            margin: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #D4AF37;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #2B2523;
        }
        .brand-sub {
            font-size: 8.5px;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: #C87D72;
        }
        .guide-badge {
            background-color: #2B2523;
            color: #FAF6F2;
            padding: 8px 12px;
            border-radius: 5px;
            text-align: right;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta-box {
            background-color: #FAF6F2;
            border: 1px solid #E8B4A2;
            border-radius: 5px;
            padding: 9px 11px;
            vertical-align: top;
        }
        .meta-heading {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            color: #C87D72;
            margin-bottom: 4px;
        }
        .picking-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .picking-table th {
            background-color: #2B2523;
            color: #FAF6F2;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            text-align: left;
            padding: 7px 7px;
        }
        .picking-table td {
            border-bottom: 1px solid #EFE4DC;
            padding: 8px 7px;
            vertical-align: top;
        }
        .check-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.5px solid #2B2523;
            border-radius: 2px;
        }
        .swatch-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 1px solid #888;
            margin-right: 4px;
            vertical-align: middle;
        }
        .batch-block {
            background-color: #FAF6F2;
            border: 1px solid #D4AF37;
            border-radius: 4px;
            padding: 3px 6px;
            margin-bottom: 3px;
            font-size: 8.5px;
        }
        .location-pill {
            display: inline-block;
            background-color: #2B2523;
            color: #FAF6F2;
            border-radius: 3px;
            padding: 1px 5px;
            font-family: monospace;
            font-size: 8px;
            font-weight: bold;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        .sign-cell {
            width: 31%;
            border: 1px solid #E8B4A2;
            background-color: #FAF6F2;
            padding: 10px;
            vertical-align: top;
            font-size: 8.5px;
        }
        .sign-line {
            margin-top: 26px;
            border-top: 1px dashed #6E635F;
            padding-top: 4px;
            color: #6E635F;
        }
        .footer-note {
            margin-top: 16px;
            border-top: 1px solid #E8B4A2;
            padding-top: 8px;
            font-size: 8px;
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
        $storePhone = trim((string) ($pdfSettings['store_phone'] ?? '')) ?: '+51 917 328 085';
        $storeAddress = trim((string) ($pdfSettings['store_address'] ?? '')) ?: 'Almacén Central: Lima Metropolitana, Perú (Envíos a todo el país vía Olva y Shalom)';
    @endphp
    <table class="header-table">
        <tr>
            <td style="width: 56%; vertical-align: middle;">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="padding-right: 10px; vertical-align: middle;">
                            <svg width="36" height="36" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="20" cy="20" r="19" fill="#2B2523" stroke="#D4AF37" stroke-width="1.5"/>
                                <text x="20" y="26" text-anchor="middle" font-size="18" font-weight="bold" fill="#D4AF37">G</text>
                            </svg>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="brand-title">GLAMORA</div>
                            <div class="brand-sub">Guía Operativa de Picking &amp; Despacho FEFO</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 44%; vertical-align: middle;">
                <div class="guide-badge">
                    <div style="font-size: 8px; text-transform: uppercase; letter-spacing: 1.2px; color: #D4AF37;">
                        Guía Almacén · {{ $order->is_b2b ? 'B2B Mayorista' : 'Boutique' }} · {{ $order->status->label() }}
                    </div>
                    <div style="font-size: 13px; font-weight: bold; margin-top: 2px;">{{ $order->order_number }}</div>
                    <div style="font-size: 8px; color: #E8B4A2; margin-top: 2px;">
                        Tracking: {{ $order->tracking_code ?? 'Pendiente' }} · Emitida {{ now()->format('d/m/Y H:i') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td class="meta-box" style="width: 49%;">
                <div class="meta-heading">Destinatario &amp; Comprobante</div>
                <strong>{{ $order->customer_name }}</strong><br>
                {{ $order->document_type }}: {{ $order->document_number ?? '—' }}<br>
                Tel.: {{ $order->customer_phone ?? '—' }} · {{ $order->customer_email }}<br>
                Nivel: <strong>{{ $order->customer_tier->label() }}</strong>
            </td>
            <td style="width: 2%;"></td>
            <td class="meta-box" style="width: 49%;">
                <div class="meta-heading">Ruta de Despacho</div>
                {{ $order->shipping_address }}<br>
                {{ $order->shipping_district }}, {{ $order->shipping_city }}<br>
                Servicio: <strong>{{ $order->shipping_method->label() }}</strong><br>
                Ventana: {{ $order->shipping_method->estimatedWindow() }}
            </td>
        </tr>
    </table>

    <table class="picking-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">OK</th>
                <th style="width: 14%;">SKU</th>
                <th style="width: 28%;">Producto &amp; Tono</th>
                <th style="width: 7%; text-align: center;">Cant.</th>
                <th style="width: 46%;">Lotes FEFO Asignados &amp; Ubicación Física</th>
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
                    <td style="text-align: center; padding-top: 10px;">
                        <span class="check-box"></span>
                    </td>
                    <td style="font-family: monospace; font-size: 9px; font-weight: bold;">{{ $item->sku }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong><br>
                        <span style="color: #6E635F; font-size: 9px;">
                            <span class="swatch-dot" style="background-color: {{ $safeHex }};"></span>
                            {{ $item->variant_name }}
                        </span>
                    </td>
                    <td style="text-align: center; font-size: 11px; font-weight: bold;">
                        {{ $item->quantity }}
                    </td>
                    <td>
                        @if (count($allocations) > 0)
                            @foreach ($allocations as $alloc)
                                @php
                                    $bModel = isset($alloc['batch_id']) ? $batchLookup->get((int) $alloc['batch_id']) : null;
                                    $loc = $bModel?->warehouseLocation;
                                @endphp
                                <div class="batch-block">
                                    <strong>Lote: {{ $alloc['batch_number'] ?? $bModel?->batch_number ?? 'FEFO' }}</strong>
                                    · Retirar: <strong>{{ $alloc['quantity'] ?? $item->quantity }} u.</strong>
                                    · Vence: {{ $alloc['expiration_date'] ?? $bModel?->expiration_date?->format('Y-m-d') ?? '—' }}<br>
                                    @if ($loc)
                                        <span class="location-pill">{{ $loc->code }}</span>
                                        Pasillo {{ $loc->aisle }} · Rack {{ $loc->rack }} · Nivel {{ $loc->shelf }}
                                        @if ($loc->temperature_controlled)
                                            · <strong>[CLIMATIZADA]</strong>
                                        @endif
                                    @else
                                        <span>Ubicación por confirmar</span>
                                    @endif
                                </div>
                            @endforeach
                        @elseif ($item->batch)
                            <div class="batch-block">
                                <strong>Lote: {{ $item->batch->batch_number }}</strong>
                                · Retirar: <strong>{{ $item->quantity }} u.</strong>
                                · Vence: {{ $item->batch->expiration_date?->format('Y-m-d') ?? '—' }}<br>
                                @if ($item->batch->warehouseLocation)
                                    <span class="location-pill">{{ $item->batch->warehouseLocation->code }}</span>
                                    Pasillo {{ $item->batch->warehouseLocation->aisle }} · Rack {{ $item->batch->warehouseLocation->rack }} · Nivel {{ $item->batch->warehouseLocation->shelf }}
                                @endif
                            </div>
                        @else
                            <span style="color: #B45309; font-weight: bold;">Sin asignación FEFO previa (se asignará al despachar)</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($order->notes)
        <div class="meta-box" style="margin-bottom: 12px;">
            <div class="meta-heading">Instrucciones &amp; Notas de Almacén</div>
            <div>{{ $order->notes }}</div>
        </div>
    @endif

    <table class="sign-table">
        <tr>
            <td class="sign-cell">
                <strong>1. Picking FEFO (Almacén)</strong>
                <div class="sign-line">Firma / Fecha y Hora</div>
            </td>
            <td style="width: 3.5%;"></td>
            <td class="sign-cell">
                <strong>2. Packing &amp; Precinto Sanitario</strong>
                <div class="sign-line">Firma / Nro. Bultos</div>
            </td>
            <td style="width: 3.5%;"></td>
            <td class="sign-cell">
                <strong>3. Despacho / Courier</strong>
                <div class="sign-line">Firma / DNI Transportista</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Glamora Perú S.A.C. ({{ $storeName }}) · RUC {{ $storeRuc }} · {{ $storeAddress }} · Tel. {{ $storePhone }}<br>
        Trazabilidad FEFO &amp; Control Sanitario DIGEMID · Documento interno de alistamiento para {{ $order->order_number }}.
    </div>
</body>
</html>
