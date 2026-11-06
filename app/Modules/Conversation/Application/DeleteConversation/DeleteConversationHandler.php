<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\DeleteConversation;

use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;

final class DeleteConversationHandler
{
    public function __construct(private readonly ConversationRepository $conversations)
    {
    }

    public function handle(string $conversationId): void
    {
        $id = ConversationId::fromString($conversationId);

        $this->conversations->get($id);
        $this->conversations->delete($id);
    }
}
