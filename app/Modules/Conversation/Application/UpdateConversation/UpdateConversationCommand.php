<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\UpdateConversation;

final class UpdateConversationCommand
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $title,
        public readonly string $model,
        public readonly float $temperature,
        public readonly ?string $systemPrompt
    ) {
    }
}
