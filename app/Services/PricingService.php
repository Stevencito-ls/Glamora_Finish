<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerTier;
use App\Enums\ShippingMethod;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\User;
use App\ValueObjects\MoneyAmount;
use App\ValueObjects\PriceCalculation;
use Illuminate\Support\Facades\Auth;

class PricingService
{
    public const float TAX_RATE_IGV = 0.18;

    public const string DEFAULT_CURRENCY = 'PEN';

    public function __construct(
        private readonly ?ShippingService $shippingService = null,
    ) {}

    public function taxRate(): float
    {
        return self::TAX_RATE_IGV;
    }

    public function resolveActiveTier(?User $user = null): CustomerTier
    {
        $target = $user ?? Auth::user();

        if ($target instanceof User) {
            return $target->effectiveTier();
        }

        return CustomerTier::Minorista;
    }

    public function unitPriceFor(ProductVariant $variant, int $quantity = 1, ?CustomerTier $tier = null): float
    {
        $effectiveTier = $tier ?? $this->resolveActiveTier();

        return $variant->priceForTier($effectiveTier, max(1, $quantity));
    }

    public function calculateVariant(
        ProductVariant $variant,
        int $quantity = 1,
        ?CustomerTier $tier = null,
        ?Coupon $coupon = null,
        float|int|string $shippingCost = 0.0,
    ): PriceCalculation {
        $qty = max(1, $quantity);
        $effectiveTier = $tier ?? $this->resolveActiveTier();
        $baseUnitPrice = (float) $variant->base_price;
        $effectiveUnitPrice = $this->unitPriceFor($variant, $qty, $effectiveTier);
        $lineSubtotal = round($effectiveUnitPrice * $qty, 2);

        $couponDiscount = 0.0;
        if ($coupon instanceof Coupon && $coupon->isValidFor($lineSubtotal, $effectiveTier)) {
            $couponDiscount = $coupon->calculateDiscount($lineSubtotal);
        }

        return PriceCalculation::forUnitPrice(
            baseUnitPrice: $baseUnitPrice,
            effectiveUnitPrice: $effectiveUnitPrice,
            quantity: $qty,
            tier: $effectiveTier,
            couponDiscount: $couponDiscount,
            shippingCost: $shippingCost,
            taxIncluded: true,
            currency: self::DEFAULT_CURRENCY,
            taxRate: $this->taxRate(),
        );
    }

    /**
     * @return array{valid: bool, coupon: ?Coupon, discount: float, message: string}
     */
    public function validateCoupon(string $code, float $subtotal, ?CustomerTier $tier = null): array
    {
        $normalized = strtoupper(trim($code));
        $effectiveTier = $tier ?? $this->resolveActiveTier();

        if ($normalized === '') {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => 0.0,
                'message' => 'Ingresa un código promocional válido.',
            ];
        }

