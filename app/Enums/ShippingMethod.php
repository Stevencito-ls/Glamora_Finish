<?php

declare(strict_types=1);

namespace App\Enums;

enum ShippingMethod: string
{
    case Estandar = 'estandar';
    case Express = 'express';
    case SameDay = 'same_day';

    public function label(): string
    {
        return match ($this) {
            self::Estandar => 'Envío Estándar (Lima y Provincias)',
            self::Express => 'Envío Express Prioritario',
            self::SameDay => 'Entrega Same Day (Lima Metropolitana)',
        };
    }

    public function baseCost(): float
    {
        return match ($this) {
            self::Estandar => 12.0,
            self::Express => 22.0,
            self::SameDay => 32.0,
        };
    }

    public function estimatedWindow(): string
    {
        return match ($this) {
            self::Estandar => '24 a 72 horas hábiles',
            self::Express => 'Hasta 24 horas hábiles',
            self::SameDay => 'Mismo día (pedidos confirmados antes de 2:00 p.m.)',
        };
    }

    public function freeShippingEligible(): bool
    {
        return $this === self::Estandar;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
