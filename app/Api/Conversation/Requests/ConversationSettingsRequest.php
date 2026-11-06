<?php

declare(strict_types=1);

namespace App\Api\Conversation\Requests;

use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the requests that set up a conversation. The domain constants are the single
 * source of the limits, so they cannot drift apart from the invariants the domain enforces.
 */
abstract class ConversationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @param 'nullable'|'required' $presence Whether title, model and temperature may be omitted.
     * @return array<string, list<mixed>>
     */
    protected function settingRules(string $presence): array
    {
        return [
            'title' => [$presence, 'string', 'max:' . ConversationTitle::MAX_LENGTH],
            'model' => [$presence, 'string', Rule::in(config('openai.allowed_models'))],
            'temperature' => [$presence, 'numeric', 'between:' . Temperature::MIN . ',' . Temperature::MAX],
            'system_prompt' => [
                $presence === 'required' ? 'present' : 'sometimes',
                'nullable',
                'string',
                'max:' . SystemPrompt::MAX_LENGTH,
            ],
        ];
    }
}
