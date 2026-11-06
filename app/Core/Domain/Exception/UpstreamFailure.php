<?php

declare(strict_types=1);

namespace App\Core\Domain\Exception;

use Throwable;

/**
 * An external system the domain depends on failed to fulfil a request.
 */
abstract class UpstreamFailure extends DomainError
{
    /**
     * @param array<string, mixed> $context Diagnostic data for the logs (never sent to API clients).
     */
    public function __construct(
        string $message,
        private readonly array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Picked up by Laravel's exception reporter and added to the log entry.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
