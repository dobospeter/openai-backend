<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Application;

use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Application\DeleteMessage\DeleteMessageHandler;
use App\Modules\Conversation\Application\GetMessage\GetMessageHandler;
use App\Modules\Conversation\Application\ListMessages\ListMessagesHandler;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use App\Modules\Conversation\Domain\Exception\MessageNotFound;
use App\Modules\Conversation\Domain\MessageId;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;

final class MessageQueriesTest extends ApplicationTestCase
{
    /**
     * @return iterable<string, array{int, int, list<string>, int}>
     */
    public static function pagingProvider(): iterable
    {
        yield 'first page' => [1, 2, ['m1', 'm2'], 3];
        yield 'second page' => [2, 2, ['m3'], 3];
        yield 'beyond the end' => [3, 2, [], 3];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function operationProvider(): iterable
    {
        yield 'get' => ['get'];
        yield 'delete' => ['delete'];
    }

    /**
     * @param list<string> $expectedContents
     */
    #[DataProvider('pagingProvider')]
    public function testListReturnsTheOldestFirstAndOnlyOfThatConversation(
        int $page,
        int $perPage,
        array $expectedContents,
        int $expectedTotal
    ): void {
        // Arrange
        $conversation = Fixtures::conversation();
        $other = Fixtures::conversation();
        $this->conversations->save($conversation);
        $this->conversations->save($other);
        foreach ([3, 1, 2] as $n) {
            $this->messages->save(Fixtures::userMessage($conversation->id(), "m{$n}", "2026-01-01T09:00:0{$n}+00:00"));
        }
        $this->messages->save(Fixtures::userMessage($other->id(), 'foreign'));
        $handler = new ListMessagesHandler($this->conversations, $this->messages);

        // Act
        $result = $handler->handle($conversation->id()->value(), new PageRequest($page, $perPage));

        // Assert
        $contents = array_map(static fn ($m): string => $m->content()->value(), $result->items);
        $this->assertSame($expectedContents, $contents);
        $this->assertSame($expectedTotal, $result->total);
    }

    public function testListFailsForAnUnknownConversation(): void
    {
        // Arrange
        $handler = new ListMessagesHandler($this->conversations, $this->messages);
        $this->expectException(ConversationNotFound::class);

        // Act
        $handler->handle(ConversationId::generate()->value(), new PageRequest());
    }

    public function testGetReturnsTheMessage(): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $message = Fixtures::userMessage($conversation->id());
        $this->messages->save($message);
        $handler = new GetMessageHandler($this->conversations, $this->messages);

        // Act
        $found = $handler->handle($conversation->id()->value(), $message->id()->value());

        // Assert
        $this->assertSame($message, $found);
    }

    public function testDeleteRemovesTheMessageButKeepsTheOthers(): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $doomed = Fixtures::userMessage($conversation->id(), 'doomed');
        $kept = Fixtures::assistantMessage($conversation->id(), 'kept');
        $this->messages->save($doomed);
        $this->messages->save($kept);
        $handler = new DeleteMessageHandler($this->conversations, $this->messages);

        // Act
        $handler->handle($conversation->id()->value(), $doomed->id()->value());

        // Assert
        $this->assertSame([$kept], $this->messages->all());
    }

    #[DataProvider('operationProvider')]
    public function testGetAndDeleteFailForAnUnknownConversation(string $operation): void
    {
        // Arrange
        $handler = $this->handlerFor($operation);
        $this->expectException(ConversationNotFound::class);

        // Act
        $handler->handle(ConversationId::generate()->value(), MessageId::generate()->value());
    }

    #[DataProvider('operationProvider')]
    public function testGetAndDeleteFailForAnUnknownMessage(string $operation): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $handler = $this->handlerFor($operation);
        $this->expectException(MessageNotFound::class);

        // Act
        $handler->handle($conversation->id()->value(), MessageId::generate()->value());
    }

    #[DataProvider('operationProvider')]
    public function testAMessageCannotBeReachedThroughAnotherConversation(string $operation): void
    {
        // Arrange
        $owner = Fixtures::conversation();
        $stranger = Fixtures::conversation();
        $this->conversations->save($owner);
        $this->conversations->save($stranger);
        $message = Fixtures::userMessage($owner->id());
        $this->messages->save($message);
        $handler = $this->handlerFor($operation);
        $this->expectException(MessageNotFound::class);

        // Act
        try {
            $handler->handle($stranger->id()->value(), $message->id()->value());
        } finally {
            // Assert
            $this->assertSame([$message], $this->messages->all());
        }
    }

    private function handlerFor(string $operation): GetMessageHandler|DeleteMessageHandler
    {
        return $operation === 'get'
            ? new GetMessageHandler($this->conversations, $this->messages)
            : new DeleteMessageHandler($this->conversations, $this->messages);
    }
}
