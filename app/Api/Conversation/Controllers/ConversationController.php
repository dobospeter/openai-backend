<?php

declare(strict_types=1);

namespace App\Api\Conversation\Controllers;

use App\Api\Conversation\Requests\CreateConversationRequest;
use App\Api\Conversation\Requests\UpdateConversationRequest;
use App\Api\Conversation\Resources\ConversationResource;
use App\Core\Http\PaginationMeta;
use App\Core\Http\Requests\PaginationRequest;
use App\Modules\Conversation\Application\CreateConversation\CreateConversationHandler;
use App\Modules\Conversation\Application\DeleteConversation\DeleteConversationHandler;
use App\Modules\Conversation\Application\GetConversation\GetConversationHandler;
use App\Modules\Conversation\Application\ListConversations\ListConversationsHandler;
use App\Modules\Conversation\Application\UpdateConversation\UpdateConversationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ConversationController
{
    public function index(PaginationRequest $request, ListConversationsHandler $handler): AnonymousResourceCollection
    {
        $page = $handler->handle($request->pageRequest());

        return ConversationResource::collection($page->items)->additional(PaginationMeta::of($page));
    }

    public function store(CreateConversationRequest $request, CreateConversationHandler $handler): JsonResponse
    {
        $conversation = $handler->handle($request->command());

        return ConversationResource::make($conversation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('conversations.show', ['conversation' => $conversation->id()->value()]));
    }

    public function show(string $conversation, GetConversationHandler $handler): ConversationResource
    {
        return ConversationResource::make($handler->handle($conversation));
    }

    public function update(
        string $conversation,
        UpdateConversationRequest $request,
        UpdateConversationHandler $handler
    ): ConversationResource {
        return ConversationResource::make($handler->handle($request->command($conversation)));
    }

    public function destroy(string $conversation, DeleteConversationHandler $handler): Response
    {
        $handler->handle($conversation);

        return response()->noContent();
    }
}
