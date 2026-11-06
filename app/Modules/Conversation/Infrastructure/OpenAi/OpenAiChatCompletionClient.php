<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\OpenAi;

use App\Core\Domain\Exception\InvalidValue;
use App\Modules\Conversation\Domain\ChatCompletion;
use App\Modules\Conversation\Domain\ChatCompletionClient;
use App\Modules\Conversation\Domain\CompletionMetadata;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\Exception\ChatCompletionFailed;
use App\Modules\Conversation\Domain\Exception\ChatCompletionTimedOut;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageContent;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\TokenUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Chat completions through OpenAI's REST API (POST /chat/completions).
 */
final class OpenAiChatCompletionClient implements ChatCompletionClient
{
    private const CURL_TIMEOUT_ERRNO = 28;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly OpenAiSettings $settings
    ) {
    }

    public function complete(Conversation $conversation, array $history): ChatCompletion
    {
        if ($this->settings->apiKey === null || $this->settings->apiKey === '') {
            throw ChatCompletionFailed::because(['reason' => 'OPENAI_API_KEY is not configured.']);
        }

        $response = $this->send($this->payload($conversation, $history));

        if ($response->failed()) {
            throw ChatCompletionFailed::because([
                'upstream_status' => $response->status(),
                'upstream_body' => $response->body(),
            ]);
        }

        return $this->toCompletion($response, $conversation);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload): Response
    {
        try {
            return $this->request()->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            $context = ['reason' => $e->getMessage()];

            throw $this->isTimeout($e)
                ? ChatCompletionTimedOut::afterWaiting($context, $e)
                : ChatCompletionFailed::because($context, $e);
        }
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->baseUrl(rtrim($this->settings->baseUrl, '/'))
            ->withToken((string) $this->settings->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->settings->timeoutSeconds);

        if ($this->settings->organization !== null && $this->settings->organization !== '') {
            $request->withHeaders(['OpenAI-Organization' => $this->settings->organization]);
        }

        return $request;
    }

    /**
     * @param list<Message> $history
     * @return array<string, mixed>
     */
    private function payload(Conversation $conversation, array $history): array
    {
        $messages = array_map(
            static fn (Message $message): array => [
                'role' => $message->role()->value,
                'content' => $message->content()->value(),
            ],
            $history
        );

        if ($conversation->systemPrompt() !== null) {
            array_unshift($messages, ['role' => 'system', 'content' => $conversation->systemPrompt()->value()]);
        }

        return [
            'model' => $conversation->model()->value(),
            'temperature' => $conversation->temperature()->value(),
            'messages' => $messages,
        ];
    }

    private function toCompletion(Response $response, Conversation $conversation): ChatCompletion
    {
        $content = $response->json('choices.0.message.content');

        try {
            $answer = new MessageContent(is_string($content) ? $content : '');
        } catch (InvalidValue $e) {
            throw ChatCompletionFailed::because(
                ['reason' => 'The answer is empty.', 'upstream_body' => $response->body()],
                $e
            );
        }

        $model = $response->json('model');
        $usage = $response->json('usage');

        return new ChatCompletion($answer, new CompletionMetadata(
            is_string($model) && $model !== '' ? new ModelName($model) : $conversation->model(),
            is_array($usage) ? $this->toUsage($usage) : null,
            $this->stringOrNull($response->json('choices.0.finish_reason'))
        ));
    }

    /**
     * @param array<string, mixed> $usage
     */
    private function toUsage(array $usage): TokenUsage
    {
        return new TokenUsage(
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
            (int) ($usage['total_tokens'] ?? 0)
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function isTimeout(ConnectionException $e): bool
    {
        $previous = $e->getPrevious();

        if (
            $previous instanceof \GuzzleHttp\Exception\ConnectException
            && ($previous->getHandlerContext()['errno'] ?? null) === self::CURL_TIMEOUT_ERRNO
        ) {
            return true;
        }

        return stripos($e->getMessage(), 'timed out') !== false;
    }
}
