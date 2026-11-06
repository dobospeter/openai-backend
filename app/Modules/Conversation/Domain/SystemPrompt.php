<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use Stringable;

/**
 * Instructions given to the model at the start of every completion request.
 */
final class SystemPrompt implements Stringable
{
    public const MAX_LENGTH = 4000;

    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidValue::because('The system prompt must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because(sprintf(
                'The system prompt must not be longer than %d characters.',
                self::MAX_LENGTH
            ));
        }

        $this->value = $value;
    }

    /**
     * Treats null and blank input as "no system prompt".
     */
    public static function fromNullable(?string $value): ?self
    {
        return $value === null || trim($value) === '' ? null : new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
