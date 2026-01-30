<?php

namespace App\Repositories;

use App\Contracts\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Support\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    /**
     * Create a new message
     *
     * @param array $data
     * @return Message
     */
    public function create(array $data): Message
    {
        return Message::create($data);
    }

    /**
     * Find messages by conversation ID
     *
     * @param string $conversationId
     * @return Collection
     */
    public function findByConversationId(string $conversationId): Collection
    {
        return Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get latest conversations
     *
     * @param int $limit
     * @return Collection
     */
    public function getLatestConversations(int $limit = 10): Collection
    {
        return Message::select('conversation_id')
            ->distinct()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('conversation_id');
    }
}
