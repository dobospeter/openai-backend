<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\GetMessage;

use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRepository;

final class GetMessageHandler
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages
    ) {
    }

    public function handle(string $conversationId, string $messageId): Message
    {
        $conversation = $this->conversations->get(ConversationId::fromString($conversationId));

        return $this->messages->get($conversation->id(), MessageId::fromString($messageId));
    }
}
