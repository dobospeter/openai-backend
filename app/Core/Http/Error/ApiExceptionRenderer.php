<?php

declare(strict_types=1);

namespace App\Core\Http\Error;

use App\Core\Domain\Exception\InvalidValue;
use App\Core\Domain\Exception\NotFound;
use App\Core\Domain\Exception\UpstreamFailure;
use App\Core\Domain\Exception\UpstreamTimeout;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception into the one error format the API speaks (application/problem+json).
 */
final class ApiExceptionRenderer
{
    private const CONTENT_TYPE = 'application/problem+json';

    public function __construct(private readonly bool $debug)
    {
    }

    public function render(Throwable $e): JsonResponse
    {
        $problem = $this->toProblem($e);

        return new JsonResponse(
            $problem->toArray(),
            $problem->status,
            [...$problem->headers, 'Content-Type' => self::CONTENT_TYPE]
        );
    }

    public function toProblem(Throwable $e): Problem
    {
        return match (true) {
            $e instanceof ValidationException => $this->problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'validation_failed',
                'The request data is invalid.',
                $e->errors()
            ),
            $e instanceof NotFound => $this->problem(Response::HTTP_NOT_FOUND, $e->errorCode(), $e->getMessage()),
            $e instanceof InvalidValue => $this->problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $e->errorCode(),
                $e->getMessage()
            ),
            $e instanceof UpstreamTimeout => $this->problem(
                Response::HTTP_GATEWAY_TIMEOUT,
                $e->errorCode(),
                $e->getMessage()
            ),
            $e instanceof UpstreamFailure => $this->problem(
                Response::HTTP_BAD_GATEWAY,
                $e->errorCode(),
                $e->getMessage()
            ),
            $e instanceof AuthenticationException => $this->problem(
                Response::HTTP_UNAUTHORIZED,
                'unauthenticated',
                'Authentication is required.'
            ),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => $this->problem(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'internal_error',
                $this->debug ? $e->getMessage() : 'An unexpected error occurred.'
            ),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): Problem
    {
        $status = $e->getStatusCode();
        $title = Response::$statusTexts[$status] ?? 'Error';

        return $this->problem(
            $status,
            Str::snake($title),
            $e->getMessage() !== '' ? $e->getMessage() : $title,
            null,
            $e->getHeaders()
        );
    }

    /**
     * @param array<string, list<string>>|null $errors
     * @param array<string, string> $headers
     */
    private function problem(
        int $status,
        string $code,
        string $detail,
        ?array $errors = null,
        array $headers = []
    ): Problem {
        return new Problem($status, $code, Response::$statusTexts[$status] ?? 'Error', $detail, $errors, $headers);
    }
}
