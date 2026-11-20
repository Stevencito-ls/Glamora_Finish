<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\CustomerTier;
use InvalidArgumentException;

final class PriceCalculation
{
    public private(set) int $quantity {
        set {
            if ($value < 1) {
                throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
            }

            $this->quantity = $value;
        }
    }

    public private(set) MoneyAmount $baseUnitPrice;

    public private(set) MoneyAmount $effectiveUnitPrice;

    public private(set) CustomerTier $tier;

    public private(set) float $discountPercent {
        set {
            if ($value < 0.0 || $value > 100.0) {
                throw new InvalidArgumentException('El porcentaje de descuento debe estar entre 0 y 100.');
            }

            $this->discountPercent = round($value, 2);
        }
    }

    public private(set) MoneyAmount $couponDiscount;

    public private(set) MoneyAmount $shippingCost;

    public private(set) float $taxRate {
        set {
            if ($value < 0.0 || $value > 1.0) {
                throw new InvalidArgumentException('La tasa de impuesto debe estar entre 0.0 y 1.0.');
            }

            $this->taxRate = $value;
        }
    }

    public private(set) bool $taxIncluded;

    public MoneyAmount $listSubtotal {
        get => $this->baseUnitPrice->multiply($this->quantity);
    }

    public MoneyAmount $subtotal {
        get => $this->effectiveUnitPrice->multiply($this->quantity);
    }

    public MoneyAmount $tierSavings {
        get => $this->listSubtotal->subtract($this->subtotal);
    }

    public MoneyAmount $totalDiscount {
        get => $this->tierSavings->add($this->couponDiscount);
    }

    public MoneyAmount $netSubtotal {
        get => $this->subtotal->subtract($this->couponDiscount);
    }

    public MoneyAmount $taxableBase {
        get => $this->taxIncluded
            ? new MoneyAmount((int) round($this->netSubtotal->cents / (1 + $this->taxRate)), $this->netSubtotal->currency)
            : $this->netSubtotal;
    }

    public MoneyAmount $taxIgv {
        get => $this->taxIncluded
            ? $this->netSubtotal->subtract($this->taxableBase)
            : $this->taxableBase->percentage($this->taxRate * 100);
    }

    public MoneyAmount $grandTotal {
        get => $this->taxIncluded
            ? $this->netSubtotal->add($this->shippingCost)
            : $this->netSubtotal->add($this->taxIgv)->add($this->shippingCost);
    }

    public bool $hasWholesaleSavings {
        get => $this->tierSavings->cents > 0 || $this->discountPercent > 0.0;
    }

    public function __construct(
        int $quantity,
        MoneyAmount $baseUnitPrice,
        MoneyAmount $effectiveUnitPrice,
        CustomerTier $tier = CustomerTier::Minorista,
        float $discountPercent = 0.0,
        ?MoneyAmount $couponDiscount = null,
        ?MoneyAmount $shippingCost = null,
        float $taxRate = 0.18,
        bool $taxIncluded = true,
    ) {
        $currency = $baseUnitPrice->currency;
        $couponDiscount ??= MoneyAmount::zero($currency);
        $shippingCost ??= MoneyAmount::zero($currency);

        foreach ([$effectiveUnitPrice, $couponDiscount, $shippingCost] as $amount) {
            if ($amount->currency !== $currency) {
                throw new InvalidArgumentException(
                    sprintf('Todos los montos de PriceCalculation deben compartir la moneda %s (recibido %s).', $currency, $amount->currency)
                );
            }
        }

        $this->quantity = $quantity;
        $this->baseUnitPrice = $baseUnitPrice;
        $this->effectiveUnitPrice = $effectiveUnitPrice;
        $this->tier = $tier;
        $this->discountPercent = $discountPercent;
        $this->couponDiscount = $couponDiscount;
        $this->shippingCost = $shippingCost;
        $this->taxRate = $taxRate;
        $this->taxIncluded = $taxIncluded;
    }

    public static function forUnitPrice(
        float|int|string $baseUnitPrice,
        float|int|string $effectiveUnitPrice,
        int $quantity = 1,
        CustomerTier $tier = CustomerTier::Minorista,
        float|int|string $couponDiscount = 0.0,
        float|int|string $shippingCost = 0.0,
        bool $taxIncluded = true,
        string $currency = 'PEN',
        float $taxRate = 0.18,
    ): self {
        foreach ([$baseUnitPrice, $effectiveUnitPrice, $couponDiscount, $shippingCost] as $numericInput) {
            if (is_string($numericInput) && ! is_numeric($numericInput)) {
                throw new InvalidArgumentException('Los montos enviados a PriceCalculation deben ser numéricos.');
            }
        }

        $baseFloat = (float) $baseUnitPrice;
        $effectiveFloat = (float) $effectiveUnitPrice;
        $couponFloat = (float) $couponDiscount;
        $shippingFloat = (float) $shippingCost;

        $discountPercent = $baseFloat > 0
            ? max(0.0, (($baseFloat - $effectiveFloat) / $baseFloat) * 100)
            : 0.0;

        return new self(
            quantity: $quantity,
            baseUnitPrice: MoneyAmount::fromFloat($baseFloat, $currency),
            effectiveUnitPrice: MoneyAmount::fromFloat($effectiveFloat, $currency),
            tier: $tier,
            discountPercent: $discountPercent,
            couponDiscount: MoneyAmount::fromFloat($couponFloat, $currency),
            shippingCost: MoneyAmount::fromFloat($shippingFloat, $currency),
            taxRate: $taxRate,
            taxIncluded: $taxIncluded,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'tier' => $this->tier->value,
            'discount_percent' => $this->discountPercent,
            'base_unit_price' => $this->baseUnitPrice->toArray(),
            'effective_unit_price' => $this->effectiveUnitPrice->toArray(),
            'list_subtotal' => $this->listSubtotal->toArray(),
            'subtotal' => $this->subtotal->toArray(),
            'tier_savings' => $this->tierSavings->toArray(),
            'coupon_discount' => $this->couponDiscount->toArray(),
            'total_discount' => $this->totalDiscount->toArray(),
            'net_subtotal' => $this->netSubtotal->toArray(),
            'tax_rate' => $this->taxRate,
            'tax_percent' => round($this->taxRate * 100, 2),
            'taxable_base' => $this->taxableBase->toArray(),
            'tax_igv' => $this->taxIgv->toArray(),
            'shipping_cost' => $this->shippingCost->toArray(),
            'grand_total' => $this->grandTotal->toArray(),
        ];
    }
}
