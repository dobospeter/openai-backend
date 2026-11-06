<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Docs;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

final class DocumentationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function documentUrlProvider(): iterable
    {
        yield 'swagger ui' => ['/docs'];
        yield 'openapi document' => ['/docs/openapi.yaml'];
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function rejectedCredentialsProvider(): iterable
    {
        yield 'no credentials' => [null, null];
        yield 'wrong password' => ['docs', 'wrong'];
        yield 'wrong user' => ['admin', 's3cret'];
        yield 'both wrong' => ['admin', 'wrong'];
        yield 'password of a different case' => ['docs', 'S3CRET'];
        yield 'empty credentials' => ['', ''];
        yield 'password only' => ['', 's3cret'];
        yield 'user only' => ['docs', ''];
    }

    #[DataProvider('documentUrlProvider')]
    public function testItAcceptsTheConfiguredCredentials(string $url): void
    {
        // Arrange
        $headers = $this->basicAuth('docs', 's3cret');

        // Act
        $response = $this->get($url, $headers);

        // Assert
        $response->assertOk();
    }

    /**
     * @param ?string $username
     * @param ?string $password
     */
    #[DataProvider('rejectedCredentialsProvider')]
    public function testItRejectsAnythingElseWithABasicChallenge(?string $username, ?string $password): void
    {
        // Arrange
        $headers = $username === null ? [] : $this->basicAuth($username, (string) $password);

        // Act
        $responses = [$this->get('/docs', $headers), $this->get('/docs/openapi.yaml', $headers)];

        // Assert
        foreach ($responses as $response) {
            $response->assertUnauthorized();
            $this->assertStringStartsWith('Basic realm=', (string) $response->headers->get('WWW-Authenticate'));
            $this->assertStringNotContainsString('openapi:', (string) $response->getContent());
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unconfiguredPasswordProvider(): iterable
    {
        yield 'password not set' => [null];
        yield 'password empty' => [''];
    }

    #[DataProvider('unconfiguredPasswordProvider')]
    public function testItStaysLockedWhileNoPasswordIsConfigured(?string $password): void
    {
        // Arrange
        config(['docs.password' => $password]);

        // Act
        $withEmptyPassword = $this->get('/docs', $this->basicAuth('docs', ''));
        $withoutCredentials = $this->get('/docs');

        // Assert
        $withEmptyPassword->assertUnauthorized();
        $withoutCredentials->assertUnauthorized();
    }

    public function testTheSwaggerUiPointsAtTheProtectedSpecification(): void
    {
        // Arrange
        $headers = $this->basicAuth('docs', 's3cret');

        // Act
        $response = $this->get('/docs', $headers);

        // Assert
        $response->assertOk()
            ->assertSee('SwaggerUIBundle', false)
            ->assertSee('openapi.yaml', false);
    }

    public function testTheSpecificationIsServedAsYaml(): void
    {
        // Arrange
        $headers = $this->basicAuth('docs', 's3cret');

        // Act
        $response = $this->get('/docs/openapi.yaml', $headers);

        // Assert
        $response->assertOk()->assertHeader('Content-Type', 'application/yaml');
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);
        $document = (string) file_get_contents($file->getFile()->getPathname());
        $this->assertStringStartsWith('openapi: 3.0.3', $document);
    }

    /**
     * @return array<string, string>
     */
    private function basicAuth(string $username, string $password): array
    {
        return ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)];
    }
}
