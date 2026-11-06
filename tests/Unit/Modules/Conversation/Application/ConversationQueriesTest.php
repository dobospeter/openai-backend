<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Application;

use App\Core\Domain\Exception\InvalidValue;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Application\DeleteConversation\DeleteConversationHandler;
use App\Modules\Conversation\Application\GetConversation\GetConversationHandler;
use App\Modules\Conversation\Application\ListConversations\ListConversationsHandler;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;

final class ConversationQueriesTest extends ApplicationTestCase
{
    /**
     * @return iterable<string, array{int, int, list<string>, int}>
     */
    public static function pagingProvider(): iterable
    {
        yield 'first page' => [1, 2, ['C5', 'C4'], 5];
        yield 'second page' => [2, 2, ['C3', 'C2'], 5];
        yield 'last, partial page' => [3, 2, ['C1'], 5];
        yield 'page beyond the end is empty' => [4, 2, [], 5];
        yield 'everything on one page' => [1, 100, ['C5', 'C4', 'C3', 'C2', 'C1'], 5];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedIdProvider(): iterable
    {
        yield 'not a uuid' => ['abc'];
        yield 'empty' => [''];
    }

    public function testGetReturnsTheStoredConversation(): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $handler = new GetConversationHandler($this->conversations);

        // Act
        $found = $handler->handle($conversation->id()->value());

        // Assert
        $this->assertSame($conversation, $found);
    }

    public function testGetFailsForAnUnknownConversation(): void
    {
        // Arrange
        $handler = new GetConversationHandler($this->conversations);
        $this->expectException(ConversationNotFound::class);

        // Act
        $handler->handle(ConversationId::generate()->value());
    }

    #[DataProvider('malformedIdProvider')]
    public function testGetRejectsMalformedIds(string $id): void
    {
        // Arrange
        $handler = new GetConversationHandler($this->conversations);
        $this->expectException(InvalidValue::class);

        // Act
        $handler->handle($id);
    }

    /**
     * @param list<string> $expectedTitles
     */
    #[DataProvider('pagingProvider')]
    public function testListReturnsTheMostRecentlyActiveFirst(
        int $page,
        int $perPage,
        array $expectedTitles,
        int $expectedTotal
    ): void {
        // Arrange
        foreach (range(1, 5) as $n) {
            $this->conversations->save(Fixtures::conversation(title: "C{$n}", at: "2026-01-0{$n}T09:00:00+00:00"));
        }
        $handler = new ListConversationsHandler($this->conversations);

        // Act
        $result = $handler->handle(new PageRequest($page, $perPage));

        // Assert
        $titles = array_map(static fn ($c): string => $c->title()->value(), $result->items);
        $this->assertSame($expectedTitles, $titles);
        $this->assertSame($expectedTotal, $result->total);
    }

    public function testDeleteRemovesTheConversation(): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $handler = new DeleteConversationHandler($this->conversations);

        // Act
        $handler->handle($conversation->id()->value());

        // Assert
        $this->assertSame(0, $this->conversations->count());
    }

    public function testDeleteFailsForAnUnknownConversation(): void
    {
        // Arrange
        $handler = new DeleteConversationHandler($this->conversations);
        $this->expectException(ConversationNotFound::class);

        // Act
        $handler->handle(ConversationId::generate()->value());
    }
}
