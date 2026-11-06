<?php

declare(strict_types=1);

namespace App\Core\Domain\Exception;

/**
 * An external system did not answer in time.
 */
abstract class UpstreamTimeout extends UpstreamFailure
{
}
