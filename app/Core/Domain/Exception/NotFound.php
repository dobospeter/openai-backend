<?php

declare(strict_types=1);

namespace App\Core\Domain\Exception;

/**
 * A requested entity does not exist.
 */
abstract class NotFound extends DomainError
{
}
