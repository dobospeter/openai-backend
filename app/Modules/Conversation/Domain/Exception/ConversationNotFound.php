<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain\Exception;

use App\Core\Domain\Exception\NotFound;
use App\Modules\Conversation\Domain\ConversationId;

final class ConversationNotFound extends NotFound
{
    public static function withId(ConversationId $id): self
    {
        return new self(sprintf('Conversation "%s" was not found.', $id));
    }

    public function errorCode(): string
    {
        return 'conversation_not_found';
    }
}
