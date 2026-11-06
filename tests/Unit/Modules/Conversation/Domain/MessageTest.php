<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageContent;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRole;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixtures;

final class MessageTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function acceptedLengthProvider(): iterable
    {
        yield 'one character' => [1];
        yield 'exactly the maximum' => [Message::MAX_USER_CONTENT_LENGTH];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rejectedLengthProvider(): iterable
    {
        yield 'one over the maximum' => [Message::MAX_USER_CONTENT_LENGTH + 1];
        yield 'far over the maximum' => [Message::MAX_USER_CONTENT_LENGTH * 3];
    }

    #[DataProvider('acceptedLengthProvider')]
    public function testUserMessagesUpToTheLimitAreAccepted(int $length): void
    {
        // Arrange
        $content = new MessageContent(str_repeat('a', $length));
        $conversationId = ConversationId::generate();
        $now = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

        // Act
        $message = Message::fromUser(MessageId::generate(), $conversationId, $content, $now);

        // Assert
        $this->assertSame(MessageRole::User, $message->role());
        $this->assertNull($message->metadata());
        $this->assertTrue($conversationId->equals($message->conversationId()));
        $this->assertEquals($now, $message->createdAt());
    }

    #[DataProvider('rejectedLengthProvider')]
    public function testUserMessagesOverTheLimitAreRejected(int $length): void
    {
        // Arrange
        $content = new MessageContent(str_repeat('a', $length));
        $this->expectException(InvalidValue::class);

        // Act
        Message::fromUser(MessageId::generate(), ConversationId::generate(), $content, new DateTimeImmutable());
    }

    public function testAssistantMessagesCarryTheCompletionDetails(): void
    {
        // Arrange
        $conversationId = ConversationId::generate();

        // Act
        $message = Fixtures::assistantMessage($conversationId, 'The answer');

        // Assert
        $this->assertSame(MessageRole::Assistant, $message->role());
        $this->assertSame('The answer', $message->content()->value());
        $metadata = $message->metadata();
        $this->assertNotNull($metadata);
        $this->assertSame('gpt-4-0613', $metadata->model->value());
        $this->assertSame(30, $metadata->usage?->totalTokens);
        $this->assertSame('stop', $metadata->finishReason);
    }

    public function testAssistantAnswersAreNotLimitedInLength(): void
    {
        // Arrange
        $longAnswer = str_repeat('a', Message::MAX_USER_CONTENT_LENGTH * 5);

        // Act
        $message = Fixtures::assistantMessage(ConversationId::generate(), $longAnswer);

        // Assert
        $this->assertSame($longAnswer, $message->content()->value());
    }
}
