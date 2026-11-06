<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;

final class InMemoryConversationRepository implements ConversationRepository
{
    /** @var array<string, Conversation> */
    private array $conversations = [];

    public function save(Conversation $conversation): void
    {
        $this->conversations[$conversation->id()->value()] = $conversation;
    }

    public function get(ConversationId $id): Conversation
    {
        return $this->conversations[$id->value()] ?? throw ConversationNotFound::withId($id);
    }

    public function page(PageRequest $request): Page
    {
        $all = array_values($this->conversations);
        usort($all, static fn (Conversation $a, Conversation $b): int => [$b->updatedAt(), $b->id()->value()]
            <=> [$a->updatedAt(), $a->id()->value()]);

        return new Page(array_slice($all, $request->offset(), $request->perPage), count($all), $request);
    }

    public function delete(ConversationId $id): void
    {
        unset($this->conversations[$id->value()]);
    }

    public function count(): int
    {
        return count($this->conversations);
    }
}
