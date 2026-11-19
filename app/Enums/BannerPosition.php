<?php

declare(strict_types=1);

namespace App\Enums;

enum BannerPosition: string
{
    case HeroPrincipal = 'hero_principal';
    case PromoSecundario = 'promo_secundario';
    case PortalMayorista = 'portal_mayorista';

    public function label(): string
    {
        return match ($this) {
            self::HeroPrincipal => 'Hero Principal (Home)',
            self::PromoSecundario => 'Banner Promocional Secundario',
            self::PortalMayorista => 'Portal Mayorista B2B',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::HeroPrincipal => 'bg-rose-50 text-rose-800 border-rose-200',
            self::PromoSecundario => 'bg-amber-50 text-amber-800 border-amber-200',
            self::PortalMayorista => 'bg-stone-100 text-stone-800 border-stone-300',
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
