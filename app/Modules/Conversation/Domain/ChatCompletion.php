<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

/**
 * The answer produced by the chat model.
 */
final class ChatCompletion
{
    public function __construct(
        public readonly MessageContent $content,
        public readonly CompletionMetadata $metadata
    ) {
    }
}
