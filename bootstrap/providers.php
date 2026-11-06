<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Core\Infrastructure\Laravel\CoreServiceProvider::class,
    App\Modules\Conversation\Infrastructure\Laravel\ConversationServiceProvider::class,
];
