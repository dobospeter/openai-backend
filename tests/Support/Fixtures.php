<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Conversation\Domain\ChatCompletion;
use App\Modules\Conversation\Domain\CompletionMetadata;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageContent;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use App\Modules\Conversation\Domain\TokenUsage;
use DateTimeImmutable;

/**
 * Builds domain objects with sensible defaults, so each test only spells out what it cares about.
 */
final class Fixtures
{
    public static function conversation(
        ?ConversationId $id = null,
        string $title = 'Trip planning',
        string $model = 'gpt-4',
        float $temperature = 0.7,
        ?string $systemPrompt = null,
        string $at = '2026-01-01T09:00:00+00:00'
    ): Conversation {
        return Conversation::start(
            $id ?? ConversationId::generate(),
            new ConversationTitle($title),
            new ModelName($model),
            new Temperature($temperature),
            SystemPrompt::fromNullable($systemPrompt),
            new DateTimeImmutable($at)
        );
    }

    public static function userMessage(
        ConversationId $conversationId,
        string $content = 'Hello',
        string $at = '2026-01-01T09:00:00+00:00'
    ): Message {
        return Message::fromUser(
            MessageId::generate(),
            $conversationId,
            new MessageContent($content),
            new DateTimeImmutable($at)
        );
    }

    public static function assistantMessage(
        ConversationId $conversationId,
        string $content = 'Hi there',
        string $at = '2026-01-01T09:00:01+00:00'
    ): Message {
        return Message::fromAssistant(
            MessageId::generate(),
            $conversationId,
            new ChatCompletion(
                new MessageContent($content),
                new CompletionMetadata(new ModelName('gpt-4-0613'), new TokenUsage(10, 20, 30), 'stop')
            ),
            new DateTimeImmutable($at)
        );
    }
}
