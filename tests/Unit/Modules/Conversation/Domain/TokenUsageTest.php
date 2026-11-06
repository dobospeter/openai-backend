<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\TokenUsage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenUsageTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function negativeProvider(): iterable
    {
        yield 'negative prompt tokens' => [-1, 0, 0];
        yield 'negative completion tokens' => [0, -1, 0];
        yield 'negative total' => [0, 0, -1];
    }

    public function testItHoldsTheCounts(): void
    {
        // Arrange & Act
        $usage = new TokenUsage(10, 20, 30);

        // Assert
        $this->assertSame(10, $usage->promptTokens);
        $this->assertSame(20, $usage->completionTokens);
        $this->assertSame(30, $usage->totalTokens);
    }

    #[DataProvider('negativeProvider')]
    public function testItRejectsNegativeCounts(int $prompt, int $completion, int $total): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new TokenUsage($prompt, $completion, $total);
    }
}
