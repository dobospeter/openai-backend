<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\SendMessage;

use App\Modules\Conversation\Domain\Message;

final class SendMessageResult
{
    public function __construct(
        public readonly Message $userMessage,
        public readonly Message $assistantMessage
    ) {
    }
}
