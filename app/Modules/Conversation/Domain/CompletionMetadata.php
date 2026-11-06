<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

/**
 * Facts about how a completion was produced.
 */
final class CompletionMetadata
{
    public function __construct(
        public readonly ModelName $model,
        public readonly ?TokenUsage $usage = null,
        public readonly ?string $finishReason = null
    ) {
    }
}
