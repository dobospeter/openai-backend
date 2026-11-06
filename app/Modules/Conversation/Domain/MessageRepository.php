<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\Exception\MessageNotFound;

interface MessageRepository
{
    public function save(Message $message): void;

    /**
     * @throws MessageNotFound when the message does not exist in the given conversation
     */
    public function get(ConversationId $conversationId, MessageId $id): Message;

    /**
     * Oldest messages first.
     *
     * @return Page<Message>
     */
    public function page(ConversationId $conversationId, PageRequest $request): Page;

    /**
     * The newest messages of the conversation in chronological order.
     *
     * @return list<Message>
     */
    public function latest(ConversationId $conversationId, int $limit): array;

    public function delete(MessageId $id): void;
}
