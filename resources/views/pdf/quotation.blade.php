<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización Glamora Perú {{ $quoteNumber }}</title>
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
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #2B2523;
            margin: 0;
        }
        .brand-sub {
            font-size: 8.5px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #C87D72;
            margin-top: 2px;
        }
        .doc-badge {
            background-color: #2B2523;
            color: #FAF6F2;
            padding: 8px 14px;
            border-radius: 6px;
            text-align: right;
        }
        .doc-badge-title {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #D4AF37;
        }
        .doc-badge-number {
            font-size: 13px;
            font-weight: bold;
            margin-top: 2px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
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
            margin: 0 0 5px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table th {
            background-color: #2B2523;
            color: #FAF6F2;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            padding: 8px 9px;
        }
        .items-table td {
            border-bottom: 1px solid #EFE4DC;
            padding: 8px 9px;
            vertical-align: middle;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .swatch-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1px solid #888;
            margin-right: 4px;
            vertical-align: middle;
        }
        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
        }
        .notes-box {
            background-color: #FAF6F2;
            border-left: 3px solid #D4AF37;
            padding: 10px 12px;
            font-size: 9.5px;
            color: #6E635F;
            vertical-align: top;
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
        .footer-note {
            margin-top: 24px;
            border-top: 1px solid #E8B4A2;
            padding-top: 10px;
            font-size: 8.5px;
            color: #6E635F;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%; vertical-align: middle;">
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
                            <div class="brand-sub">Perú · Alta Cosmética Botánica &amp; Distribución B2B</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 40%; vertical-align: middle;">
                <div class="doc-badge">
                    <div class="doc-badge-title">Cotización Comercial</div>
                    <div class="doc-badge-number">{{ $quoteNumber }}</div>
                    <div style="font-size: 8.5px; color: #E8B4A2; margin-top: 2px;">
                        Emitida: {{ $issuedAt->format('d/m/Y H:i') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td class="meta-box" style="width: 49%;">
                <div class="meta-heading">Emisor &amp; Respaldo Sanitario</div>
                <strong>Glamora Perú S.A.C.</strong> · RUC 20608841920<br>
                Av. Los Frutales 412, Ate · Showroom Miraflores, Lima<br>
                ventas@glamora.pe · +51 987 654 321<br>
                Control FEFO por lote &amp; Registro NSOC DIGEMID
            </td>
            <td style="width: 2%;"></td>
            <td class="meta-box" style="width: 49%;">
                <div class="meta-heading">Destinatario &amp; Vigencia</div>
                @if ($customer)
                    <strong>{{ $customer->name }}</strong><br>
                    {{ $customer->email }} · {{ $customer->phone ?? 'Sin teléfono' }}<br>
                    {{ $customer->document_type ?? 'DOC' }}: {{ $customer->document_number ?? 'Por definir' }}<br>
                @else
                    <strong>Cliente Visitante / Prospecto Comercial</strong><br>
                    Cotización generada desde carrito web<br>
                @endif
                Escala aplicada: <strong>{{ $summary['tier']->label() }}</strong> · Válida hasta: <strong>{{ $validUntil->format('d/m/Y') }}</strong>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 14%;">SKU</th>
                <th style="width: 44%;">Producto &amp; Tono</th>
                <th class="text-center" style="width: 10%;">Cant.</th>
                <th class="text-right" style="width: 16%;">Precio Unit.</th>
                <th class="text-right" style="width: 16%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['items'] as $item)
                @php
                    $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item['hex_color'] ?? '')) === 1
                        ? (string) $item['hex_color']
                        : '#C87D72';
                @endphp
                <tr>
                    <td style="font-family: monospace; font-size: 9px;">{{ $item['sku'] }}</td>
                    <td>
                        <strong>{{ $item['product_name'] }}</strong><br>
                        <span style="color: #6E635F; font-size: 9.5px;">
                            <span class="swatch-dot" style="background-color: {{ $safeHex }};"></span>
                            {{ $item['shade_name'] }} · {{ $item['brand_name'] ?? 'Glamora' }}
                        </span>
                    </td>
                    <td class="text-center"><strong>{{ (int) $item['quantity'] }}</strong></td>
                    <td class="text-right">
                        S/ {{ number_format((float) $item['unit_price'], 2) }}
                        @if ((float) ($item['base_price'] ?? 0) > (float) $item['unit_price'])
                            <br><span style="font-size: 8.5px; color: #6E635F; text-decoration: line-through;">S/ {{ number_format((float) $item['base_price'], 2) }}</span>
                        @endif
                    </td>
                    <td class="text-right"><strong>S/ {{ number_format((float) $item['subtotal'], 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td style="width: 54%; vertical-align: top; padding-right: 16px;">
                <div class="notes-box">
                    <strong style="color: #2B2523;">Condiciones de Cotización:</strong><br>
                    1. Todos los precios están expresados en Soles (S/) e incluyen IGV (18%).<br>
                    2. Stock sujeto a disponibilidad FEFO al momento de confirmar el pedido en checkout.<br>
                    3. Envío Estándar gratuito en pedidos desde S/ {{ number_format((float) $summary['free_shipping_threshold'], 2) }}.<br>
                    @if ($summary['coupon_code'])
                        4. Cupón promocional pre-aplicado: <strong>{{ $summary['coupon_code'] }}</strong>.
                    @endif
                </div>
            </td>
            <td style="width: 46%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td>Valor lista catálogo</td>
                        <td class="text-right">S/ {{ number_format((float) $summary['list_subtotal'], 2) }}</td>
                    </tr>
                    @if ((float) $summary['tier_savings'] > 0)
                        <tr>
                            <td style="color: #C87D72;">Ahorro escala / volumen</td>
                            <td class="text-right" style="color: #C87D72;">-S/ {{ number_format((float) $summary['tier_savings'], 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Subtotal productos</td>
                        <td class="text-right">S/ {{ number_format((float) $summary['subtotal'], 2) }}</td>
                    </tr>
                    @if ((float) $summary['coupon_discount'] > 0)
                        <tr>
                            <td style="color: #2E7D32;">Descuento cupón ({{ $summary['coupon_code'] }})</td>
                            <td class="text-right" style="color: #2E7D32;">-S/ {{ number_format((float) $summary['coupon_discount'], 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Op. Gravada (Base Imponible)</td>
                        <td class="text-right">S/ {{ number_format((float) $summary['taxable_base'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>IGV (18% incluido)</td>
                        <td class="text-right">S/ {{ number_format((float) $summary['tax_igv'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Envío estimado ({{ $summary['shipping_method']->label() }})</td>
                        <td class="text-right">
                            {{ (float) $summary['shipping_cost'] === 0.0 ? 'GRATIS' : 'S/ ' . number_format((float) $summary['shipping_cost'], 2) }}
                        </td>
                    </tr>
                    <tr class="totals-grand">
                        <td>TOTAL COTIZADO</td>
                        <td class="text-right">S/ {{ number_format((float) $summary['total'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Glamora Perú S.A.C. · Documento informativo sin valor tributario SUNAT · Precios e inventario trazable FEFO sujetos a confirmación en checkout.
    </div>
</body>
</html>
