<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\Persistence;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\CompletionMetadata;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\MessageNotFound;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageContent;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRepository;
use App\Modules\Conversation\Domain\MessageRole;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\TokenUsage;
use App\Modules\Conversation\Infrastructure\Persistence\Eloquent\MessageModel;

final class EloquentMessageRepository implements MessageRepository
{
    public function save(Message $message): void
    {
        $row = MessageModel::query()->find($message->id()->value()) ?? new MessageModel();
        $metadata = $message->metadata();

        $row->forceFill([
            'id' => $message->id()->value(),
            'conversation_id' => $message->conversationId()->value(),
            'role' => $message->role()->value,
            'content' => $message->content()->value(),
            'model' => $metadata?->model->value(),
            'prompt_tokens' => $metadata?->usage?->promptTokens,
            'completion_tokens' => $metadata?->usage?->completionTokens,
            'total_tokens' => $metadata?->usage?->totalTokens,
            'finish_reason' => $metadata?->finishReason,
            'created_at' => $message->createdAt(),
        ])->save();
    }

    public function get(ConversationId $conversationId, MessageId $id): Message
    {
        $row = MessageModel::query()
            ->where('conversation_id', $conversationId->value())
            ->find($id->value());

        if ($row === null) {
            throw MessageNotFound::inConversation($conversationId, $id);
        }

        return $this->toEntity($row);
    }

    public function page(ConversationId $conversationId, PageRequest $request): Page
    {
        $query = MessageModel::query()->where('conversation_id', $conversationId->value());

        $items = (clone $query)
            ->orderBy('created_at')
            ->orderBy('id')
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->map(fn (MessageModel $row): Message => $this->toEntity($row))
            ->all();

        return new Page($items, $query->count(), $request);
    }

    public function latest(ConversationId $conversationId, int $limit): array
    {
        return MessageModel::query()
            ->where('conversation_id', $conversationId->value())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (MessageModel $row): Message => $this->toEntity($row))
            ->values()
            ->all();
    }

    public function delete(MessageId $id): void
    {
        MessageModel::query()->whereKey($id->value())->delete();
    }

    private function toEntity(MessageModel $row): Message
    {
        return Message::reconstitute(
            MessageId::fromString($row->id),
            ConversationId::fromString($row->conversation_id),
            MessageRole::from($row->role),
            new MessageContent($row->content),
            $this->toMetadata($row),
            $row->created_at->toDateTimeImmutable()
        );
    }

    private function toMetadata(MessageModel $row): ?CompletionMetadata
    {
        if ($row->model === null) {
            return null;
        }

        $usage = $row->total_tokens === null
            ? null
            : new TokenUsage($row->prompt_tokens ?? 0, $row->completion_tokens ?? 0, $row->total_tokens);

        return new CompletionMetadata(new ModelName($row->model), $usage, $row->finish_reason);
    }
}
