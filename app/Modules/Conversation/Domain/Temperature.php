<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;

/**
 * Sampling temperature: the higher, the more random the answers are.
 */
final class Temperature
{
    public const MIN = 0.0;
    public const MAX = 2.0;

    private readonly float $value;

    public function __construct(float $value)
    {
        if (is_nan($value) || $value < self::MIN || $value > self::MAX) {
            throw InvalidValue::because(sprintf(
                'The temperature must be between %s and %s.',
                self::MIN,
                self::MAX
            ));
        }

        $this->value = round($value, 2);
    }

    public function value(): float
    {
        return $this->value;
    }
}
