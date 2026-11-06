<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain\Exception;

use App\Core\Domain\Exception\UpstreamTimeout;
use Throwable;

final class ChatCompletionTimedOut extends UpstreamTimeout
{
    /**
     * @param array<string, mixed> $context
     */
    public static function afterWaiting(array $context = [], ?Throwable $previous = null): self
    {
        return new self('The chat model did not answer in time.', $context, $previous);
    }

    public function errorCode(): string
    {
        return 'chat_completion_timeout';
    }
}
