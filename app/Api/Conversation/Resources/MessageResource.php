<?php

declare(strict_types=1);

namespace App\Api\Conversation\Resources;

use App\Modules\Conversation\Domain\CompletionMetadata;
use App\Modules\Conversation\Domain\Message;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Message $resource
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id' => $message->id()->value(),
            'conversation_id' => $message->conversationId()->value(),
            'role' => $message->role()->value,
            'content' => $message->content()->value(),
            'completion' => $this->completion($message->metadata()),
            'created_at' => $message->createdAt()->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function completion(?CompletionMetadata $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return [
            'model' => $metadata->model->value(),
            'finish_reason' => $metadata->finishReason,
            'usage' => $metadata->usage === null ? null : [
                'prompt_tokens' => $metadata->usage->promptTokens,
                'completion_tokens' => $metadata->usage->completionTokens,
                'total_tokens' => $metadata->usage->totalTokens,
            ],
        ];
    }
}
