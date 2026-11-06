<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\CreateConversation;

use App\Modules\Conversation\Application\ConversationDefaults;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use Psr\Clock\ClockInterface;

final class CreateConversationHandler
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly ConversationDefaults $defaults,
        private readonly ClockInterface $clock
    ) {
    }

    public function handle(CreateConversationCommand $command): Conversation
    {
        $conversation = Conversation::start(
            ConversationId::generate(),
            $command->title === null ? ConversationTitle::untitled() : new ConversationTitle($command->title),
            $command->model === null ? $this->defaults->model : new ModelName($command->model),
            $command->temperature === null ? $this->defaults->temperature : new Temperature($command->temperature),
            SystemPrompt::fromNullable($command->systemPrompt),
            $this->clock->now()
        );

        $this->conversations->save($conversation);

        return $conversation;
    }
}
