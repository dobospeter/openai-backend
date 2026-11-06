<?php

declare(strict_types=1);

namespace App\Api\Conversation\Resources;

use App\Modules\Conversation\Domain\Conversation;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Conversation $resource
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;

        return [
            'id' => $conversation->id()->value(),
            'title' => $conversation->title()->value(),
            'model' => $conversation->model()->value(),
            'temperature' => $conversation->temperature()->value(),
            'system_prompt' => $conversation->systemPrompt()?->value(),
            'created_at' => $conversation->createdAt()->format(DateTimeInterface::RFC3339_EXTENDED),
            'updated_at' => $conversation->updatedAt()->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
