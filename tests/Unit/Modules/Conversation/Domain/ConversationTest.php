<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixtures;

final class ConversationTest extends TestCase
{
    /**
     * @return iterable<string, array{?string}>
     */
    public static function systemPromptProvider(): iterable
    {
        yield 'with a system prompt' => ['Be brief.'];
        yield 'without a system prompt' => [null];
    }

    #[DataProvider('systemPromptProvider')]
    public function testStartingSetsEverythingAndStampsBothDates(?string $systemPrompt): void
    {
        // Arrange
        $id = ConversationId::generate();
        $now = new DateTimeImmutable('2026-03-01T12:00:00+00:00');

        // Act
        $conversation = Conversation::start(
            $id,
            new ConversationTitle('Trip'),
            new ModelName('gpt-4'),
            new Temperature(1.5),
            SystemPrompt::fromNullable($systemPrompt),
            $now
        );

        // Assert
        $this->assertTrue($id->equals($conversation->id()));
        $this->assertSame('Trip', $conversation->title()->value());
        $this->assertSame('gpt-4', $conversation->model()->value());
        $this->assertSame(1.5, $conversation->temperature()->value());
        $this->assertSame($systemPrompt, $conversation->systemPrompt()?->value());
        $this->assertEquals($now, $conversation->createdAt());
        $this->assertEquals($now, $conversation->updatedAt());
    }

    public function testReconfiguringReplacesTheSettingsAndTouchesUpdatedAt(): void
    {
        // Arrange
        $conversation = Fixtures::conversation(systemPrompt: 'Old prompt', at: '2026-01-01T09:00:00+00:00');
        $later = new DateTimeImmutable('2026-02-01T09:00:00+00:00');

        // Act
        $conversation->reconfigure(
            new ConversationTitle('New title'),
            new ModelName('gpt-3.5-turbo'),
            new Temperature(0.2),
            null,
            $later
        );

        // Assert
        $this->assertSame('New title', $conversation->title()->value());
        $this->assertSame('gpt-3.5-turbo', $conversation->model()->value());
        $this->assertSame(0.2, $conversation->temperature()->value());
        $this->assertNull($conversation->systemPrompt());
        $this->assertEquals($later, $conversation->updatedAt());
        $this->assertEquals(new DateTimeImmutable('2026-01-01T09:00:00+00:00'), $conversation->createdAt());
    }

    public function testRecordingActivityOnlyMovesUpdatedAt(): void
    {
        // Arrange
        $conversation = Fixtures::conversation(title: 'Keep me');
        $later = new DateTimeImmutable('2026-05-05T05:05:05+00:00');

        // Act
        $conversation->recordActivity($later);

        // Assert
        $this->assertEquals($later, $conversation->updatedAt());
        $this->assertSame('Keep me', $conversation->title()->value());
    }
}
