<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Conversation\Domain\ChatCompletion;
use App\Modules\Conversation\Domain\ChatCompletionClient;
use App\Modules\Conversation\Domain\CompletionMetadata;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\MessageContent;
use App\Modules\Conversation\Domain\ModelName;
use Throwable;

final class FakeChatCompletionClient implements ChatCompletionClient
{
    /** @var list<array{conversation: Conversation, history: list<\App\Modules\Conversation\Domain\Message>}> */
    public array $calls = [];

    public function __construct(
        private readonly string $answer = 'Hello from the model',
        private readonly ?Throwable $failure = null
    ) {
    }

    public static function failingWith(Throwable $failure): self
    {
        return new self(failure: $failure);
    }

    public function complete(Conversation $conversation, array $history): ChatCompletion
    {
        $this->calls[] = ['conversation' => $conversation, 'history' => $history];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ChatCompletion(
            new MessageContent($this->answer),
            new CompletionMetadata(new ModelName('fake-model'), null, 'stop')
        );
    }
}
