<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Conversation\Infrastructure;

use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\MessageNotFound;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRole;
use App\Modules\Conversation\Infrastructure\Persistence\EloquentConversationRepository;
use App\Modules\Conversation\Infrastructure\Persistence\EloquentMessageRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;
use Tests\TestCase;

final class EloquentMessageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentMessageRepository $repository;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentMessageRepository();
        $this->conversation = Fixtures::conversation();
        (new EloquentConversationRepository())->save($this->conversation);
    }

    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function latestProvider(): iterable
    {
        yield 'fewer than stored' => [2, ['m4', 'm5']];
        yield 'exactly all' => [5, ['m1', 'm2', 'm3', 'm4', 'm5']];
        yield 'more than stored' => [50, ['m1', 'm2', 'm3', 'm4', 'm5']];
        yield 'just one' => [1, ['m5']];
    }

    /**
     * @return iterable<string, array{int, int, list<string>}>
     */
    public static function pageProvider(): iterable
    {
        yield 'first page' => [1, 2, ['m1', 'm2']];
        yield 'last page' => [3, 2, ['m5']];
        yield 'past the end' => [4, 2, []];
    }

    public function testItRoundTripsAUserMessage(): void
    {
        // Arrange
        $message = Fixtures::userMessage($this->conversation->id(), 'Ünïcode ✓ question');

        // Act
        $this->repository->save($message);
        $found = $this->repository->get($this->conversation->id(), $message->id());

        // Assert
        $this->assertSame(MessageRole::User, $found->role());
        $this->assertSame('Ünïcode ✓ question', $found->content()->value());
        $this->assertNull($found->metadata());
        $this->assertEquals($message->createdAt(), $found->createdAt());
    }

    public function testItRoundTripsAnAssistantMessageWithItsCompletionDetails(): void
    {
        // Arrange
        $message = Fixtures::assistantMessage($this->conversation->id(), 'The answer');

        // Act
        $this->repository->save($message);
        $found = $this->repository->get($this->conversation->id(), $message->id());

        // Assert
        $this->assertSame(MessageRole::Assistant, $found->role());
        $metadata = $found->metadata();
        $this->assertNotNull($metadata);
        $this->assertSame('gpt-4-0613', $metadata->model->value());
        $this->assertNotNull($metadata->usage);
        $this->assertSame(10, $metadata->usage->promptTokens);
        $this->assertSame(20, $metadata->usage->completionTokens);
        $this->assertSame(30, $metadata->usage->totalTokens);
        $this->assertSame('stop', $metadata->finishReason);
    }

    public function testItIsScopedToTheConversation(): void
    {
        // Arrange
        $message = Fixtures::userMessage($this->conversation->id());
        $this->repository->save($message);
        $this->expectException(MessageNotFound::class);

        // Act
        $this->repository->get(ConversationId::generate(), $message->id());
    }

    public function testItFailsForAnUnknownMessage(): void
    {
        // Arrange
        $this->expectException(MessageNotFound::class);

        // Act
        $this->repository->get($this->conversation->id(), MessageId::generate());
    }

    public function testItRefusesMessagesOfAConversationThatDoesNotExist(): void
    {
        // Arrange
        $orphan = Fixtures::userMessage(ConversationId::generate());
        $this->expectException(QueryException::class);

        // Act
        $this->repository->save($orphan);
    }

    /**
     * @param list<string> $expectedContents
     */
    #[DataProvider('latestProvider')]
    public function testLatestReturnsTheNewestMessagesInChronologicalOrder(int $limit, array $expectedContents): void
    {
        // Arrange
        $this->storeFiveMessages();

        // Act
        $latest = $this->repository->latest($this->conversation->id(), $limit);

        // Assert
        $this->assertSame($expectedContents, $this->contents($latest));
    }

    /**
     * @param list<string> $expectedContents
     */
    #[DataProvider('pageProvider')]
    public function testPageReturnsTheOldestFirst(int $page, int $perPage, array $expectedContents): void
    {
        // Arrange
        $this->storeFiveMessages();

        // Act
        $result = $this->repository->page($this->conversation->id(), new PageRequest($page, $perPage));

        // Assert
        $this->assertSame(5, $result->total);
        $this->assertSame($expectedContents, $this->contents($result->items));
    }

    public function testPageIgnoresOtherConversations(): void
    {
        // Arrange
        $this->storeFiveMessages();
        $other = Fixtures::conversation();
        (new EloquentConversationRepository())->save($other);
        $this->repository->save(Fixtures::userMessage($other->id(), 'foreign'));

        // Act
        $result = $this->repository->page($this->conversation->id(), new PageRequest());

        // Assert
        $this->assertSame(5, $result->total);
        $this->assertNotContains('foreign', $this->contents($result->items));
    }

    public function testDeleteRemovesOnlyThatMessage(): void
    {
        // Arrange
        $doomed = Fixtures::userMessage($this->conversation->id(), 'doomed');
        $kept = Fixtures::userMessage($this->conversation->id(), 'kept');
        $this->repository->save($doomed);
        $this->repository->save($kept);

        // Act
        $this->repository->delete($doomed->id());

        // Assert
        $this->assertDatabaseMissing('messages', ['id' => $doomed->id()->value()]);
        $this->assertDatabaseHas('messages', ['id' => $kept->id()->value()]);
    }

    private function storeFiveMessages(): void
    {
        foreach (range(1, 5) as $n) {
            $this->repository->save(
                Fixtures::userMessage($this->conversation->id(), "m{$n}", sprintf('2026-01-01T09:00:%02d+00:00', $n))
            );
        }
    }

    /**
     * @param list<Message> $messages
     * @return list<string>
     */
    private function contents(array $messages): array
    {
        return array_map(static fn (Message $message): string => $message->content()->value(), $messages);
    }
}
