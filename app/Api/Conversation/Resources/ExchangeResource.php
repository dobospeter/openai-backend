<?php

declare(strict_types=1);

namespace App\Api\Conversation\Resources;

use App\Modules\Conversation\Application\SendMessage\SendMessageResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A question and the answer it received.
 *
 * @property SendMessageResult $resource
 */
final class ExchangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_message' => MessageResource::make($this->resource->userMessage),
            'assistant_message' => MessageResource::make($this->resource->assistantMessage),
        ];
    }
}
