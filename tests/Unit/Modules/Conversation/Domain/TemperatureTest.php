<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\Temperature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemperatureTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float}>
     */
    public static function validProvider(): iterable
    {
        yield 'lower bound' => [0.0, 0.0];
        yield 'typical' => [0.7, 0.7];
        yield 'upper bound' => [2.0, 2.0];
        yield 'rounded to two decimals' => [0.12345, 0.12];
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'below range' => [-0.01];
        yield 'above range' => [2.01];
        yield 'not a number' => [NAN];
        yield 'infinity' => [INF];
    }

    #[DataProvider('validProvider')]
    public function testItAcceptsValuesWithinRange(float $input, float $expected): void
    {
        // Arrange - nothing beyond the provided input

        // Act
        $temperature = new Temperature($input);

        // Assert
        $this->assertSame($expected, $temperature->value());
    }

    #[DataProvider('invalidProvider')]
    public function testItRejectsValuesOutsideTheRange(float $input): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new Temperature($input);
    }
}
