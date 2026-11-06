<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\Persistence;

use App\Core\Domain\Pagination\Page;
use App\Core\Domain\Pagination\PageRequest;
use App\Modules\Conversation\Domain\Conversation;
use App\Modules\Conversation\Domain\ConversationId;
use App\Modules\Conversation\Domain\ConversationRepository;
use App\Modules\Conversation\Domain\ConversationTitle;
use App\Modules\Conversation\Domain\Exception\ConversationNotFound;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\SystemPrompt;
use App\Modules\Conversation\Domain\Temperature;
use App\Modules\Conversation\Infrastructure\Persistence\Eloquent\ConversationModel;

final class EloquentConversationRepository implements ConversationRepository
{
    public function save(Conversation $conversation): void
    {
        $row = ConversationModel::query()->find($conversation->id()->value()) ?? new ConversationModel();

        $row->forceFill([
            'id' => $conversation->id()->value(),
            'title' => $conversation->title()->value(),
            'model' => $conversation->model()->value(),
            'temperature' => $conversation->temperature()->value(),
            'system_prompt' => $conversation->systemPrompt()?->value(),
            'created_at' => $conversation->createdAt(),
            'updated_at' => $conversation->updatedAt(),
        ])->save();
    }

    public function get(ConversationId $id): Conversation
    {
        $row = ConversationModel::query()->find($id->value());

        if ($row === null) {
            throw ConversationNotFound::withId($id);
        }

        return $this->toEntity($row);
    }

    public function page(PageRequest $request): Page
    {
        $items = ConversationModel::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->map(fn (ConversationModel $row): Conversation => $this->toEntity($row))
            ->all();

        return new Page($items, ConversationModel::query()->count(), $request);
    }

    public function delete(ConversationId $id): void
    {
        // Messages go away with it through the foreign key's ON DELETE CASCADE.
        ConversationModel::query()->whereKey($id->value())->delete();
    }

    private function toEntity(ConversationModel $row): Conversation
    {
        return Conversation::reconstitute(
            ConversationId::fromString($row->id),
            new ConversationTitle($row->title),
            new ModelName($row->model),
            new Temperature($row->temperature),
            SystemPrompt::fromNullable($row->system_prompt),
            $row->created_at->toDateTimeImmutable(),
            $row->updated_at->toDateTimeImmutable()
        );
    }
}
