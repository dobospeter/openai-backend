<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Application;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Application\CreateConversation\CreateConversationCommand;
use App\Modules\Conversation\Application\CreateConversation\CreateConversationHandler;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateConversationHandlerTest extends ApplicationTestCase
{
    /**
     * @return iterable<string, array{CreateConversationCommand, array{string, string, float, ?string}}>
     */
    public static function commandProvider(): iterable
    {
        yield 'nothing given falls back to the defaults' => [
            new CreateConversationCommand(),
            ['Untitled conversation', 'gpt-3.5-turbo', 0.7, null],
        ];
        yield 'everything given' => [
            new CreateConversationCommand('Trip', 'gpt-4', 1.2, 'Be brief.'),
            ['Trip', 'gpt-4', 1.2, 'Be brief.'],
        ];
        yield 'blank system prompt means none' => [
            new CreateConversationCommand('Trip', null, null, '   '),
            ['Trip', 'gpt-3.5-turbo', 0.7, null],
        ];
        yield 'zero temperature is kept, not defaulted' => [
            new CreateConversationCommand(null, null, 0.0, null),
            ['Untitled conversation', 'gpt-3.5-turbo', 0.0, null],
        ];
    }

    /**
     * @return iterable<string, array{CreateConversationCommand}>
     */
    public static function invalidCommandProvider(): iterable
    {
        yield 'blank title' => [new CreateConversationCommand('   ')];
        yield 'title too long' => [new CreateConversationCommand(str_repeat('t', 256))];
        yield 'model with whitespace' => [new CreateConversationCommand(null, 'gpt 4')];
        yield 'temperature too high' => [new CreateConversationCommand(null, null, 2.5)];
        yield 'system prompt too long' => [new CreateConversationCommand(null, null, null, str_repeat('p', 4001))];
    }

    /**
     * @param array{string, string, float, ?string} $expected title, model, temperature, system prompt
     */
    #[DataProvider('commandProvider')]
    public function testItCreatesAndStoresTheConversation(CreateConversationCommand $command, array $expected): void
    {
        // Arrange
        $handler = new CreateConversationHandler($this->conversations, $this->defaults, $this->clock);

        // Act
        $conversation = $handler->handle($command);

        // Assert
        [$title, $model, $temperature, $systemPrompt] = $expected;
        $this->assertSame($title, $conversation->title()->value());
        $this->assertSame($model, $conversation->model()->value());
        $this->assertSame($temperature, $conversation->temperature()->value());
        $this->assertSame($systemPrompt, $conversation->systemPrompt()?->value());
        $this->assertSame($conversation, $this->conversations->get($conversation->id()));
    }

    public function testItStampsCreationTimeFromTheClock(): void
    {
        // Arrange
        $handler = new CreateConversationHandler($this->conversations, $this->defaults, $this->clock);

        // Act
        $conversation = $handler->handle(new CreateConversationCommand());

        // Assert
        $this->assertSame('2026-06-01T10:00:00+00:00', $conversation->createdAt()->format(DATE_ATOM));
        $this->assertEquals($conversation->createdAt(), $conversation->updatedAt());
    }

    #[DataProvider('invalidCommandProvider')]
    public function testItRejectsInvalidSettingsAndStoresNothing(CreateConversationCommand $command): void
    {
        // Arrange
        $handler = new CreateConversationHandler($this->conversations, $this->defaults, $this->clock);

        // Act
        try {
            $handler->handle($command);
            $this->fail('An InvalidValue exception was expected.');
        } catch (InvalidValue) {
            // Assert
            $this->assertSame(0, $this->conversations->count());
        }
    }

    public function testEveryConversationGetsItsOwnId(): void
    {
        // Arrange
        $handler = new CreateConversationHandler($this->conversations, $this->defaults, $this->clock);

        // Act
        $first = $handler->handle(new CreateConversationCommand());
        $second = $handler->handle(new CreateConversationCommand());

        // Assert
        $this->assertFalse($first->id()->equals($second->id()));
        $this->assertSame(2, $this->conversations->count());
    }
}
