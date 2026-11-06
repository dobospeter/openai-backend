<?php

declare(strict_types=1);

namespace App\Api\Conversation\Requests;

use App\Modules\Conversation\Application\CreateConversation\CreateConversationCommand;

final class CreateConversationRequest extends ConversationSettingsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->settingRules('nullable');
    }

    public function command(): CreateConversationCommand
    {
        $data = $this->validated();

        return new CreateConversationCommand(
            $data['title'] ?? null,
            $data['model'] ?? null,
            isset($data['temperature']) ? (float) $data['temperature'] : null,
            $data['system_prompt'] ?? null
        );
    }
}
