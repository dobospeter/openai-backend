<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\UpdateConversation;

use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use Psr\Clock\ClockInterface;

final class UpdateConversationHandler
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly ClockInterface $clock
    ) {
    }

    public function handle(UpdateConversationCommand $command): Conversation
    {
        $conversation = $this->conversations->get(ConversationId::fromString($command->conversationId));

        $conversation->reconfigure(
            new ConversationTitle($command->title),
            new ModelName($command->model),
            new Temperature($command->temperature),
            SystemPrompt::fromNullable($command->systemPrompt),
            $this->clock->now()
        );

        $this->conversations->save($conversation);

        return $conversation;
    }
}
