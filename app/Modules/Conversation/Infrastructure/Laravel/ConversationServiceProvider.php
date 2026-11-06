<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\Laravel;

use App\Modules\Conversation\Application\ConversationDefaults;
use App\Modules\Conversation\Application\SendMessage\SendMessageHandler;
use App\Modules\Conversation\Domain\ChatCompletionClient;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\MessageRepository;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\Temperature;
use App\Modules\Conversation\Infrastructure\OpenAi\OpenAiChatCompletionClient;
use App\Modules\Conversation\Infrastructure\OpenAi\OpenAiSettings;
use App\Modules\Conversation\Infrastructure\Persistence\EloquentConversationRepository;
use App\Modules\Conversation\Infrastructure\Persistence\EloquentMessageRepository;
use Illuminate\Support\ServiceProvider;

final class ConversationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConversationRepository::class, EloquentConversationRepository::class);
        $this->app->bind(MessageRepository::class, EloquentMessageRepository::class);
        $this->app->bind(ChatCompletionClient::class, OpenAiChatCompletionClient::class);

        $this->app->bind(OpenAiSettings::class, static fn (): OpenAiSettings => new OpenAiSettings(
            config('openai.api_key'),
            config('openai.api_url'),
            config('openai.organization'),
            (int) config('openai.timeout')
        ));

        $this->app->bind(ConversationDefaults::class, static fn (): ConversationDefaults => new ConversationDefaults(
            new ModelName(config('openai.model')),
            new Temperature((float) config('openai.temperature'))
        ));

        $this->app->when(SendMessageHandler::class)
            ->needs('$historyLimit')
            ->giveConfig('openai.history_limit');
    }
}
