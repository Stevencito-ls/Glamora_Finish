<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Tarjeta = 'tarjeta';
    case YapePlin = 'yape_plin';
    case Transferencia = 'transferencia';
    case Cripto = 'cripto';
    case CreditoB2b = 'credito_b2b';

    public function label(): string
    {
        return match ($this) {
            self::Tarjeta => 'Tarjeta de crédito o débito',
            self::YapePlin => 'Yape / Plin',
            self::Transferencia => 'Transferencia bancaria (BCP / Interbank)',
            self::Cripto => 'Criptomonedas (USDT / USDC)',
            self::CreditoB2b => 'Crédito B2B aprobado',
        };
    }

    public function requiresVoucher(): bool
    {
        return match ($this) {
            self::YapePlin, self::Transferencia, self::Cripto => true,
            self::Tarjeta, self::CreditoB2b => false,
        };
    }

    public function instructions(): string
    {
        return match ($this) {
            self::Tarjeta => 'Pago procesado en línea con validación 3D Secure y confirmación automática.',
            self::YapePlin => 'Escanea el QR o yapea al 987 654 321 (Glamora Perú S.A.C.) y adjunta la captura del comprobante.',
            self::Transferencia => 'Transfiere a la cuenta corriente BCP Soles 193-9948120-0-14 (CCI 002-193-009948120014-18) y sube el voucher.',
            self::Cripto => 'Envía el equivalente en USDT o USDC por red TRC20 / Polygon e ingresa el hash (TxID) de confirmación.',
            self::CreditoB2b => 'Cargo directo a tu línea de crédito mayorista Glamora B2B con vencimiento a 30 días.',
        };
    }

    public function isInstant(): bool
    {
        return match ($this) {
            self::Tarjeta, self::CreditoB2b => true,
            self::YapePlin, self::Transferencia, self::Cripto => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
