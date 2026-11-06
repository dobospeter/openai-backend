<?php

declare(strict_types=1);

namespace App\Core\Domain\Exception;

/**
 * A value violates a domain invariant.
 */
class InvalidValue extends DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'invalid_value';
    }
}
