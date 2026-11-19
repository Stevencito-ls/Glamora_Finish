<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerTier: string
{
    case Minorista = 'minorista';
    case Bronce = 'bronce';
    case Plata = 'plata';
    case Oro = 'oro';

    public function label(): string
    {
        return match ($this) {
            self::Minorista => 'Cliente Minorista',
            self::Bronce => 'Mayorista Bronce',
            self::Plata => 'Mayorista Plata',
            self::Oro => 'Mayorista Oro',
        };
    }

    public function discountPercentage(): float
    {
        return match ($this) {
            self::Minorista => 0.0,
            self::Bronce => 10.0,
            self::Plata => 18.0,
            self::Oro => 25.0,
        };
    }

    public function minMonthlyOrder(): float
    {
        return match ($this) {
            self::Minorista => 0.0,
            self::Bronce => 800.0,
            self::Plata => 2500.0,
            self::Oro => 6000.0,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Minorista => 'bg-stone-100 text-stone-700 border-stone-200',
            self::Bronce => 'bg-orange-50 text-orange-800 border-orange-200',
            self::Plata => 'bg-slate-100 text-slate-700 border-slate-300',
            self::Oro => 'bg-amber-50 text-amber-800 border-amber-300',
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
