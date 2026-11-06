<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Application;

use App\Core\Domain\Exception\InvalidValue;
use App\Core\Domain\Exception\UpstreamFailure;
use App\Modules\Conversation\Application\SendMessage\SendMessageCommand;
use App\Modules\Conversation\Application\SendMessage\SendMessageHandler;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\Exception\ChatCompletionFailed;
use App\Modules\Conversation\Domain\Exception\ChatCompletionTimedOut;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeChatCompletionClient;
use Tests\Support\Fixtures;
use Tests\Support\ImmediateTransactionManager;

final class SendMessageHandlerTest extends ApplicationTestCase
{
    private ImmediateTransactionManager $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactions = new ImmediateTransactionManager();
    }

    /**
     * @return iterable<string, array{UpstreamFailure}>
     */
    public static function upstreamFailureProvider(): iterable
    {
        yield 'model rejected the request' => [ChatCompletionFailed::because(['upstream_status' => 500])];
        yield 'model timed out' => [ChatCompletionTimedOut::afterWaiting()];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidContentProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ["  \n"];
        yield 'over the length limit' => [str_repeat('a', Message::MAX_USER_CONTENT_LENGTH + 1)];
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function historyWindowProvider(): iterable
    {
        yield 'everything fits' => [3, 10, 4];
        yield 'oldest messages are dropped' => [6, 4, 5];
        yield 'window of one keeps only the question' => [6, 1, 2];
    }

    public function testItStoresTheQuestionAndTheAnswerAndReturnsBoth(): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $chat = new FakeChatCompletionClient('Lisbon is lovely.');
        $handler = $this->handler($chat);

        // Act
        $result = $handler->handle(new SendMessageCommand($conversation->id()->value(), 'Where to go?'));

        // Assert
        $this->assertSame(MessageRole::User, $result->userMessage->role());
        $this->assertSame('Where to go?', $result->userMessage->content()->value());
        $this->assertSame(MessageRole::Assistant, $result->assistantMessage->role());
        $this->assertSame('Lisbon is lovely.', $result->assistantMessage->content()->value());
        $this->assertCount(2, $this->messages->all());
        $this->assertSame(1, $this->transactions->runs);
    }

    public function testTheAnswerIsStoredAfterTheQuestionAndBumpsTheConversation(): void
    {
        // Arrange
        $conversation = Fixtures::conversation(at: '2026-01-01T09:00:00+00:00');
        $this->conversations->save($conversation);
        $handler = $this->handler(new FakeChatCompletionClient());

        // Act
        $result = $handler->handle(new SendMessageCommand($conversation->id()->value(), 'Hi'));

        // Assert
        $this->assertLessThan($result->assistantMessage->createdAt(), $result->userMessage->createdAt());
        $this->assertEquals(
            $result->assistantMessage->createdAt(),
            $this->conversations->get($conversation->id())->updatedAt()
        );
    }

    #[DataProvider('historyWindowProvider')]
    public function testItSendsOnlyTheLatestHistoryPlusTheNewQuestion(
        int $storedMessages,
        int $historyLimit,
        int $expectedSent
    ): void {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        foreach (range(1, $storedMessages) as $n) {
            $at = sprintf('2026-01-01T09:00:%02d+00:00', $n);
            $this->messages->save(Fixtures::userMessage($conversation->id(), "old {$n}", $at));
        }
        $chat = new FakeChatCompletionClient();
        $handler = $this->handler($chat, $historyLimit);

        // Act
        $handler->handle(new SendMessageCommand($conversation->id()->value(), 'new question'));

        // Assert
        $sent = $chat->calls[0]['history'];
        $this->assertCount($expectedSent, $sent);
        $this->assertSame('new question', end($sent)->content()->value());
        $this->assertSame($conversation, $chat->calls[0]['conversation']);
    }

    #[DataProvider('upstreamFailureProvider')]
    public function testNothingIsStoredWhenTheModelFails(UpstreamFailure $failure): void
    {
        // Arrange
        $conversation = Fixtures::conversation(at: '2026-01-01T09:00:00+00:00');
        $this->conversations->save($conversation);
        $handler = $this->handler(FakeChatCompletionClient::failingWith($failure));

        // Act
        try {
            $handler->handle(new SendMessageCommand($conversation->id()->value(), 'Hi'));
            $this->fail('The upstream failure should have been propagated.');
        } catch (UpstreamFailure $caught) {
            // Assert
            $this->assertSame($failure, $caught);
            $this->assertSame([], $this->messages->all());
            $this->assertSame(0, $this->transactions->runs);
            $this->assertSame(
                '2026-01-01T09:00:00+00:00',
                $this->conversations->get($conversation->id())->updatedAt()->format(DATE_ATOM)
            );
        }
    }

    public function testItFailsForAnUnknownConversationWithoutCallingTheModel(): void
    {
        // Arrange
        $chat = new FakeChatCompletionClient();
        $handler = $this->handler($chat);
        $this->expectException(ConversationNotFound::class);

        // Act
        try {
            $handler->handle(new SendMessageCommand(ConversationId::generate()->value(), 'Hi'));
        } finally {
            // Assert
            $this->assertSame([], $chat->calls);
        }
    }

    #[DataProvider('invalidContentProvider')]
    public function testItRejectsInvalidContentWithoutCallingTheModel(string $content): void
    {
        // Arrange
        $conversation = Fixtures::conversation();
        $this->conversations->save($conversation);
        $chat = new FakeChatCompletionClient();
        $handler = $this->handler($chat);

        // Act
        try {
            $handler->handle(new SendMessageCommand($conversation->id()->value(), $content));
            $this->fail('An InvalidValue exception was expected.');
        } catch (InvalidValue) {
            // Assert
            $this->assertSame([], $chat->calls);
            $this->assertSame([], $this->messages->all());
        }
    }

    private function handler(FakeChatCompletionClient $chat, int $historyLimit = 50): SendMessageHandler
    {
        return new SendMessageHandler(
            $this->conversations,
            $this->messages,
            $chat,
            $this->transactions,
            $this->clock,
            $historyLimit
        );
    }
}
