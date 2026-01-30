<?php

namespace App\Services;

use App\Contracts\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenAIService
{
    protected MessageRepositoryInterface $messageRepository;

    public function __construct(MessageRepositoryInterface $messageRepository)
    {
        $this->messageRepository = $messageRepository;
    }

    /**
     * Send message to OpenAI and save conversation
     *
     * @throws \Exception
     */
    public function sendMessage(
        string $message,
        ?string $conversationId = null,
        string $model = 'gpt-3.5-turbo',
        float $temperature = 0.7
    ): array {
        // Generate conversation ID if not provided
        $conversationId = $conversationId ?? Str::uuid()->toString();

        // Save user message
        $this->messageRepository->create([
            'role' => 'user',
            'content' => $message,
            'conversation_id' => $conversationId,
        ]);

        // Get conversation history
        $conversationHistory = $this->getConversationHistory($conversationId);

        try {
            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('openai.api_key'),
                'Content-Type' => 'application/json',
            ])
                ->timeout(config('openai.timeout', 30))
                ->post(config('openai.api_url').'/chat/completions', [
                    'model' => $model,
                    'messages' => $conversationHistory,
                    'temperature' => $temperature,
                ]);

            if (! $response->successful()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('OpenAI API request failed: '.$response->body());
            }

            $responseData = $response->json();
            $assistantMessage = $responseData['choices'][0]['message']['content'] ?? '';

            // Save assistant response
            $assistantMessageRecord = $this->messageRepository->create([
                'role' => 'assistant',
                'content' => $assistantMessage,
                'conversation_id' => $conversationId,
                'metadata' => [
                    'model' => $responseData['model'] ?? $model,
                    'usage' => $responseData['usage'] ?? null,
                    'finish_reason' => $responseData['choices'][0]['finish_reason'] ?? null,
                ],
            ]);

            return [
                'conversation_id' => $conversationId,
                'message' => $assistantMessage,
                'role' => 'assistant',
                'metadata' => $assistantMessageRecord->metadata,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'conversation_id' => $conversationId,
            ]);
            throw $e;
        }
    }

    /**
     * Get conversation history formatted for OpenAI
     */
    protected function getConversationHistory(string $conversationId): array
    {
        $messages = $this->messageRepository->findByConversationId($conversationId);

        return $messages->map(function ($message) {
            return [
                'role' => $message->role,
                'content' => $message->content,
            ];
        })->toArray();
    }
}
