<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain\Exception;

use App\Core\Domain\Exception\UpstreamFailure;
use Throwable;

final class ChatCompletionFailed extends UpstreamFailure
{
    private const MESSAGE = 'The chat model could not produce an answer.';

    /**
     * @param array<string, mixed> $context
     */
    public static function because(array $context = [], ?Throwable $previous = null): self
    {
        return new self(self::MESSAGE, $context, $previous);
    }

    public function errorCode(): string
    {
        return 'chat_completion_failed';
    }
}
