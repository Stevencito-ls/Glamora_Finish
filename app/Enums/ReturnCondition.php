<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnCondition: string
{
    case Reventa = 'reventa';
    case Reparacion = 'reparacion';
    case Desecho = 'desecho';

    public function label(): string
    {
        return match ($this) {
            self::Reventa => 'Apto para reventa',
            self::Reparacion => 'Requiere reparación / reempaque',
            self::Desecho => 'Desecho / merma sanitaria',
        };
    }

    public function shouldRestock(): bool
    {
        return $this === self::Reventa;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Reventa => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::Reparacion => 'bg-amber-50 text-amber-700 border-amber-200',
            self::Desecho => 'bg-rose-50 text-rose-700 border-rose-200',
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
