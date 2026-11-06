<?php

declare(strict_types=1);

namespace App\Core\Domain\Exception;

use RuntimeException;

/**
 * Root of every error raised by the domain. Subclasses fall into the categories below, which the
 * presentation layer maps to transport level errors (the domain itself knows nothing about HTTP).
 */
abstract class DomainError extends RuntimeException
{
    /**
     * Stable, machine readable identifier of the error (snake_case).
     */
    abstract public function errorCode(): string;
}
