<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ShippingMethod;
use App\ValueObjects\MoneyAmount;
use App\ValueObjects\ShippingQuote;

class ShippingService
{
    public const float FREE_SHIPPING_THRESHOLD = 199.00;

    public const string DEFAULT_CURRENCY = 'PEN';

    public function freeShippingThreshold(): float
    {
        return self::FREE_SHIPPING_THRESHOLD;
    }

    public function resolveMethod(?string $method): ShippingMethod
    {
        return ShippingMethod::tryFrom(trim((string) $method)) ?? ShippingMethod::Estandar;
    }

    public function quote(
        ShippingMethod $method,
        float|int|string|MoneyAmount $subtotal,
    ): ShippingQuote {
        $subtotalAmount = $subtotal instanceof MoneyAmount
            ? $subtotal
            : MoneyAmount::fromFloat($subtotal, self::DEFAULT_CURRENCY);

        $thresholdAmount = MoneyAmount::fromFloat($this->freeShippingThreshold(), $subtotalAmount->currency);

        $isFree = $method->freeShippingEligible()
            && $subtotalAmount->cents > 0
            && $subtotalAmount->cents >= $thresholdAmount->cents;

        $costAmount = $isFree
            ? MoneyAmount::zero($subtotalAmount->currency)
            : MoneyAmount::fromFloat($method->baseCost(), $subtotalAmount->currency);

        return new ShippingQuote(
            method: $method,
            cost: $costAmount,
            threshold: $thresholdAmount,
            subtotal: $subtotalAmount,
        );
    }

    /**
     * @return array<string, ShippingQuote>
     */
    public function allQuotes(float|int|string|MoneyAmount $subtotal): array
    {
        $quotes = [];

        foreach (ShippingMethod::cases() as $method) {
            $quotes[$method->value] = $this->quote($method, $subtotal);
        }

        return $quotes;
    }
}
