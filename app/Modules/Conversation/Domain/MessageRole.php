<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
