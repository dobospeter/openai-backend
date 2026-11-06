<?php

declare(strict_types=1);

namespace App\Api\Conversation\Requests;

use App\Modules\Conversation\Application\SendMessage\SendMessageCommand;
use App\Modules\Conversation\Domain\Message;
use Illuminate\Foundation\Http\FormRequest;

final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:' . Message::MAX_USER_CONTENT_LENGTH],
        ];
    }

    public function command(string $conversationId): SendMessageCommand
    {
        return new SendMessageCommand($conversationId, $this->validated('content'));
    }
}
