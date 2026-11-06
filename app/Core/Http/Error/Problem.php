<?php

declare(strict_types=1);

namespace App\Core\Http\Error;

/**
 * An RFC 9457 "problem details" document, the single shape of every error the API returns.
 */
final class Problem
{
    /**
     * @param array<string, list<string>>|null $errors Validation messages keyed by field.
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $code,
        public readonly string $title,
        public readonly string $detail,
        public readonly ?array $errors = null,
        public readonly array $headers = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => 'about:blank',
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'code' => $this->code,
            'errors' => $this->errors,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
