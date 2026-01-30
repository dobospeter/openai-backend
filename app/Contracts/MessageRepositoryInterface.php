<?php

namespace App\Contracts;

use App\Models\Message;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    /**
     * Create a new message
     *
     * @param array $data
     * @return Message
     */
    public function create(array $data): Message;

    /**
     * Find messages by conversation ID
     *
     * @param string $conversationId
     * @return Collection
     */
    public function findByConversationId(string $conversationId): Collection;

    /**
     * Get latest conversations
     *
     * @param int $limit
     * @return Collection
     */
    public function getLatestConversations(int $limit = 10): Collection;
}
