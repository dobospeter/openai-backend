<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use Stringable;

/**
 * Name of the chat model that answers in a conversation (e.g. "gpt-4").
 */
final class ModelName implements Stringable
{
    public const MAX_LENGTH = 100;

    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH || preg_match('/\s/', $value) === 1) {
            throw InvalidValue::because(sprintf(
                'The model name must be 1-%d characters long and contain no whitespace.',
                self::MAX_LENGTH
            ));
        }

        $this->value = $value;
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
