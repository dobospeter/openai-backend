<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\SystemPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemPromptTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function nullableProvider(): iterable
    {
        yield 'null means no prompt' => [null, null];
        yield 'blank means no prompt' => ["  \n", null];
        yield 'text is kept and trimmed' => [' Be brief. ', 'Be brief.'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => ['   '];
        yield 'too long' => [str_repeat('a', 4001)];
    }

    #[DataProvider('nullableProvider')]
    public function testFromNullableTreatsBlankAsAbsent(?string $input, ?string $expected): void
    {
        // Arrange - nothing beyond the provided input

        // Act
        $prompt = SystemPrompt::fromNullable($input);

        // Assert
        $this->assertSame($expected, $prompt?->value());
    }

    #[DataProvider('invalidProvider')]
    public function testItRejectsInvalidPrompts(string $input): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new SystemPrompt($input);
    }
}
