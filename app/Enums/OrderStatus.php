<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Cotizacion = 'cotizacion';
    case PendientePago = 'pendiente_pago';
    case PagoVerificado = 'pago_verificado';
    case EnPicking = 'en_picking';
    case EnPacking = 'en_packing';
    case Despachado = 'despachado';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Cotizacion => 'Cotización',
            self::PendientePago => 'Pendiente de pago',
            self::PagoVerificado => 'Pago verificado',
            self::EnPicking => 'En picking',
            self::EnPacking => 'En packing',
            self::Despachado => 'Despachado',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Cotizacion => 'bg-slate-100 text-slate-700 border-slate-200',
            self::PendientePago => 'bg-amber-50 text-amber-700 border-amber-200',
            self::PagoVerificado => 'bg-sky-50 text-sky-700 border-sky-200',
            self::EnPicking => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::EnPacking => 'bg-violet-50 text-violet-700 border-violet-200',
            self::Despachado => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::Entregado => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::Cancelado => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    public function step(): int
    {
        return match ($this) {
            self::Cotizacion => 1,
            self::PendientePago => 2,
            self::PagoVerificado => 3,
            self::EnPicking => 4,
            self::EnPacking => 5,
            self::Despachado => 6,
            self::Entregado => 7,
            self::Cancelado => 0,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Cotizacion => in_array($next, [self::PendientePago, self::Cancelado], true),
            self::PendientePago => in_array($next, [self::PagoVerificado, self::Cancelado], true),
            self::PagoVerificado => in_array($next, [self::EnPicking, self::Cancelado], true),
            self::EnPicking => in_array($next, [self::EnPacking, self::Cancelado], true),
            self::EnPacking => in_array($next, [self::Despachado, self::Cancelado], true),
            self::Despachado => $next === self::Entregado,
            self::Entregado, self::Cancelado => false,
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
