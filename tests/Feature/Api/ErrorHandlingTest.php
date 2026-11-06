<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class ErrorHandlingTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function routingErrorProvider(): iterable
    {
        yield 'unknown path' => ['GET', '/api/does-not-exist', 404, 'not_found'];
        yield 'unknown version' => ['GET', '/api/v2/conversations', 404, 'not_found'];
        yield 'malformed conversation id' => ['GET', '/api/v1/conversations/not-a-uuid', 404, 'not_found'];
        yield 'malformed message id' => [
            'GET',
            '/api/v1/conversations/0191c3a2-7d4b-7c3e-9f61-2b1c8e5d4a70/messages/42',
            404,
            'not_found',
        ];
        yield 'patch is not supported' => ['PATCH', '/api/v1/conversations', 405, 'method_not_allowed'];
        yield 'delete on the collection' => ['DELETE', '/api/v1/conversations', 405, 'method_not_allowed'];
        yield 'post on a single conversation' => [
            'POST',
            '/api/v1/conversations/0191c3a2-7d4b-7c3e-9f61-2b1c8e5d4a70',
            405,
            'method_not_allowed',
        ];
    }

    #[DataProvider('routingErrorProvider')]
    public function testRoutingErrorsUseTheCommonFormat(string $method, string $uri, int $status, string $code): void
    {
        // Arrange - the request comes from the provider

        // Act
        $response = $this->json($method, $uri);

        // Assert
        $response->assertStatus($status)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'code'])
            ->assertJsonPath('status', $status)
            ->assertJsonPath('code', $code);
    }

    public function testMethodNotAllowedListsTheSupportedMethods(): void
    {
        // Arrange - nothing to prepare

        // Act
        $response = $this->json('PATCH', '/api/v1/conversations');

        // Assert
        $response->assertStatus(405);
        $this->assertStringContainsString('POST', (string) $response->headers->get('Allow'));
    }

    public function testErrorsAreJsonEvenWithoutAnAcceptHeader(): void
    {
        // Arrange - a plain request that does not ask for JSON

        // Act
        $response = $this->get('/api/does-not-exist');

        // Assert
        $response->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
    }

    public function testUnexpectedFailuresDoNotLeakInternals(): void
    {
        // Arrange
        config(['app.debug' => false]);
        Route::get('/api/boom', static fn () => throw new RuntimeException('database password is hunter2'));

        // Act
        $response = $this->getJson('/api/boom');

        // Assert
        $response->assertStatus(500)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'internal_error')
            ->assertJsonPath('detail', 'An unexpected error occurred.');
        $this->assertStringNotContainsString('hunter2', (string) $response->getContent());
        $this->assertStringNotContainsString('vendor/', (string) $response->getContent());
    }

    public function testNonApiPathsAreLeftToTheFrameworkDefaults(): void
    {
        // Arrange - nothing to prepare

        // Act
        $response = $this->get('/definitely-not-here');

        // Assert
        $response->assertNotFound();
        $this->assertStringNotContainsString('problem+json', (string) $response->headers->get('Content-Type'));
    }
}
