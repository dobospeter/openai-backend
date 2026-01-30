<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MessageRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;

class OpenAIController extends Controller
{
    protected OpenAIService $openAIService;
    protected MessageRepositoryInterface $messageRepository;

    public function __construct(
        OpenAIService $openAIService,
        MessageRepositoryInterface $messageRepository
    ) {
        $this->openAIService = $openAIService;
        $this->messageRepository = $messageRepository;
    }

    /**
     * Send a message to OpenAI
     *
     * @param SendMessageRequest $request
     * @return JsonResponse
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $result = $this->openAIService->sendMessage(
                message: $request->input('message'),
                conversationId: $request->input('conversation_id'),
                model: $request->input('model', config('openai.model')),
                temperature: $request->input('temperature', 0.7)
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message to OpenAI.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get conversation history
     *
     * @param string $conversationId
     * @return JsonResponse
     */
    public function getConversation(string $conversationId): JsonResponse
    {
        try {
            $messages = $this->messageRepository->findByConversationId($conversationId);

            if ($messages->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId,
                    'messages' => $messages,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List recent conversations
     *
     * @return JsonResponse
     */
    public function listConversations(): JsonResponse
    {
        try {
            $conversations = $this->messageRepository->getLatestConversations(10);
            dd($conversations);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => $conversations,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
