<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\ListMessages;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageRepository;

final class ListMessagesHandler
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages
    ) {
    }

    /**
     * @return Page<Message>
     */
    public function handle(string $conversationId, PageRequest $request): Page
    {
        $id = ConversationId::fromString($conversationId);

        $this->conversations->get($id);

        return $this->messages->page($id, $request);
    }
}
