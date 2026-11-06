<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use DateTimeImmutable;

/**
 * A single utterance in a conversation, written either by the user or by the assistant.
 */
final class Message
{
    public const MAX_USER_CONTENT_LENGTH = 2000;

    private function __construct(
        private readonly MessageId $id,
        private readonly ConversationId $conversationId,
        private readonly MessageRole $role,
        private readonly MessageContent $content,
        private readonly ?CompletionMetadata $metadata,
        private readonly DateTimeImmutable $createdAt
    ) {
    }

    public static function fromUser(
        MessageId $id,
        ConversationId $conversationId,
        MessageContent $content,
        DateTimeImmutable $now
    ): self {
        if ($content->length() > self::MAX_USER_CONTENT_LENGTH) {
            throw InvalidValue::because(sprintf(
                'A message must not be longer than %d characters.',
                self::MAX_USER_CONTENT_LENGTH
            ));
        }

        return new self($id, $conversationId, MessageRole::User, $content, null, $now);
    }

    public static function fromAssistant(
        MessageId $id,
        ConversationId $conversationId,
        ChatCompletion $completion,
        DateTimeImmutable $now
    ): self {
        return new self(
            $id,
            $conversationId,
            MessageRole::Assistant,
            $completion->content,
            $completion->metadata,
            $now
        );
    }

    /**
     * Rebuilds a persisted message; for repositories only.
     */
    public static function reconstitute(
        MessageId $id,
        ConversationId $conversationId,
        MessageRole $role,
        MessageContent $content,
        ?CompletionMetadata $metadata,
        DateTimeImmutable $createdAt
    ): self {
        return new self($id, $conversationId, $role, $content, $metadata, $createdAt);
    }

    public function id(): MessageId
    {
        return $this->id;
    }

    public function conversationId(): ConversationId
    {
        return $this->conversationId;
    }

    public function role(): MessageRole
    {
        return $this->role;
    }

    public function content(): MessageContent
    {
        return $this->content;
    }

    public function metadata(): ?CompletionMetadata
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
