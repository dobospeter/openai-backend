<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;

final class TokenUsage
{
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens
    ) {
        if ($promptTokens < 0 || $completionTokens < 0 || $totalTokens < 0) {
            throw InvalidValue::because('Token counts must not be negative.');
        }
    }
}
