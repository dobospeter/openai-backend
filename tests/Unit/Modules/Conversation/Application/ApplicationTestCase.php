<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Conversation\Application;

use App\Modules\Conversation\Application\ConversationDefaults;
use App\Modules\Conversation\Domain\ModelName;
use App\Modules\Conversation\Domain\Temperature;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryConversationRepository;
use Tests\Support\InMemoryMessageRepository;
use Tests\Support\TickingClock;

/**
 * Wires the in-memory collaborators every use case test needs.
 */
abstract class ApplicationTestCase extends TestCase
{
    protected InMemoryConversationRepository $conversations;
    protected InMemoryMessageRepository $messages;
    protected TickingClock $clock;
    protected ConversationDefaults $defaults;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversations = new InMemoryConversationRepository();
        $this->messages = new InMemoryMessageRepository();
        $this->clock = new TickingClock('2026-06-01T10:00:00+00:00');
        $this->defaults = new ConversationDefaults(new ModelName('gpt-3.5-turbo'), new Temperature(0.7));
    }
}
