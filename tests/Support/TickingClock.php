<?php

declare(strict_types=1);

namespace Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A deterministic clock that moves forward by a fixed step every time it is read.
 */
final class TickingClock implements ClockInterface
{
    private DateTimeImmutable $current;

    public function __construct(string $start = '2026-01-01T10:00:00+00:00', private readonly int $stepSeconds = 1)
    {
        $this->current = new DateTimeImmutable($start);
    }

    public function now(): DateTimeImmutable
    {
        $now = $this->current;
        $this->current = $now->add(new DateInterval('PT' . $this->stepSeconds . 'S'));

        return $now;
    }
}
