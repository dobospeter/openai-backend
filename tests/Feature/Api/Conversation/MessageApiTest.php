<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Conversation;

use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\MessageId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\ApiTestCase;

final class MessageApiTest extends ApiTestCase
{
    private const COLLECTION = '/v1/conversations/{conversationId}/messages';
    private const ITEM = '/v1/conversations/{conversationId}/messages/{messageId}';

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidContentProvider(): iterable
    {
        yield 'missing' => [''];
        yield 'too long' => [str_repeat('a', 2001)];
    }

    /**
     * Upstream behaviours that must surface as 502 / 504 and leave no trace in the database.
     *
     * @return iterable<string, array{callable(): mixed, int, string}>
     */
    public static function upstreamFailureProvider(): iterable
    {
        yield 'OpenAI answers with a server error' => [
            static fn () => Http::response(['error' => ['message' => 'boom']], 500),
            502,
            'chat_completion_failed',
        ];
        yield 'OpenAI rejects the request' => [
            static fn () => Http::response(['error' => ['message' => 'bad key']], 401),
            502,
            'chat_completion_failed',
        ];
        yield 'OpenAI rate limits us' => [
            static fn () => Http::response(['error' => ['message' => 'slow down']], 429),
            502,
            'chat_completion_failed',
        ];
        yield 'answer without choices' => [
            static fn () => Http::response(['choices' => []]),
            502,
            'chat_completion_failed',
        ];
        yield 'blank answer' => [
            static fn () => Http::response(['choices' => [['message' => ['content' => '  ']]]]),
            502,
            'chat_completion_failed',
        ];
        yield 'connection refused' => [
            static fn () => throw new ConnectionException('cURL error 7: Failed to connect'),
            502,
            'chat_completion_failed',
        ];
        yield 'request timed out' => [
            static fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30001 ms'),
            504,
            'chat_completion_timeout',
        ];
    }

    public function testSendingAMessageStoresAndReturnsTheQuestionAndTheAnswer(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer('Lisbon is lovely.');
        $conversation = $this->createConversation([
            'model' => 'gpt-4',
            'temperature' => 0.4,
            'system_prompt' => 'Be brief.',
        ]);
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';

        // Act
        $response = $this->postJson($url, ['content' => 'Where should I go?']);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.user_message.content', 'Where should I go?')
            ->assertJsonPath('data.user_message.completion', null)
            ->assertJsonPath('data.assistant_message.role', 'assistant')
            ->assertJsonPath('data.assistant_message.content', 'Lisbon is lovely.')
            ->assertJsonPath('data.assistant_message.completion.model', 'gpt-4-0613')
            ->assertJsonPath('data.assistant_message.completion.finish_reason', 'stop')
            ->assertJsonPath('data.assistant_message.completion.usage.total_tokens', 20);
        $response->assertHeader('Location', url($url . '/' . $response->json('data.assistant_message.id')));
        $this->assertDatabaseCount('messages', 2);
        $this->assertMatchesContract($response, self::COLLECTION, 'post');
    }

