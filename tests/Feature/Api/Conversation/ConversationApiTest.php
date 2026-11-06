<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Conversation;

use App\Modules\Conversation\Domain\ConversationId;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\ApiTestCase;

final class ConversationApiTest extends ApiTestCase
{
    private const ITEM = '/v1/conversations/{conversationId}';

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function creationProvider(): iterable
    {
        yield 'empty body uses the defaults' => [[], [
            'title' => 'Untitled conversation',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'system_prompt' => null,
        ]];
        yield 'everything given' => [
            ['title' => 'Trip', 'model' => 'gpt-4', 'temperature' => 1.3, 'system_prompt' => 'Be brief.'],
            ['title' => 'Trip', 'model' => 'gpt-4', 'temperature' => 1.3, 'system_prompt' => 'Be brief.'],
        ];
        yield 'zero temperature is honoured' => [['temperature' => 0], ['temperature' => 0.0]];
        yield 'whitespace is trimmed' => [['title' => '  Padded  '], ['title' => 'Padded']];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidCreationProvider(): iterable
    {
        yield 'unknown model' => [['model' => 'gpt-99'], 'model'];
        yield 'temperature too high' => [['temperature' => 2.1], 'temperature'];
        yield 'temperature below zero' => [['temperature' => -0.1], 'temperature'];
        yield 'temperature not numeric' => [['temperature' => 'hot'], 'temperature'];
        yield 'title too long' => [['title' => str_repeat('t', 256)], 'title'];
        yield 'title not a string' => [['title' => ['a']], 'title'];
        yield 'system prompt too long' => [['system_prompt' => str_repeat('p', 4001)], 'system_prompt'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidReplacementProvider(): iterable
    {
        $valid = ['title' => 'T', 'model' => 'gpt-4', 'temperature' => 1, 'system_prompt' => null];

        yield 'nothing sent' => [[], ['title', 'model', 'temperature', 'system_prompt']];
        yield 'title missing' => [array_diff_key($valid, ['title' => 1]), ['title']];
        yield 'model missing' => [array_diff_key($valid, ['model' => 1]), ['model']];
        yield 'temperature missing' => [array_diff_key($valid, ['temperature' => 1]), ['temperature']];
        yield 'system prompt missing (must be sent, even as null)' => [
            array_diff_key($valid, ['system_prompt' => 1]),
            ['system_prompt'],
        ];
        yield 'blank title' => [[...$valid, 'title' => '   '], ['title']];
        yield 'unknown model' => [[...$valid, 'model' => 'gpt-99'], ['model']];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPagingProvider(): iterable
    {
        yield 'page zero' => ['page=0'];
        yield 'negative page' => ['page=-2'];
        yield 'page not a number' => ['page=abc'];
        yield 'page size zero' => ['per_page=0'];
        yield 'page size above the maximum' => ['per_page=101'];
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function pagingProvider(): iterable
    {
        yield 'defaults' => ['', 3, 1, 3];
        yield 'second page of two' => ['per_page=2&page=2', 1, 2, 3];
        yield 'page past the end' => ['per_page=2&page=5', 0, 5, 3];
        yield 'maximum page size' => ['per_page=100', 3, 1, 3];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $expected
     */
    #[DataProvider('creationProvider')]
    public function testCreatingAConversation(array $payload, array $expected): void
    {
        // Arrange - the payload comes from the provider

        // Act
        $response = $this->postJson(self::CONVERSATIONS, $payload);

        // Assert
        $response->assertCreated()->assertJson(['data' => $expected]);
        $id = $response->json('data.id');
        $response->assertHeader('Location', url(self::CONVERSATIONS . '/' . $id));
        $this->assertDatabaseHas('conversations', ['id' => $id]);
        $this->assertMatchesContract($response, '/v1/conversations', 'post');
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidCreationProvider')]
    public function testCreatingWithInvalidDataIsRejected(array $payload, string $invalidField): void
    {
        // Arrange - the payload comes from the provider

        // Act
        $response = $this->postJson(self::CONVERSATIONS, $payload);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors($invalidField);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertMatchesContract($response, '/v1/conversations', 'post');
    }

    public function testMalformedJsonIsRejectedInsteadOfBeingTreatedAsEmpty(): void
    {
        // Arrange
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $server = $this->transformHeadersToServerVars($headers);

        // Act
        $response = $this->call('POST', self::CONVERSATIONS, [], [], [], $server, '{oops');

        // Assert
        $response->assertStatus(400)->assertJsonPath('code', 'bad_request');
        $this->assertDatabaseCount('conversations', 0);
        $this->assertMatchesContract($response, '/v1/conversations', 'post');
    }

    public function testShowingAConversation(): void
    {
        // Arrange
        $created = $this->createConversation(['title' => 'Trip', 'system_prompt' => 'Be brief.']);

        // Act
        $response = $this->getJson(self::CONVERSATIONS . '/' . $created['id']);

        // Assert
        $response->assertOk()->assertExactJson(['data' => $created]);
        $this->assertMatchesContract($response, self::ITEM, 'get');
    }

    public function testShowingAnUnknownConversation(): void
    {
        // Arrange
        $unknown = ConversationId::generate()->value();

        // Act
        $response = $this->getJson(self::CONVERSATIONS . '/' . $unknown);

        // Assert
        $response->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        $this->assertMatchesContract($response, self::ITEM, 'get');
    }

    /**
     * @param int $expectedItems
     */
    #[DataProvider('pagingProvider')]
    public function testListingConversationsIsPaginatedNewestActivityFirst(
        string $query,
        int $expectedItems,
        int $expectedPage,
        int $expectedTotal
    ): void {
        // Arrange
        $first = $this->createConversation(['title' => 'First']);
        $this->createConversation(['title' => 'Second']);
        $third = $this->createConversation(['title' => 'Third']);

        // Act
        $response = $this->getJson(self::CONVERSATIONS . '?' . $query);

        // Assert
        $response->assertOk()
            ->assertJsonCount($expectedItems, 'data')
            ->assertJsonPath('meta.page', $expectedPage)
            ->assertJsonPath('meta.total', $expectedTotal);
        if ($query === '') {
            $this->assertSame(
                [$third['id'], $first['id']],
                [$response->json('data.0.id'), $response->json('data.2.id')]
            );
        }
        $this->assertMatchesContract($response, '/v1/conversations', 'get');
    }

    #[DataProvider('invalidPagingProvider')]
    public function testListingWithInvalidPagingIsRejected(string $query): void
    {
        // Arrange - the query string comes from the provider

        // Act
        $response = $this->getJson(self::CONVERSATIONS . '?' . $query);

        // Assert
        $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        $this->assertMatchesContract($response, '/v1/conversations', 'get');
    }

    public function testReplacingTheSettingsOfAConversation(): void
    {
        // Arrange
        $created = $this->createConversation(['title' => 'Old', 'system_prompt' => 'Old prompt']);
        $payload = ['title' => 'New', 'model' => 'gpt-4-turbo-preview', 'temperature' => 0.2, 'system_prompt' => null];

        // Act
        $response = $this->putJson(self::CONVERSATIONS . '/' . $created['id'], $payload);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.id', $created['id'])
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.model', 'gpt-4-turbo-preview')
            ->assertJsonPath('data.temperature', 0.2)
            ->assertJsonPath('data.system_prompt', null)
            ->assertJsonPath('data.created_at', $created['created_at']);
        $this->assertNotSame($created['updated_at'], $response->json('data.updated_at'));
        $this->assertMatchesContract($response, self::ITEM, 'put');
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $invalidFields
     */
    #[DataProvider('invalidReplacementProvider')]
    public function testReplacingWithIncompleteOrInvalidDataIsRejected(array $payload, array $invalidFields): void
    {
        // Arrange
        $created = $this->createConversation(['title' => 'Untouched']);

        // Act
        $response = $this->putJson(self::CONVERSATIONS . '/' . $created['id'], $payload);

        // Assert
        $response->assertUnprocessable()->assertJsonValidationErrors($invalidFields);
        $this->assertDatabaseHas('conversations', ['id' => $created['id'], 'title' => 'Untouched']);
        $this->assertMatchesContract($response, self::ITEM, 'put');
    }

    public function testReplacingAnUnknownConversation(): void
    {
        // Arrange
        $payload = ['title' => 'T', 'model' => 'gpt-4', 'temperature' => 1, 'system_prompt' => null];

        // Act
        $response = $this->putJson(self::CONVERSATIONS . '/' . ConversationId::generate(), $payload);

        // Assert
        $response->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        $this->assertMatchesContract($response, self::ITEM, 'put');
    }

    public function testDeletingAConversationAlsoDeletesItsMessages(): void
    {
        // Arrange
        $this->fakeOpenAiAnswer();
        $created = $this->createConversation();
        $this->postJson(self::CONVERSATIONS . '/' . $created['id'] . '/messages', ['content' => 'Hi'])->assertCreated();
        $this->assertDatabaseCount('messages', 2);

        // Act
        $response = $this->deleteJson(self::CONVERSATIONS . '/' . $created['id']);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertMatchesContract($response, self::ITEM, 'delete');
    }

    public function testDeletingTwiceReportsNotFound(): void
    {
        // Arrange
        $created = $this->createConversation();
        $this->deleteJson(self::CONVERSATIONS . '/' . $created['id'])->assertNoContent();

        // Act
        $response = $this->deleteJson(self::CONVERSATIONS . '/' . $created['id']);

        // Assert
        $response->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        $this->assertMatchesContract($response, self::ITEM, 'delete');
    }
}
