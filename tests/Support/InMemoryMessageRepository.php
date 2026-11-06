<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\MessageNotFound;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRepository;

final class InMemoryMessageRepository implements MessageRepository
{
    /** @var array<string, Message> */
    private array $messages = [];

    public function save(Message $message): void
    {
        $this->messages[$message->id()->value()] = $message;
    }

    public function get(ConversationId $conversationId, MessageId $id): Message
    {
        $message = $this->messages[$id->value()] ?? null;

        if ($message === null || !$message->conversationId()->equals($conversationId)) {
            throw MessageNotFound::inConversation($conversationId, $id);
        }

        return $message;
    }

    public function page(ConversationId $conversationId, PageRequest $request): Page
    {
        $all = $this->chronological($conversationId);

        return new Page(array_slice($all, $request->offset(), $request->perPage), count($all), $request);
    }

    public function latest(ConversationId $conversationId, int $limit): array
    {
        return array_slice($this->chronological($conversationId), -$limit);
    }

    public function delete(MessageId $id): void
    {
        unset($this->messages[$id->value()]);
    }

    /**
     * @return list<Message>
     */
    public function all(): array
    {
        return array_values($this->messages);
    }

    /**
     * @return list<Message>
     */
    private function chronological(ConversationId $conversationId): array
    {
        $own = array_values(array_filter(
            $this->messages,
            static fn (Message $message): bool => $message->conversationId()->equals($conversationId)
        ));
        usort($own, static fn (Message $a, Message $b): int => [$a->createdAt(), $a->id()->value()]
            <=> [$b->createdAt(), $b->id()->value()]);

        return $own;
    }
}
