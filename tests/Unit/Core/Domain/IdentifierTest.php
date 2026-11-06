<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\MessageId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validUuidProvider(): iterable
    {
        yield 'lower case' => ['0191c3a2-7d4b-7c3e-9f61-2b1c8e5d4a70', '0191c3a2-7d4b-7c3e-9f61-2b1c8e5d4a70'];
        yield 'upper case is normalised' => [
            '0191C3A2-7D4B-7C3E-9F61-2B1C8E5D4A70',
            '0191c3a2-7d4b-7c3e-9f61-2b1c8e5d4a70',
        ];
        yield 'uuid v4' => ['f47ac10b-58cc-4372-a567-0e02b2c3d479', 'f47ac10b-58cc-4372-a567-0e02b2c3d479'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUuidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'words' => ['not-a-uuid'];
        yield 'too short' => ['0191c3a2-7d4b-7c3e-9f61'];
        yield 'non hex characters' => ['zzzzzzzz-7d4b-7c3e-9f61-2b1c8e5d4a70'];
        yield 'integer' => ['42'];
    }

    #[DataProvider('validUuidProvider')]
    public function testItAcceptsValidUuids(string $input, string $expected): void
    {
        // Arrange - nothing beyond the provided input

        // Act
        $id = ConversationId::fromString($input);

        // Assert
        $this->assertSame($expected, $id->value());
        $this->assertSame($expected, (string) $id);
    }

    #[DataProvider('invalidUuidProvider')]
    public function testItRejectsInvalidUuids(string $input): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        ConversationId::fromString($input);
    }

    public function testGeneratedIdsAreValidAndUnique(): void
    {
        // Arrange & Act
        $first = ConversationId::generate();
        $second = ConversationId::generate();

        // Assert
        $this->assertSame($first->value(), ConversationId::fromString($first->value())->value());
        $this->assertFalse($first->equals($second));
    }

    public function testGeneratedIdsSortByCreationTime(): void
    {
        // Arrange
        $earlier = MessageId::generate();
        usleep(2000);
        $later = MessageId::generate();

        // Act
        $sorted = [$later->value(), $earlier->value()];
        sort($sorted);

        // Assert
        $this->assertSame([$earlier->value(), $later->value()], $sorted);
    }

    public function testIdsAreEqualOnlyWhenTypeAndValueMatch(): void
    {
        // Arrange
        $value = '0191c3a2-7d4b-7c3e-9f61-2b1c8e5d4a70';

        // Act & Assert
        $this->assertTrue(ConversationId::fromString($value)->equals(ConversationId::fromString($value)));
        $this->assertFalse(ConversationId::fromString($value)->equals(MessageId::fromString($value)));
    }
}
