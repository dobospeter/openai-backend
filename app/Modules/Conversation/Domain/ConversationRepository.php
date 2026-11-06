<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;

interface ConversationRepository
{
    /**
     * Inserts the conversation or updates it when it already exists.
     */
    public function save(Conversation $conversation): void;

    /**
     * @throws ConversationNotFound
     */
    public function get(ConversationId $id): Conversation;

    /**
     * Most recently active conversations first.
     *
     * @return Page<Conversation>
     */
    public function page(PageRequest $request): Page;

    /**
     * Removes the conversation together with all of its messages.
     */
    public function delete(ConversationId $id): void;
}
