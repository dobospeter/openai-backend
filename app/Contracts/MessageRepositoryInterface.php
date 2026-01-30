<?php

namespace App\Contracts;

use App\Models\Message;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    /**
     * Create a new message
     */
    public function create(array $data): Message;

    /**
     * Find messages by conversation ID
     */
    public function findByConversationId(string $conversationId): Collection;

    /**
     * Get latest conversations
     */
    public function getLatestConversations(int $limit = 10): Collection;
}
