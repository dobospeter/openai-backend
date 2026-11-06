<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\ConversationTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConversationTitleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validProvider(): iterable
    {
        yield 'plain' => ['Trip planning', 'Trip planning'];
        yield 'surrounding whitespace is trimmed' => ["  Trip \n", 'Trip'];
        yield 'multibyte characters' => ['Árvíztűrő tükörfúrógép', 'Árvíztűrő tükörfúrógép'];
        yield 'maximum length' => [str_repeat('a', 255), str_repeat('a', 255)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => ["  \t\n"];
        yield 'one character too long' => [str_repeat('a', 256)];
        yield 'too long multibyte' => [str_repeat('é', 256)];
    }

    #[DataProvider('validProvider')]
    public function testItAcceptsValidTitles(string $input, string $expected): void
    {
        // Arrange - nothing beyond the provided input

        // Act
        $title = new ConversationTitle($input);

        // Assert
        $this->assertSame($expected, $title->value());
    }

    #[DataProvider('invalidProvider')]
    public function testItRejectsInvalidTitles(string $input): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new ConversationTitle($input);
    }

    public function testUntitledProvidesADefault(): void
    {
        // Arrange & Act
        $title = ConversationTitle::untitled();

        // Assert
        $this->assertSame('Untitled conversation', (string) $title);
    }
}
