<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\MessageContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageContentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validProvider(): iterable
    {
        yield 'single character' => ['a', 1];
        yield 'keeps inner and outer whitespace' => [" hello \n world ", 15];
        yield 'counts characters, not bytes' => ['ééé', 3];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'newlines and tabs' => ["\n\t\n"];
    }

    #[DataProvider('validProvider')]
    public function testItKeepsTheContentAsIs(string $input, int $length): void
    {
        // Arrange - nothing beyond the provided input

        // Act
        $content = new MessageContent($input);

        // Assert
        $this->assertSame($input, $content->value());
        $this->assertSame($length, $content->length());
    }

    #[DataProvider('blankProvider')]
    public function testItRejectsBlankContent(string $input): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new MessageContent($input);
    }
}
