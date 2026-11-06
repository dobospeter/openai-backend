<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\SendMessage;

final class SendMessageCommand
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $content
    ) {
    }
}
