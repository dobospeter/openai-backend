<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\InvalidValue;
use Stringable;

final class MessageContent implements Stringable
{
    private readonly string $value;

    public function __construct(string $value)
    {
        if (trim($value) === '') {
            throw InvalidValue::because('The message content must not be blank.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function length(): int
    {
        return mb_strlen($this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