    public function testTheModelIsAskedWithTheConversationSettingsSystemPromptAndHistory(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer('First answer');
        $conversation = $this->createConversation([
            'model' => 'gpt-4',
            'temperature' => 0.4,
            'system_prompt' => 'Be brief.',
        ]);
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';
        $this->postJson($url, ['content' => 'First question'])->assertCreated();

        // Act
        $this->postJson($url, ['content' => 'Second question'])->assertCreated();

        // Assert
        $second = $this->sentToOpenAi()[1];
        $this->assertSame('https://openai.test/v1/chat/completions', $second->url());
        $this->assertSame('Bearer test-key', $second->header('Authorization')[0]);
        $this->assertSame('gpt-4', $second['model']);
        $this->assertSame(0.4, $second['temperature']);
        $this->assertSame(
            [
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'First question'],
                ['role' => 'assistant', 'content' => 'First answer'],
                ['role' => 'user', 'content' => 'Second question'],
            ],
            $second['messages']
        );
    }

    #[DataProvider('invalidContentProvider')]
    public function testSendingInvalidContentIsRejectedWithoutCallingOpenAi(string $content): void
    {
        // Arrange
        Http::fake();
        $conversation = $this->createConversation();
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';

        // Act
        $response = $this->postJson($url, ['content' => $content]);

        // Assert
        $response->assertUnprocessable()->assertJsonValidationErrors('content');
        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 0);
        $this->assertMatchesContract($response, self::COLLECTION, 'post');
    }

    public function testSendingToAnUnknownConversationIsNotFound(): void
    {
        // Arrange
        Http::fake();
        $url = self::CONVERSATIONS . '/' . ConversationId::generate() . '/messages';

        // Act
        $response = $this->postJson($url, ['content' => 'Hi']);

        // Assert
        $response->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        Http::assertNothingSent();
        $this->assertMatchesContract($response, self::COLLECTION, 'post');
    }

    /**
     * @param callable(): mixed $upstream
     */
    #[DataProvider('upstreamFailureProvider')]
    public function testUpstreamFailuresAreReportedAndLeaveNothingBehind(
        callable $upstream,
        int $expectedStatus,
        string $expectedCode
    ): void {
        // Arrange
        Http::fake(['openai.test/*' => $upstream]);
        $conversation = $this->createConversation();

        // Act
        $response = $this->postJson(self::CONVERSATIONS . '/' . $conversation['id'] . '/messages', ['content' => 'Hi']);

        // Assert
        $response->assertStatus($expectedStatus)->assertJsonPath('code', $expectedCode);
        $this->assertDatabaseCount('messages', 0);
        $this->assertStringNotContainsString('boom', (string) $response->getContent());
        $this->assertMatchesContract($response, self::COLLECTION, 'post');
    }

    public function testAMissingApiKeyIsReportedAsAnUpstreamFailure(): void
    {
        // Arrange
        config(['openai.api_key' => null]);
        Http::fake();
        $conversation = $this->createConversation();

        // Act
        $response = $this->postJson(self::CONVERSATIONS . '/' . $conversation['id'] . '/messages', ['content' => 'Hi']);

        // Assert
        $response->assertStatus(502)->assertJsonPath('code', 'chat_completion_failed');
        Http::assertNothingSent();
    }

    public function testTheMessageEndpointIsRateLimited(): void
    {
        // Arrange
        config(['openai.messages_per_minute' => 2]);
        $this->fakeOpenAiAnswer();
        $conversation = $this->createConversation();
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';
        $this->postJson($url, ['content' => 'one'])->assertCreated();
        $this->postJson($url, ['content' => 'two'])->assertCreated();

        // Act
        $response = $this->postJson($url, ['content' => 'three']);

        // Assert
        $response->assertStatus(429)->assertJsonPath('code', 'too_many_requests')->assertHeader('Retry-After');
        $this->assertCount(2, $this->sentToOpenAi());
        $this->assertMatchesContract($response, self::COLLECTION, 'post');
    }

    public function testTheRateLimitCannotBeEvadedByForgingForwardedHeaders(): void
    {
        // Arrange
        config(['openai.messages_per_minute' => 1]);
        $this->fakeOpenAiAnswer();
        $conversation = $this->createConversation();
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';
        $this->postJson($url, ['content' => 'one'], ['X-Forwarded-For' => '203.0.113.1'])->assertCreated();

        // Act
        $response = $this->postJson($url, ['content' => 'two'], ['X-Forwarded-For' => '203.0.113.2']);

        // Assert
        $response->assertStatus(429);
    }

    public function testListingMessagesOldestFirstWithPaging(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer('Answer');
        $conversation = $this->createConversation();
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';
        $this->postJson($url, ['content' => 'Question 1'])->assertCreated();
        $this->postJson($url, ['content' => 'Question 2'])->assertCreated();

        // Act
        $all = $this->getJson($url);
        $secondPage = $this->getJson($url . '?per_page=3&page=2');

        // Assert
        $all->assertOk()->assertJsonPath('meta.total', 4)->assertJsonCount(4, 'data');
        $this->assertSame(
            ['Question 1', 'Answer', 'Question 2', 'Answer'],
            array_column($all->json('data'), 'content')
        );
        $secondPage->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total_pages', 2);
        $this->assertMatchesContract($all, self::COLLECTION, 'get');
    }

    public function testListingMessagesOfAnUnknownConversation(): void
    {
        // Arrange
        $url = self::CONVERSATIONS . '/' . ConversationId::generate() . '/messages';

        // Act
        $response = $this->getJson($url);

        // Assert
        $response->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        $this->assertMatchesContract($response, self::COLLECTION, 'get');
    }

    public function testShowingAndDeletingASingleMessage(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer();
        $conversation = $this->createConversation();
        $base = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';
        $sent = $this->postJson($base, ['content' => 'Hi'])->assertCreated();
        $userMessageId = $sent->json('data.user_message.id');

        // Act
        $shown = $this->getJson($base . '/' . $userMessageId);
        $deleted = $this->deleteJson($base . '/' . $userMessageId);
        $shownAfterDelete = $this->getJson($base . '/' . $userMessageId);

        // Assert
        $shown->assertOk()->assertJsonPath('data.content', 'Hi');
        $deleted->assertNoContent();
        $shownAfterDelete->assertNotFound()->assertJsonPath('code', 'message_not_found');
        $this->assertDatabaseCount('messages', 1);
        $this->assertMatchesContract($shown, self::ITEM, 'get');
        $this->assertMatchesContract($deleted, self::ITEM, 'delete');
        $this->assertMatchesContract($shownAfterDelete, self::ITEM, 'get');
    }

    public function testAMessageIsNotReachableThroughAnotherConversation(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer();
        $owner = $this->createConversation();
        $stranger = $this->createConversation();
        $messageId = $this->postJson(self::CONVERSATIONS . '/' . $owner['id'] . '/messages', ['content' => 'Hi'])
            ->json('data.user_message.id');
        $foreignUrl = self::CONVERSATIONS . '/' . $stranger['id'] . '/messages/' . $messageId;

        // Act
        $shown = $this->getJson($foreignUrl);
        $deleted = $this->deleteJson($foreignUrl);

        // Assert
        $shown->assertNotFound()->assertJsonPath('code', 'message_not_found');
        $deleted->assertNotFound();
        $this->assertDatabaseCount('messages', 2);
    }

    public function testUnknownMessageInKnownConversation(): void
    {
        // Arrange
        $conversation = $this->createConversation();
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages/' . MessageId::generate();

        // Act
        $response = $this->deleteJson($url);

        // Assert
        $response->assertNotFound()->assertJsonPath('code', 'message_not_found');
        $this->assertMatchesContract($response, self::ITEM, 'delete');
    }

    public function testOpenAiIsAskedOnlyOncePerMessage(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer();
        $conversation = $this->createConversation();
        $url = self::CONVERSATIONS . '/' . $conversation['id'] . '/messages';
        $this->postJson($url, ['content' => 'Hi'])->assertCreated();

        // Assert
        $this->assertCount(1, array_filter(
            $this->sentToOpenAi(),
            static fn (HttpClientRequest $request): bool => str_ends_with($request->url(), '/chat/completions')
        ));
    }
}
