<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\ShippingMethod;
use InvalidArgumentException;

final class ShippingQuote
{
    public private(set) ShippingMethod $method;

    public private(set) MoneyAmount $cost;

    public private(set) MoneyAmount $threshold;

    public private(set) MoneyAmount $subtotal;

    public bool $isFree {
        get => $this->cost->isZero;
    }

    public MoneyAmount $remainingForFree {
        get => $this->subtotal->cents >= $this->threshold->cents
            ? MoneyAmount::zero($this->subtotal->currency)
            : $this->threshold->subtract($this->subtotal);
    }

    public float $progressPercent {
        get {
            if ($this->threshold->cents <= 0) {
                return 100.0;
            }

            return round(min(100.0, ($this->subtotal->cents / $this->threshold->cents) * 100), 1);
        }
    }

    public bool $qualifiesForFreeThreshold {
        get => $this->subtotal->cents >= $this->threshold->cents;
    }

    public function __construct(
        ShippingMethod $method,
        MoneyAmount $cost,
        MoneyAmount $threshold,
        MoneyAmount $subtotal,
    ) {
        $currency = $subtotal->currency;

        foreach ([$cost, $threshold] as $amount) {
            if ($amount->currency !== $currency) {
                throw new InvalidArgumentException(
                    sprintf('Todos los montos de ShippingQuote deben usar la moneda %s (recibido %s).', $currency, $amount->currency)
                );
            }
        }

        $this->method = $method;
        $this->cost = $cost;
        $this->threshold = $threshold;
        $this->subtotal = $subtotal;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'estimated_window' => $this->method->estimatedWindow(),
            'free_shipping_eligible' => $this->method->freeShippingEligible(),
            'cost' => $this->cost->toArray(),
            'threshold' => $this->threshold->toArray(),
            'subtotal' => $this->subtotal->toArray(),
            'is_free' => $this->isFree,
            'qualifies_for_free_threshold' => $this->qualifiesForFreeThreshold,
            'remaining_for_free' => $this->remainingForFree->toArray(),
            'progress_percent' => $this->progressPercent,
        ];
    }
}
