<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Conversation\Infrastructure;

use App\Core\Domain\Exception\UpstreamFailure;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\ChatCompletionFailed;
use App\Modules\Conversation\Domain\Exception\ChatCompletionTimedOut;
use App\Modules\Conversation\Infrastructure\OpenAi\OpenAiChatCompletionClient;
use App\Modules\Conversation\Infrastructure\OpenAi\OpenAiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;
use Tests\TestCase;

final class OpenAiChatCompletionClientTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, list<array{string, string}>}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'no system prompt' => [null, [['user', 'Hello']]];
        yield 'system prompt goes first' => ['Be brief.', [['system', 'Be brief.'], ['user', 'Hello']]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?int, ?string}>
     */
    public static function completionProvider(): iterable
    {
        yield 'full response' => [
            [
                'model' => 'gpt-4-0613',
                'choices' => [['message' => ['content' => 'Hi!'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 4, 'total_tokens' => 7],
            ],
            7,
            'stop',
        ];
        yield 'no usage and no finish reason' => [
            ['model' => 'gpt-4', 'choices' => [['message' => ['content' => 'Hi!']]]],
            null,
            null,
        ];
        yield 'no model falls back to the requested one' => [
            ['choices' => [['message' => ['content' => 'Hi!'], 'finish_reason' => 'length']]],
            null,
            'length',
        ];
    }

    /**
     * @return iterable<string, array{callable(): mixed, class-string<UpstreamFailure>}>
     */
    public static function failureProvider(): iterable
    {
        yield 'server error' => [static fn () => Http::response('upstream down', 503), ChatCompletionFailed::class];
        yield 'unauthorised' => [
            static fn () => Http::response(['error' => 'bad key'], 401),
            ChatCompletionFailed::class,
        ];
        yield 'no choices' => [static fn () => Http::response(['choices' => []]), ChatCompletionFailed::class];
        yield 'content is not a string' => [
            static fn () => Http::response(['choices' => [['message' => ['content' => null]]]]),
            ChatCompletionFailed::class,
        ];
        yield 'not json at all' => [static fn () => Http::response('<html>', 200), ChatCompletionFailed::class];
        yield 'connection error' => [
            static fn () => throw new ConnectionException('cURL error 6: Could not resolve host'),
            ChatCompletionFailed::class,
        ];
        yield 'curl timeout' => [
            static fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
            ChatCompletionTimedOut::class,
        ];
    }

    /**
     * @param list<array{string, string}> $expectedMessages
     */
    #[DataProvider('payloadProvider')]
    public function testItSendsTheConversationSettingsAndHistory(?string $systemPrompt, array $expectedMessages): void
    {
        // Arrange
        Http::fake(['openai.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);
        $conversation = Fixtures::conversation(model: 'gpt-4', temperature: 0.3, systemPrompt: $systemPrompt);
        $question = Fixtures::userMessage($conversation->id(), 'Hello');

        // Act
        $this->client()->complete($conversation, [$question]);

        // Assert
        Http::assertSent(function (Request $request) use ($expectedMessages): bool {
            return $request->url() === 'https://openai.test/v1/chat/completions'
                && $request->method() === 'POST'
                && $request->header('Authorization') === ['Bearer sk-test']
                && $request['model'] === 'gpt-4'
                && $request['temperature'] === 0.3
                && $request['messages'] === array_map(
                    static fn (array $pair): array => ['role' => $pair[0], 'content' => $pair[1]],
                    $expectedMessages
                );
        });
    }

    public function testItSendsTheOrganizationHeaderOnlyWhenConfigured(): void
    {
        // Arrange
        Http::fake(['openai.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);
        $conversation = Fixtures::conversation();
        $history = [Fixtures::userMessage($conversation->id())];

        // Act
        $this->client(organization: 'org-123')->complete($conversation, $history);
        $this->client(organization: null)->complete($conversation, $history);

        // Assert
        $recorded = Http::recorded();
        $this->assertSame(['org-123'], $recorded[0][0]->header('OpenAI-Organization'));
        $this->assertSame([], $recorded[1][0]->header('OpenAI-Organization'));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('completionProvider')]
    public function testItParsesTheCompletion(array $body, ?int $expectedTokens, ?string $expectedFinishReason): void
    {
        // Arrange
        Http::fake(['openai.test/*' => Http::response($body)]);
        $conversation = Fixtures::conversation(model: 'gpt-4');

        // Act
        $completion = $this->client()->complete($conversation, [Fixtures::userMessage($conversation->id())]);

        // Assert
        $this->assertSame('Hi!', $completion->content->value());
        $this->assertSame($body['model'] ?? 'gpt-4', $completion->metadata->model->value());
        $this->assertSame($expectedTokens, $completion->metadata->usage?->totalTokens);
        $this->assertSame($expectedFinishReason, $completion->metadata->finishReason);
    }

    /**
     * @param callable(): mixed $upstream
     * @param class-string<UpstreamFailure> $expectedException
     */
    #[DataProvider('failureProvider')]
    public function testItTranslatesFailuresIntoDomainErrors(callable $upstream, string $expectedException): void
    {
        // Arrange
        Http::fake(['openai.test/*' => $upstream]);
        $conversation = Fixtures::conversation();
        $this->expectException($expectedException);

        // Act
        $this->client()->complete($conversation, [Fixtures::userMessage($conversation->id())]);
    }

    public function testFailuresCarryDiagnosticsForTheLogsButNotInTheirMessage(): void
    {
        // Arrange
        Http::fake(['openai.test/*' => Http::response('{"error":"quota exceeded"}', 429)]);
        $conversation = Fixtures::conversation();

        // Act
        try {
            $this->client()->complete($conversation, [Fixtures::userMessage($conversation->id())]);
            $this->fail('A ChatCompletionFailed exception was expected.');
        } catch (ChatCompletionFailed $e) {
            // Assert
            $this->assertSame(429, $e->context()['upstream_status']);
            $this->assertStringContainsString('quota exceeded', $e->context()['upstream_body']);
            $this->assertStringNotContainsString('quota', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function missingKeyProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
    }

    #[DataProvider('missingKeyProvider')]
    public function testItDoesNotCallOpenAiWithoutAnApiKey(?string $apiKey): void
    {
        // Arrange
        Http::fake();
        $conversation = Fixtures::conversation(id: ConversationId::generate());
        $this->expectException(ChatCompletionFailed::class);

        // Act
        try {
            $this->client(apiKey: $apiKey)->complete($conversation, [Fixtures::userMessage($conversation->id())]);
        } finally {
            // Assert
            Http::assertNothingSent();
        }
    }

    private function client(?string $apiKey = 'sk-test', ?string $organization = null): OpenAiChatCompletionClient
    {
        return new OpenAiChatCompletionClient(
            $this->app->make(HttpFactory::class),
            new OpenAiSettings($apiKey, 'https://openai.test/v1/', $organization, 5)
        );
    }
}
