<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\ModelName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModelNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validProvider(): iterable
    {
        yield 'simple' => ['gpt-4'];
        yield 'dated snapshot' => ['gpt-4-0613'];
        yield 'maximum length' => [str_repeat('m', 100)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'contains whitespace' => ['gpt 4'];
        yield 'too long' => [str_repeat('m', 101)];
    }

    #[DataProvider('validProvider')]
    public function testItAcceptsValidNames(string $input): void
    {
        // Arrange - nothing beyond the provided input

        // Act
        $name = new ModelName($input);

        // Assert
        $this->assertSame($input, $name->value());
    }

    #[DataProvider('invalidProvider')]
    public function testItRejectsInvalidNames(string $input): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new ModelName($input);
    }
}
