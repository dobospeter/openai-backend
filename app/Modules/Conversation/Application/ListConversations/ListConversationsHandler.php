<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Application\ListConversations;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationRepository;

final class ListConversationsHandler
{
    public function __construct(private readonly ConversationRepository $conversations)
    {
    }

    /**
     * @return Page<Conversation>
     */
    public function handle(PageRequest $request): Page
    {
        return $this->conversations->page($request);
    }
}
