<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu bolsa Glamora Perú te espera</title>
</head>
<body style="margin: 0; padding: 0; background-color: #FAF6F2; font-family: 'Helvetica Neue', Arial, sans-serif; color: #2B2523; line-height: 1.55;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #FAF6F2; padding: 28px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #FFFFFF; border: 1px solid #E8B4A2; border-radius: 16px; overflow: hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #2B2523; padding: 26px 28px; border-bottom: 3px solid #D4AF37;">
                            <p style="margin: 0; font-family: Georgia, serif; font-size: 24px; letter-spacing: 5px; font-weight: 700; color: #FAF6F2;">
                                GLAMORA
                            </p>
                            <p style="margin: 4px 0 0 0; font-size: 10px; letter-spacing: 2.2px; text-transform: uppercase; color: #E8B4A2;">
                                Alta Cosmética Botánica · Trazabilidad FEFO &amp; DIGEMID
                            </p>
                        </td>
                    </tr>

                    {{-- Greeting & Intro --}}
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <span style="display: inline-block; background-color: #FAF6F2; border: 1px solid #D4AF37; color: #2B2523; font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 12px; border-radius: 999px;">
                                Recordatorio de Bolsa Guardada
                            </span>

                            <h1 style="margin: 14px 0 10px 0; font-family: Georgia, serif; font-size: 26px; line-height: 1.2; color: #2B2523;">
                                Hola, {{ $cart->user?->name ?: 'amiga de Glamora' }}
                            </h1>

                            <p style="margin: 0; font-size: 14px; color: #6E635F;">
                                Guardamos la selección cosmética que dejaste pendiente en tu bolsa. Nuestros lotes vigentes cuentan con registro sanitario DIGEMID y rotación FEFO garantizada.
                            </p>
                        </td>
                    </tr>

                    {{-- Cart Items --}}
                    <tr>
                        <td style="padding: 8px 28px 20px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border: 1px solid #E8B4A2; border-radius: 10px; overflow: hidden;">
                                <thead>
                                    <tr style="background-color: #FAF6F2;">
                                        <th align="left" style="padding: 10px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6E635F; border-bottom: 1px solid #E8B4A2;">
                                            Producto &amp; Tono
                                        </th>
                                        <th align="center" style="padding: 10px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6E635F; border-bottom: 1px solid #E8B4A2;">
                                            Cant.
                                        </th>
                                        <th align="right" style="padding: 10px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6E635F; border-bottom: 1px solid #E8B4A2;">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        <tr>
                                            <td style="padding: 12px; border-bottom: 1px solid #F3E5DC; font-size: 13px; color: #2B2523;">
                                                <strong style="display: block;">{{ $item['product_name'] ?? 'Producto Glamora' }}</strong>
                                                @if (! empty($item['shade_name']))
                                                    <span style="display: block; font-size: 11px; color: #6E635F;">
                                                        Tono: {{ $item['shade_name'] }}
                                                        @if (! empty($item['sku']))
                                                            · SKU {{ $item['sku'] }}
                                                        @endif
                                                    </span>
                                                @endif
                                            </td>
                                            <td align="center" style="padding: 12px; border-bottom: 1px solid #F3E5DC; font-size: 13px; font-weight: 600; color: #2B2523;">
                                                {{ (int) ($item['quantity'] ?? 1) }}
                                            </td>
                                            <td align="right" style="padding: 12px; border-bottom: 1px solid #F3E5DC; font-size: 13px; font-weight: 700; color: #2B2523;">
                                                S/ {{ number_format((float) ($item['subtotal'] ?? ((float) ($item['unit_price'] ?? 0) * (int) ($item['quantity'] ?? 1))), 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" style="padding: 14px; text-align: center; font-size: 13px; color: #6E635F;">
                                                Tu selección cosmética está reservada en línea para retomar tu pedido.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #FAF6F2;">
                                        <td colspan="2" align="right" style="padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #6E635F;">
                                            Total estimado (incl. IGV)
                                        </td>
                                        <td align="right" style="padding: 12px; font-family: Georgia, serif; font-size: 18px; font-weight: 700; color: #C87D72;">
                                            S/ {{ number_format((float) $cart->subtotal, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td align="center" style="padding: 4px 28px 28px 28px;">
                            <a
                                href="{{ $cartUrl }}"
                                style="display: inline-block; background-color: #2B2523; color: #FAF6F2; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 14px 28px; border-radius: 999px;"
                            >
                                Retomar mi Bolsa en Glamora &rarr;
                            </a>
                            <p style="margin: 14px 0 0 0; font-size: 12px; color: #6E635F;">
                                ¿Prefieres explorar nuevos tonos? <a href="{{ $catalogUrl }}" style="color: #C87D72; font-weight: 600; text-decoration: underline;">Ir al catálogo completo</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #FAF6F2; border-top: 1px solid #E8B4A2; padding: 18px 28px; font-size: 11px; color: #6E635F; text-align: center;">
                            <p style="margin: 0;">
                                <strong style="color: #2B2523;">Glamora Perú S.A.C.</strong> · Envíos Same Day Lima y cobertura nacional.
                            </p>
                            <p style="margin: 4px 0 0 0;">
                                Pagos aceptados: Tarjeta 3DS, Yape/Plin, Transferencia, Cripto y Crédito B2B.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
