<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\OpenAi;

final class OpenAiSettings
{
    public function __construct(
        public readonly ?string $apiKey,
        public readonly string $baseUrl,
        public readonly ?string $organization,
        public readonly int $timeoutSeconds
    ) {
    }
}
