<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Conversation\Infrastructure;

use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use App\Modules\Conversation\Infrastructure\Persistence\EloquentConversationRepository;
use App\Modules\Conversation\Infrastructure\Persistence\EloquentMessageRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures;
use Tests\TestCase;

final class EloquentConversationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentConversationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentConversationRepository();
    }

    /**
     * @return iterable<string, array{float, ?string}>
     */
    public static function settingsProvider(): iterable
    {
        yield 'with a system prompt' => [0.7, 'Be brief.'];
        yield 'without a system prompt' => [1.25, null];
        yield 'lowest temperature' => [0.0, null];
        yield 'highest temperature' => [2.0, 'Be wild.'];
    }

    /**
     * @return iterable<string, array{int, int, list<string>}>
     */
    public static function pageProvider(): iterable
    {
        yield 'first page' => [1, 2, ['E', 'D']];
        yield 'middle page' => [2, 2, ['C', 'B']];
        yield 'last page' => [3, 2, ['A']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('settingsProvider')]
    public function testItRoundTripsAConversationWithoutLosingPrecision(float $temperature, ?string $systemPrompt): void
    {
        // Arrange
        $conversation = Fixtures::conversation(temperature: $temperature, systemPrompt: $systemPrompt);
        $conversation->recordActivity(new DateTimeImmutable('2026-02-03T04:05:06.123456+00:00'));

        // Act
        $this->repository->save($conversation);
        $found = $this->repository->get($conversation->id());

        // Assert
        $this->assertTrue($conversation->id()->equals($found->id()));
        $this->assertSame('Trip planning', $found->title()->value());
        $this->assertSame('gpt-4', $found->model()->value());
        $this->assertSame($temperature, $found->temperature()->value());
        $this->assertSame($systemPrompt, $found->systemPrompt()?->value());
        $this->assertSame('2026-02-03T04:05:06.123456+00:00', $found->updatedAt()->format('Y-m-d\TH:i:s.uP'));
        $this->assertEquals($conversation->createdAt(), $found->createdAt());
    }

    public function testSavingAnExistingConversationUpdatesIt(): void
    {
        // Arrange
        $conversation = Fixtures::conversation(title: 'Old');
        $this->repository->save($conversation);
        $conversation->reconfigure(
            new ConversationTitle('New'),
            new ModelName('gpt-3.5-turbo'),
            new Temperature(0.1),
            SystemPrompt::fromNullable('Prompt'),
            new DateTimeImmutable('2026-03-03T03:03:03+00:00')
        );

        // Act
        $this->repository->save($conversation);

        // Assert
        $this->assertDatabaseCount('conversations', 1);
        $found = $this->repository->get($conversation->id());
        $this->assertSame('New', $found->title()->value());
        $this->assertSame('gpt-3.5-turbo', $found->model()->value());
        $this->assertSame('Prompt', $found->systemPrompt()?->value());
    }

    public function testItFailsForAnUnknownConversation(): void
    {
        // Arrange
        $this->expectException(ConversationNotFound::class);

        // Act
        $this->repository->get(ConversationId::generate());
    }

    /**
     * @param list<string> $expectedTitles
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pageProvider')]
    public function testItPagesTheMostRecentlyActiveFirst(int $page, int $perPage, array $expectedTitles): void
    {
        // Arrange
        foreach (['A', 'B', 'C', 'D', 'E'] as $index => $title) {
            $at = '2026-01-0' . ($index + 1) . 'T09:00:00+00:00';
            $this->repository->save(Fixtures::conversation(title: $title, at: $at));
        }

        // Act
        $result = $this->repository->page(new PageRequest($page, $perPage));

        // Assert
        $this->assertSame(5, $result->total);
        $this->assertSame(
            $expectedTitles,
            array_map(static fn ($conversation): string => $conversation->title()->value(), $result->items)
        );
    }

    public function testDeletingAConversationTakesItsMessagesAlong(): void
    {
        // Arrange
        $doomed = Fixtures::conversation();
        $survivor = Fixtures::conversation();
        $this->repository->save($doomed);
        $this->repository->save($survivor);
        $messages = new EloquentMessageRepository();
        $messages->save(Fixtures::userMessage($doomed->id()));
        $messages->save(Fixtures::userMessage($survivor->id()));

        // Act
        $this->repository->delete($doomed->id());

        // Assert
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', ['conversation_id' => $survivor->id()->value()]);
    }
}
