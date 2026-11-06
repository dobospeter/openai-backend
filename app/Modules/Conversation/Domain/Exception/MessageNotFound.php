<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain\Exception;

use App\Core\Domain\Exception\NotFound;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\MessageId;

final class MessageNotFound extends NotFound
{
    public static function inConversation(ConversationId $conversationId, MessageId $id): self
    {
        return new self(sprintf('Message "%s" was not found in conversation "%s".', $id, $conversationId));
    }

    public function errorCode(): string
    {
        return 'message_not_found';
    }
}
