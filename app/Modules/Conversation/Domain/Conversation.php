<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Domain;

use DateTimeImmutable;

/**
 * Aggregate root: a chat with the model and the settings that govern it. Its messages are separate
 * aggregates that reference it by id.
 */
final class Conversation
{
    private function __construct(
        private readonly ConversationId $id,
        private ConversationTitle $title,
        private ModelName $model,
        private Temperature $temperature,
        private ?SystemPrompt $systemPrompt,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
    }

    public static function start(
        ConversationId $id,
        ConversationTitle $title,
        ModelName $model,
        Temperature $temperature,
        ?SystemPrompt $systemPrompt,
        DateTimeImmutable $now
    ): self {
        return new self($id, $title, $model, $temperature, $systemPrompt, $now, $now);
    }

    /**
     * Rebuilds a persisted conversation; for repositories only.
     */
    public static function reconstitute(
        ConversationId $id,
        ConversationTitle $title,
        ModelName $model,
        Temperature $temperature,
        ?SystemPrompt $systemPrompt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $title, $model, $temperature, $systemPrompt, $createdAt, $updatedAt);
    }

    public function reconfigure(
        ConversationTitle $title,
        ModelName $model,
        Temperature $temperature,
        ?SystemPrompt $systemPrompt,
        DateTimeImmutable $now
    ): void {
        $this->title = $title;
        $this->model = $model;
        $this->temperature = $temperature;
        $this->systemPrompt = $systemPrompt;
        $this->updatedAt = $now;
    }

    public function recordActivity(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }

    public function id(): ConversationId
    {
        return $this->id;
    }

    public function title(): ConversationTitle
    {
        return $this->title;
    }

    public function model(): ModelName
    {
        return $this->model;
    }

    public function temperature(): Temperature
    {
        return $this->temperature;
    }

    public function systemPrompt(): ?SystemPrompt
    {
        return $this->systemPrompt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
