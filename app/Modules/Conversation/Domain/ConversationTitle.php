<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use Stringable;

final class ConversationTitle implements Stringable
{
    public const MAX_LENGTH = 255;
    private const UNTITLED = 'Untitled conversation';

    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidValue::because('The conversation title must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because(sprintf(
                'The conversation title must not be longer than %d characters.',
                self::MAX_LENGTH
            ));
        }

        $this->value = $value;
    }

    public static function untitled(): self
    {
        return new self(self::UNTITLED);
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
