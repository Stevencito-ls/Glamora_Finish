<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @php
        $sanitizeHex = static fn (?string $hex, string $fallback): string => preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $hex) === 1
            ? (string) $hex
            : $fallback;

        $cCream = $sanitizeHex($theme['cream'] ?? null, '#FAF6F2');
        $cCharcoal = $sanitizeHex($theme['charcoal'] ?? null, '#2B2523');
        $cTerracotta = $sanitizeHex($theme['terracotta'] ?? null, '#C87D72');
        $cBlush = $sanitizeHex($theme['blush'] ?? null, '#E8B4A2');
        $cGold = $sanitizeHex($theme['gold'] ?? null, '#D4AF37');
        $cMuted = $sanitizeHex($theme['muted'] ?? null, '#6E635F');

        $storeName = trim((string) ($settings['store_name'] ?? '')) ?: 'Glamora Perú';
        $storeTagline = trim((string) ($settings['store_tagline'] ?? '')) ?: 'Alta Cosmética Botánica & Distribución B2B';
        $storeRuc = trim((string) ($settings['store_ruc'] ?? '')) ?: '20608945123';
        $storeEmail = trim((string) ($settings['store_email'] ?? '')) ?: 'info.glamora@gmail.com';
        $storePhone = trim((string) ($settings['store_phone'] ?? '')) ?: '+51 917 328 085';
        $storeAddress = trim((string) ($settings['store_address'] ?? '')) ?: 'Almacén Central: Lima Metropolitana, Perú (Envíos a todo el país vía Olva y Shalom)';
        $monogram = strtoupper(mb_substr(trim($storeName), 0, 1) ?: 'G');
    @endphp
    <title>Catálogo Oficial — {{ $storeName }}</title>
    <style>
        @page {
            margin: 26px 32px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: {{ $cCharcoal }};
            line-height: 1.4;
            margin: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid {{ $cGold }};
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 3px;
            color: {{ $cCharcoal }};
            margin: 0;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: {{ $cTerracotta }};
            margin-top: 2px;
        }
        .doc-badge {
            background-color: {{ $cCharcoal }};
            color: {{ $cCream }};
            padding: 8px 12px;
            border-radius: 5px;
            text-align: right;
        }
        .doc-badge-title {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: {{ $cGold }};
        }
        .doc-badge-tier {
            font-size: 11px;
            font-weight: bold;
            margin-top: 2px;
        }
        .summary-banner {
            width: 100%;
            border-collapse: collapse;
            background-color: {{ $cCream }};
            border: 1px solid {{ $cBlush }};
            margin-bottom: 14px;
        }
        .summary-banner td {
            padding: 7px 10px;
            font-size: 8.5px;
            color: {{ $cMuted }};
        }
        .product-card {
            border: 1px solid {{ $cBlush }};
            border-radius: 5px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .product-head {
            background-color: {{ $cCream }};
            padding: 8px 10px;
            border-bottom: 1px solid {{ $cBlush }};
        }
        .product-head-table {
            width: 100%;
            border-collapse: collapse;
        }
        .product-name {
            font-size: 11px;
            font-weight: bold;
            color: {{ $cCharcoal }};
        }
        .product-meta {
            font-size: 8px;
            color: {{ $cMuted }};
            margin-top: 2px;
        }
        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 4px;
        }
        .tag-gold {
            background-color: {{ $cGold }};
            color: #1A1614;
        }
        .tag-dark {
            background-color: {{ $cCharcoal }};
            color: {{ $cCream }};
        }
        .variants-table {
            width: 100%;
            border-collapse: collapse;
        }
        .variants-table th {
            background-color: #F4EFEA;
            color: {{ $cCharcoal }};
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            text-align: left;
            padding: 5px 8px;
            border-bottom: 1px solid {{ $cBlush }};
        }
        .variants-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #F1EAE4;
            font-size: 8.5px;
            vertical-align: middle;
        }
        .variants-table tr:last-child td {
            border-bottom: none;
        }
        .swatch-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: 1px solid #666;
            margin-right: 4px;
            vertical-align: middle;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer-note {
            margin-top: 16px;
            border-top: 1px solid {{ $cBlush }};
            padding-top: 8px;
            font-size: 8px;
            color: {{ $cMuted }};
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 62%; vertical-align: middle;">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="padding-right: 10px; vertical-align: middle;">
                            <svg width="36" height="36" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="20" cy="20" r="19" fill="{{ $cCharcoal }}" stroke="{{ $cGold }}" stroke-width="1.5"/>
                                <text x="20" y="26" text-anchor="middle" font-size="18" font-weight="bold" fill="{{ $cGold }}">{{ $monogram }}</text>
                            </svg>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="brand-title">{{ $storeName }}</div>
                            <div class="brand-sub">{{ $storeTagline }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 38%; vertical-align: middle;">
                <div class="doc-badge">
                    <div class="doc-badge-title">Catálogo Comercial A4</div>
                    <div class="doc-badge-tier">Escala: {{ $activeTier->label() }}</div>
                    <div style="font-size: 8px; color: {{ $cBlush }}; margin-top: 2px;">
                        Actualizado: {{ $generatedAt->format('d/m/Y H:i') }}
                        @if ($activeCategory)
                            · {{ $activeCategory->name }}
                        @elseif ($onlyB2b)
                            · Kits B2B
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="summary-banner">
        <tr>
            <td style="width: 55%;">
                <strong style="color: {{ $cCharcoal }};">Emisor:</strong> {{ $storeName }}
                @if ($storeRuc !== '')
                    · RUC {{ $storeRuc }}
                @endif
                <br>
                {{ $storeAddress }} · {{ $storeEmail }} · {{ $storePhone }}
            </td>
            <td style="width: 45%; text-align: right;">
                Precios en Soles (S/) incl. IGV ({{ number_format((float) $taxPercent, 0) }}%)<br>
                Envío estándar gratis desde <strong style="color: {{ $cCharcoal }};">S/ {{ number_format((float) $freeShippingThreshold, 2) }}</strong> · WhatsApp: <strong style="color: {{ $cCharcoal }};">+{{ $whatsappNumber }}</strong>
            </td>
        </tr>
    </table>

    @forelse ($products as $product)
        @php
            $isBestseller = in_array($product->id, $bestsellerIds, true);
        @endphp
        <div class="product-card">
            <div class="product-head">
                <table class="product-head-table">
                    <tr>
                        <td style="width: 72%;">
                            <div class="product-name">
                                {{ $product->name }}
                                @if ($isBestseller)
                                    <span class="tag tag-gold">Top Ventas</span>
                                @endif
                                @if ($product->is_b2b_kit)
                                    <span class="tag tag-dark">Kit B2B</span>
                                @endif
                            </div>
                            <div class="product-meta">
                                {{ $product->brand?->name ?? $storeName }}
                                · {{ $product->category?->name ?? 'Catálogo' }}
                                @if ($product->sanitary_registration)
                                    · NSOC: <strong>{{ $product->sanitary_registration }}</strong>
                                @endif
                                @if ($product->finish_type)
                                    · {{ $product->finish_type }}
                                @endif
                            </div>
                        </td>
                        <td style="width: 28%; text-align: right; vertical-align: middle; font-size: 8px; color: {{ $cMuted }};">
                            {{ $product->variants->count() }} tono(s) / formato(s)
                        </td>
                    </tr>
                </table>
            </div>

            <table class="variants-table">
                <thead>
                    <tr>
                        <th style="width: 17%;">SKU</th>
                        <th style="width: 34%;">Tono / Presentación</th>
                        <th style="width: 13%;">Formato</th>
                        <th class="text-right" style="width: 12%;">Precio Lista</th>
                        <th class="text-right" style="width: 14%;">Tu Escala</th>
                        <th class="text-center" style="width: 10%;">Stock FEFO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($product->variants as $variant)
                        @php
                            $safeHex = $sanitizeHex($variant->hex_color, '#C87D72');
                            $tierPrice = $variant->priceForTier($activeTier, 1);
                            $stock = $variant->availableStock();
                            $measure = $variant->volume_ml !== null
                                ? number_format((float) $variant->volume_ml, 1) . ' ml'
                                : ($variant->weight_grams !== null ? number_format((float) $variant->weight_grams, 0) . ' g' : 'Estándar');
                        @endphp
                        <tr>
                            <td style="font-family: monospace; font-size: 8px;">{{ $variant->sku }}</td>
                            <td>
                                <span class="swatch-dot" style="background-color: {{ $safeHex }};"></span>
                                <strong>{{ $variant->shade_name }}</strong>
                            </td>
                            <td style="color: {{ $cMuted }};">{{ $measure }}</td>
                            <td class="text-right">S/ {{ number_format((float) $variant->base_price, 2) }}</td>
                            <td class="text-right">
                                <strong style="color: {{ $cTerracotta }};">S/ {{ number_format($tierPrice, 2) }}</strong>
                            </td>
                            <td class="text-center">
                                {{ $stock > 0 ? $stock . ' uds.' : 'Agotado' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div style="padding: 20px; border: 1px solid {{ $cBlush }}; text-align: center; color: {{ $cMuted }};">
            No se encontraron productos activos para los filtros seleccionados.
        </div>
    @endforelse

    <div class="footer-note">
        {{ $storeName }} · {{ $products->count() }} producto(s) activo(s) · Precios referenciales sujetos a stock FEFO vigente · Pedidos vía web o WhatsApp +{{ $whatsappNumber }}.
    </div>
</body>
</html>
