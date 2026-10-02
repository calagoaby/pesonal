<?php

namespace App\Support;

// Makes centavos a type, not a bare int that could silently become a float.
// No positivity invariant: validation guarantees amount > 0 at the boundary,
// and the VO must also hold copied/aggregate values without throwing.
final class Money
{
    private function __construct(private int $centavos) {}

    public static function fromCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    public function centavos(): int
    {
        return $this->centavos;
    }
}
