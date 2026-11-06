<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Http;

use App\Core\Domain\Exception\InvalidValue;
use App\Core\Http\Error\ApiExceptionRenderer;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\ChatCompletionFailed;
use App\Modules\Conversation\Domain\Exception\ChatCompletionTimedOut;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;
use Throwable;

final class ApiExceptionRendererTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): Throwable, int, string}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'entity not found' => [
            static fn () => ConversationNotFound::withId(ConversationId::generate()),
            404,
            'conversation_not_found',
        ];
        yield 'domain invariant violated' => [static fn () => InvalidValue::because('Too long.'), 422, 'invalid_value'];
        yield 'validation failed' => [
            static fn () => ValidationException::withMessages(['title' => ['The title is required.']]),
            422,
            'validation_failed',
        ];
        yield 'upstream failed' => [static fn () => ChatCompletionFailed::because(), 502, 'chat_completion_failed'];
        yield 'upstream timed out' => [
            static fn () => ChatCompletionTimedOut::afterWaiting(),
            504,
            'chat_completion_timeout',
        ];
        yield 'unauthenticated' => [static fn () => new AuthenticationException(), 401, 'unauthenticated'];
        yield 'unknown route' => [static fn () => new NotFoundHttpException('No such route.'), 404, 'not_found'];
        yield 'method not allowed' => [
            static fn () => new MethodNotAllowedHttpException(['GET', 'POST']),
            405,
            'method_not_allowed',
        ];
        yield 'rate limited' => [static fn () => new TooManyRequestsHttpException(30), 429, 'too_many_requests'];
        yield 'unexpected failure' => [static fn () => new LogicException('secret detail'), 500, 'internal_error'];
    }

    #[DataProvider('exceptionProvider')]
    public function testItMapsExceptionsToProblemDetails(Closure $makeException, int $status, string $code): void
    {
        // Arrange
        $renderer = new ApiExceptionRenderer(false);

        // Act
        $response = $renderer->render($makeException());
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // Assert
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertSame($code, $body['code']);
        $this->assertSame($status, $body['status']);
        $this->assertSame('about:blank', $body['type']);
        $this->assertIsString($body['title']);
        $this->assertIsString($body['detail']);
    }

    public function testValidationProblemsListTheErrorsPerField(): void
    {
        // Arrange
        $exception = ValidationException::withMessages(['title' => ['Too long.'], 'model' => ['Unknown.']]);

        // Act
        $body = json_decode(
            (string) (new ApiExceptionRenderer(false))->render($exception)->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        // Assert
        $this->assertSame(['title' => ['Too long.'], 'model' => ['Unknown.']], $body['errors']);
    }

    public function testHttpExceptionHeadersAreKept(): void
    {
        // Arrange
        $exception = new TooManyRequestsHttpException(30);

        // Act
        $response = (new ApiExceptionRenderer(false))->render($exception);

        // Assert
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function debugProvider(): iterable
    {
        yield 'debug off hides the message' => [false, 'An unexpected error occurred.'];
        yield 'debug on shows the message' => [true, 'secret detail'];
    }

    #[DataProvider('debugProvider')]
    public function testInternalErrorsOnlyExposeDetailsInDebugMode(bool $debug, string $expectedDetail): void
    {
        // Arrange
        $renderer = new ApiExceptionRenderer($debug);

        // Act
        $body = json_decode(
            (string) $renderer->render(new LogicException('secret detail'))->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        // Assert
        $this->assertSame($expectedDetail, $body['detail']);
    }

    public function testUpstreamFailureDoesNotLeakItsContext(): void
    {
        // Arrange
        $exception = ChatCompletionFailed::because(['upstream_body' => 'sk-secret-key']);

        // Act
        $content = (string) (new ApiExceptionRenderer(false))->render($exception)->getContent();

        // Assert
        $this->assertStringNotContainsString('sk-secret-key', $content);
    }
}
