<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\GetConversation;

use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;

final class GetConversationHandler
{
    public function __construct(private readonly ConversationRepository $conversations)
    {
    }

    public function handle(string $conversationId): Conversation
    {
        return $this->conversations->get(ConversationId::fromString($conversationId));
    }
}
