<?php

declare(strict_types=1);

namespace App\Core\Domain\ValueObject;

use App\Core\Domain\Exception\InvalidValue;
use Ramsey\Uuid\Uuid;
use Stringable;

/**
 * Base class for UUID based identities. Generated ids are UUIDv7, so they sort by creation time.
 */
abstract class Identifier implements Stringable
{
    final protected function __construct(private readonly string $value)
    {
    }

    public static function generate(): static
    {
        return new static(Uuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        if (!Uuid::isValid($value)) {
            throw InvalidValue::because(sprintf('"%s" is not a valid %s.', $value, static::class));
        }

        return new static(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
