<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Application;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Application\UpdateConversation\UpdateConversationCommand;
use App\Modules\Conversation\Application\UpdateConversation\UpdateConversationHandler;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;

final class UpdateConversationHandlerTest extends ApplicationTestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function systemPromptProvider(): iterable
    {
        yield 'sets a prompt' => ['New prompt', 'New prompt'];
        yield 'null clears the prompt' => [null, null];
        yield 'blank clears the prompt' => ['  ', null];
    }

    /**
     * @return iterable<string, array{string, string, float}>
     */
    public static function invalidSettingsProvider(): iterable
    {
        yield 'blank title' => ['', 'gpt-4', 0.5];
        yield 'invalid model' => ['Title', 'gpt 4', 0.5];
        yield 'temperature below range' => ['Title', 'gpt-4', -1.0];
    }

    #[DataProvider('systemPromptProvider')]
    public function testItReplacesAllSettings(?string $systemPrompt, ?string $expectedPrompt): void
    {
        // Arrange
        $existing = Fixtures::conversation(systemPrompt: 'Old prompt');
        $this->conversations->save($existing);
        $handler = new UpdateConversationHandler($this->conversations, $this->clock);
        $id = $existing->id()->value();
        $command = new UpdateConversationCommand($id, 'Renamed', 'gpt-3.5-turbo', 0.1, $systemPrompt);

        // Act
        $updated = $handler->handle($command);

        // Assert
        $stored = $this->conversations->get($existing->id());
        $this->assertSame($stored, $updated);
        $this->assertSame('Renamed', $stored->title()->value());
        $this->assertSame('gpt-3.5-turbo', $stored->model()->value());
        $this->assertSame(0.1, $stored->temperature()->value());
        $this->assertSame($expectedPrompt, $stored->systemPrompt()?->value());
        $this->assertSame('2026-06-01T10:00:00+00:00', $stored->updatedAt()->format(DATE_ATOM));
    }

    public function testItFailsForAnUnknownConversation(): void
    {
        // Arrange
        $handler = new UpdateConversationHandler($this->conversations, $this->clock);
        $command = new UpdateConversationCommand(ConversationId::generate()->value(), 'Title', 'gpt-4', 0.5, null);
        $this->expectException(ConversationNotFound::class);

        // Act
        $handler->handle($command);
    }

    #[DataProvider('invalidSettingsProvider')]
    public function testItKeepsTheConversationUnchangedWhenSettingsAreInvalid(
        string $title,
        string $model,
        float $temperature
    ): void {
        // Arrange
        $existing = Fixtures::conversation(title: 'Original');
        $this->conversations->save($existing);
        $handler = new UpdateConversationHandler($this->conversations, $this->clock);

        // Act
        try {
            $id = $existing->id()->value();
            $handler->handle(new UpdateConversationCommand($id, $title, $model, $temperature, null));
            $this->fail('An InvalidValue exception was expected.');
        } catch (InvalidValue) {
            // Assert
            $this->assertSame('Original', $this->conversations->get($existing->id())->title()->value());
        }
    }
}
