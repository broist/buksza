<?php

namespace App\Finance;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use JsonSerializable;

/** Exact rational arithmetic; decimal display is truncated, never rounded. */
final class Exact implements JsonSerializable
{
    public readonly BigRational $number;

    public function __construct(int|string|BigRational $number = 0)
    {
        $this->number = BigRational::of($number);
    }

    public function plus(int|string|self $other): self
    {
        return new self($this->number->plus($other instanceof self ? $other->number : $other));
    }

    public function minus(int|string|self $other): self
    {
        return new self($this->number->minus($other instanceof self ? $other->number : $other));
    }

    public function times(int|string|self $other): self
    {
        return new self($this->number->multipliedBy($other instanceof self ? $other->number : $other));
    }

    public function divide(int|string|self $other): self
    {
        return new self($this->number->dividedBy($other instanceof self ? $other->number : $other));
    }

    public function jsonSerialize(): array
    {
        $numerator = (string) $this->number->getNumerator();
        $denominator = (string) $this->number->getDenominator();
        $negative = str_starts_with($numerator, '-');
        $abs = ltrim($numerator, '-');
        $n = BigInteger::of($abs);
        $d = BigInteger::of($denominator);
        [$whole, $remainder] = $n->quotientAndRemainder($d);
        $decimals = '';
        for ($i = 0; $i < 6 && ! $remainder->isZero(); $i++) {
            [$digit, $remainder] = $remainder->multipliedBy(10)->quotientAndRemainder($d);
            $decimals .= (string) $digit;
        }
        $text = ($negative ? '−' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', (string) $whole);
        if ($decimals !== '') {
            $text .= ','.$decimals.($remainder->isZero() ? '' : '…');
        }

        return ['numerator' => $numerator, 'denominator' => $denominator, 'value' => (float) $numerator / (float) $denominator, 'text' => $text, 'exact' => (string) $this->number];
    }
}
