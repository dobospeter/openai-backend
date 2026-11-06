<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Domain;

use App\Core\Domain\Exception\InvalidValue;
use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageRequestTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function offsetProvider(): iterable
    {
        yield 'first page' => [1, 15, 0];
        yield 'second page' => [2, 15, 15];
        yield 'custom size' => [4, 10, 30];
        yield 'maximum size' => [3, 100, 200];
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'page zero' => [0, 15];
        yield 'negative page' => [-1, 15];
        yield 'page size zero' => [1, 0];
        yield 'page size above maximum' => [1, 101];
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function totalPagesProvider(): iterable
    {
        yield 'empty result still has one page' => [0, 15, 1];
        yield 'exactly one full page' => [15, 15, 1];
        yield 'one item over' => [16, 15, 2];
        yield 'many pages' => [101, 10, 11];
    }

    #[DataProvider('offsetProvider')]
    public function testItCalculatesTheOffset(int $page, int $perPage, int $expected): void
    {
        // Arrange
        $request = new PageRequest($page, $perPage);

        // Act
        $offset = $request->offset();

        // Assert
        $this->assertSame($expected, $offset);
    }

    #[DataProvider('invalidProvider')]
    public function testItRejectsOutOfRangeValues(int $page, int $perPage): void
    {
        // Arrange
        $this->expectException(InvalidValue::class);

        // Act
        new PageRequest($page, $perPage);
    }

    #[DataProvider('totalPagesProvider')]
    public function testPageCountsTotalPages(int $total, int $perPage, int $expected): void
    {
        // Arrange
        $page = new Page([], $total, new PageRequest(1, $perPage));

        // Act
        $totalPages = $page->totalPages();

        // Assert
        $this->assertSame($expected, $totalPages);
    }
}
