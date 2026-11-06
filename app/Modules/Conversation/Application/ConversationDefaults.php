<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application;

use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\Temperature;

/**
 * Settings a new conversation starts with when the client does not choose any.
 */
final class ConversationDefaults
{
    public function __construct(
        public readonly ModelName $model,
        public readonly Temperature $temperature
    ) {
    }
}
