<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use Stringable;

final class MoneyAmount implements Stringable
{
    public private(set) int $cents {
        set {
            if ($value < 0) {
                throw new InvalidArgumentException('El monto en céntimos no puede ser negativo.');
            }

            $this->cents = $value;
        }
    }

    public private(set) string $currency {
        set {
            $normalized = strtoupper(trim($value));
            if ($normalized === '') {
                throw new InvalidArgumentException('La moneda no puede estar vacía.');
            }

            $this->currency = $normalized;
        }
    }

    public float $amount {
        get => round($this->cents / 100, 2);
    }

    public string $symbol {
        get => match ($this->currency) {
            'PEN' => 'S/',
            'USD' => '$',
            'EUR' => '€',
            default => $this->currency,
        };
    }

    public string $formatted {
        get => sprintf('%s %s', $this->symbol, number_format($this->amount, 2, '.', ','));
    }

    public bool $isZero {
        get => $this->cents === 0;
    }

    public function __construct(int $cents, string $currency = 'PEN')
    {
        $this->cents = $cents;
        $this->currency = $currency;
    }

    public static function fromFloat(float|int|string $amount, string $currency = 'PEN'): self
    {
        if (is_string($amount) && ! is_numeric($amount)) {
            throw new InvalidArgumentException('El monto debe ser un valor numérico válido.');
        }

        $numericAmount = (float) $amount;

        if ($numericAmount < 0) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        return new self((int) round($numericAmount * 100), $currency);
    }

    public static function zero(string $currency = 'PEN'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->cents - $other->cents), $this->currency);
    }

    public function multiply(float|int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('El factor multiplicador no puede ser negativo.');
        }

        return new self((int) round($this->cents * $factor), $this->currency);
    }

    public function percentage(float $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('El porcentaje no puede ser negativo.');
        }

        return new self((int) round($this->cents * ($percent / 100)), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->cents === $other->cents;
    }

    /**
     * @return array{cents: int, amount: float, currency: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'cents' => $this->cents,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->formatted,
        ];
    }

    public function __toString(): string
    {
        return $this->formatted;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Incompatibilidad de monedas: %s vs %s.', $this->currency, $other->currency)
            );
        }
    }
}
