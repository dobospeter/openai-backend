<?php

declare(strict_types=1);

namespace App\Api\Conversation\Controllers;

use App\Api\Conversation\Requests\SendMessageRequest;
use App\Api\Conversation\Resources\ExchangeResource;
use App\Api\Conversation\Resources\MessageResource;
use App\Core\Http\PaginationMeta;
use App\Core\Http\Requests\PaginationRequest;
use App\Modules\Conversation\Application\DeleteMessage\DeleteMessageHandler;
use App\Modules\Conversation\Application\GetMessage\GetMessageHandler;
use App\Modules\Conversation\Application\ListMessages\ListMessagesHandler;
use App\Modules\Conversation\Application\SendMessage\SendMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class MessageController
{
    public function index(
        string $conversation,
        PaginationRequest $request,
        ListMessagesHandler $handler
    ): AnonymousResourceCollection {
        $page = $handler->handle($conversation, $request->pageRequest());

        return MessageResource::collection($page->items)->additional(PaginationMeta::of($page));
    }

    public function store(string $conversation, SendMessageRequest $request, SendMessageHandler $handler): JsonResponse
    {
        $result = $handler->handle($request->command($conversation));

        return ExchangeResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('conversations.messages.show', [
                'conversation' => $conversation,
                'message' => $result->assistantMessage->id()->value(),
            ]));
    }

    public function show(string $conversation, string $message, GetMessageHandler $handler): MessageResource
    {
        return MessageResource::make($handler->handle($conversation, $message));
    }

    public function destroy(string $conversation, string $message, DeleteMessageHandler $handler): Response
    {
        $handler->handle($conversation, $message);

        return response()->noContent();
    }
}
