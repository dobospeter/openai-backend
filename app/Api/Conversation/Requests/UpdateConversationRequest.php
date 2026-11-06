<?php

declare(strict_types=1);

namespace App\Api\Conversation\Requests;

use App\Modules\Conversation\Application\UpdateConversation\UpdateConversationCommand;

/**
 * Full replacement (PUT): every setting has to be sent, system_prompt may be null to clear it.
 */
final class UpdateConversationRequest extends ConversationSettingsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->settingRules('required');
    }

    public function command(string $conversationId): UpdateConversationCommand
    {
        $data = $this->validated();

        return new UpdateConversationCommand(
            $conversationId,
            $data['title'],
            $data['model'],
            (float) $data['temperature'],
            $data['system_prompt']
        );
    }
}
