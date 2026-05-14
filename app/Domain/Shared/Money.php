<?php

namespace App\Domain\Shared;

final class Money
{
    private function __construct(
        public readonly int    $amountCents,
        public readonly string $currency,
    ) {}

    public static function fromCents(int $cents, string $currency = 'ZAR'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public static function zero(string $currency = 'ZAR'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountCents + $other->amountCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountCents - $other->amountCents, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->amountCents * $factor), $this->currency);
    }

    public function toFloat(): float
    {
        return $this->amountCents / 100;
    }

    public function isZero(): bool
    {
        return $this->amountCents === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
