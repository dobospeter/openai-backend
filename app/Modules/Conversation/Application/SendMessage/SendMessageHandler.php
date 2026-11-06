<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\SendMessage;

use App\Core\Application\TransactionManager;
use App\Modules\Conversation\Domain\ChatCompletionClient;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\Message;
use App\Modules\Conversation\Domain\MessageContent;
use App\Modules\Conversation\Domain\MessageId;
use App\Modules\Conversation\Domain\MessageRepository;
use Psr\Clock\ClockInterface;

final class SendMessageHandler
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages,
        private readonly ChatCompletionClient $chat,
        private readonly TransactionManager $transactions,
        private readonly ClockInterface $clock,
        private readonly int $historyLimit
    ) {
    }

    /**
     * Asks the model to answer the user's message and stores both. Nothing is stored when the model fails,
     * so a failed request leaves no dangling question behind and can safely be retried.
     */
    public function handle(SendMessageCommand $command): SendMessageResult
    {
        $conversation = $this->conversations->get(ConversationId::fromString($command->conversationId));

        $userMessage = Message::fromUser(
            MessageId::generate(),
            $conversation->id(),
            new MessageContent($command->content),
            $this->clock->now()
        );

        $history = [...$this->messages->latest($conversation->id(), $this->historyLimit), $userMessage];
        $completion = $this->chat->complete($conversation, $history);

        $assistantMessage = Message::fromAssistant(
            MessageId::generate(),
            $conversation->id(),
            $completion,
            $this->clock->now()
        );
        $conversation->recordActivity($assistantMessage->createdAt());

        $this->transactions->transactional(function () use ($userMessage, $assistantMessage, $conversation): void {
            $this->messages->save($userMessage);
            $this->messages->save($assistantMessage);
            $this->conversations->save($conversation);
        });

        return new SendMessageResult($userMessage, $assistantMessage);
    }
}