        $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [$normalized])->first();

        if (! $coupon instanceof Coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => 0.0,
                'message' => "El cupón «{$normalized}» no existe.",
            ];
        }

        if (! $coupon->is_active) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => "El cupón {$coupon->code} se encuentra inactivo.",
            ];
        }

        $now = now();
        if ($coupon->starts_at !== null && $coupon->starts_at->isAfter($now)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => "El cupón {$coupon->code} aún no inicia su vigencia.",
            ];
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isBefore($now)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => "El cupón {$coupon->code} ha expirado.",
            ];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => "El cupón {$coupon->code} alcanzó su límite máximo de usos.",
            ];
        }

        if ($coupon->applicable_tier !== null && $coupon->applicable_tier !== $effectiveTier) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => "El cupón {$coupon->code} es exclusivo para el nivel {$coupon->applicable_tier->label()}.",
            ];
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            $minFormatted = number_format((float) $coupon->min_order_amount, 2);

            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => "El cupón {$coupon->code} requiere una compra mínima de S/ {$minFormatted}.",
            ];
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => "Cupón {$coupon->code} aplicado (-S/ " . number_format($discount, 2) . ').',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     tier: CustomerTier,
     *     items_count: int,
     *     list_subtotal: float,
     *     subtotal: float,
     *     tier_savings: float,
     *     coupon: ?Coupon,
     *     coupon_code: ?string,
     *     coupon_discount: float,
     *     coupon_warning: ?string,
     *     net_subtotal: float,
     *     taxable_base: float,
     *     tax_igv: float,
     *     shipping_method: ShippingMethod,
     *     shipping_quote: \App\ValueObjects\ShippingQuote,
     *     shipping_cost: float,
     *     total: float,
     *     price_calculation: PriceCalculation
     * }
     */
    public function calculateCartTotals(
        array $items,
        ?CustomerTier $tier = null,
        ?string $couponCode = null,
        ?ShippingMethod $shippingMethod = null,
    ): array {
        $effectiveTier = $tier ?? $this->resolveActiveTier();
        $method = $shippingMethod ?? ShippingMethod::Estandar;
        $shipping = $this->shippingService ?? new ShippingService();

        $itemsCount = 0;
        $listCents = 0;
        $subtotalCents = 0;

        foreach ($items as $item) {
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            if ($qty === 0) {
                continue;
            }

            $baseUnit = (float) ($item['base_price'] ?? $item['unit_price'] ?? 0.0);
            $effectiveUnit = (float) ($item['unit_price'] ?? $baseUnit);

            $itemsCount += $qty;
            $listCents += MoneyAmount::fromFloat($baseUnit, self::DEFAULT_CURRENCY)->multiply($qty)->cents;
            $subtotalCents += MoneyAmount::fromFloat($effectiveUnit, self::DEFAULT_CURRENCY)->multiply($qty)->cents;
        }

        $listSubtotal = round($listCents / 100, 2);
        $subtotal = round($subtotalCents / 100, 2);

        $coupon = null;
        $appliedCode = null;
        $couponDiscount = 0.0;
        $couponWarning = null;

        if ($itemsCount > 0 && is_string($couponCode) && trim($couponCode) !== '') {
            $couponResult = $this->validateCoupon($couponCode, $subtotal, $effectiveTier);
            if ($couponResult['valid'] && $couponResult['coupon'] instanceof Coupon) {
                $coupon = $couponResult['coupon'];
                $appliedCode = $coupon->code;
                $couponDiscount = $couponResult['discount'];
            } else {
                $couponWarning = $couponResult['message'];
            }
        }

        $netSubtotal = max(0.0, round($subtotal - $couponDiscount, 2));
        $shippingQuote = $shipping->quote($method, $netSubtotal);
        $shippingCost = $itemsCount > 0 ? $shippingQuote->cost->amount : 0.0;

        $discountPercent = $listSubtotal > 0
            ? max(0.0, (($listSubtotal - $subtotal) / $listSubtotal) * 100)
            : 0.0;

        $priceCalculation = new PriceCalculation(
            quantity: 1,
            baseUnitPrice: MoneyAmount::fromFloat($listSubtotal, self::DEFAULT_CURRENCY),
            effectiveUnitPrice: MoneyAmount::fromFloat($subtotal, self::DEFAULT_CURRENCY),
            tier: $effectiveTier,
            discountPercent: $discountPercent,
            couponDiscount: MoneyAmount::fromFloat($couponDiscount, self::DEFAULT_CURRENCY),
            shippingCost: MoneyAmount::fromFloat($shippingCost, self::DEFAULT_CURRENCY),
            taxRate: $this->taxRate(),
            taxIncluded: true,
        );

        return [
            'tier' => $effectiveTier,
            'items_count' => $itemsCount,
            'list_subtotal' => $priceCalculation->listSubtotal->amount,
            'subtotal' => $priceCalculation->subtotal->amount,
            'tier_savings' => $priceCalculation->tierSavings->amount,
            'coupon' => $coupon,
            'coupon_code' => $appliedCode,
            'coupon_discount' => $priceCalculation->couponDiscount->amount,
            'coupon_warning' => $couponWarning,
            'net_subtotal' => $priceCalculation->netSubtotal->amount,
            'taxable_base' => $priceCalculation->taxableBase->amount,
            'tax_igv' => $priceCalculation->taxIgv->amount,
            'shipping_method' => $method,
            'shipping_quote' => $shippingQuote,
            'shipping_cost' => $shippingCost,
            'total' => $itemsCount > 0 ? $priceCalculation->grandTotal->amount : 0.0,
            'price_calculation' => $priceCalculation,
        ];
    }
}
