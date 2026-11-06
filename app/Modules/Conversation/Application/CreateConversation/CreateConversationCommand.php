<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\CreateConversation;

final class CreateConversationCommand
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $model = null,
        public readonly ?float $temperature = null,
        public readonly ?string $systemPrompt = null
    ) {
    }
}
