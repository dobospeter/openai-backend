<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use App\Core\Domain\Exception\UpstreamFailure;

/**
 * Port to the chat model provider.
 */
interface ChatCompletionClient
{
    /**
     * @param list<Message> $history Chronological messages the model should answer, last one being the question.
     * @throws UpstreamFailure when the provider cannot produce an answer (ChatCompletionFailed, ChatCompletionTimedOut)
     */
    public function complete(Conversation $conversation, array $history): ChatCompletion;
}
