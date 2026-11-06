<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\DeleteMessage;

use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRepository;

final class DeleteMessageHandler
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages
    ) {
    }

    public function handle(string $conversationId, string $messageId): void
    {
        $conversation = $this->conversations->get(ConversationId::fromString($conversationId));
        $message = $this->messages->get($conversation->id(), MessageId::fromString($messageId));

        $this->messages->delete($message->id());
    }
}
