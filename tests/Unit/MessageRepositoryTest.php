<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Repositories\MessageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected MessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MessageRepository;
    }

    /**
     * Test creating a message
     */
    public function test_create_message(): void
    {
        $data = [
            'role' => 'user',
            'content' => 'Test message',
            'conversation_id' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        $message = $this->repository->create($data);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('user', $message->role);
        $this->assertEquals('Test message', $message->content);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $message->conversation_id);
    }

    /**
     * Test finding messages by conversation ID
     */
    public function test_find_by_conversation_id(): void
    {
        $conversationId = '550e8400-e29b-41d4-a716-446655440000';

        // Create messages
        Message::create([
            'role' => 'user',
            'content' => 'First message',
            'conversation_id' => $conversationId,
        ]);

        Message::create([
            'role' => 'assistant',
            'content' => 'Second message',
            'conversation_id' => $conversationId,
        ]);

        // Create message in different conversation
        Message::create([
            'role' => 'user',
            'content' => 'Different conversation',
            'conversation_id' => '650e8400-e29b-41d4-a716-446655440001',
        ]);

        $messages = $this->repository->findByConversationId($conversationId);

        $this->assertCount(2, $messages);
        $this->assertEquals('First message', $messages[0]->content);
        $this->assertEquals('Second message', $messages[1]->content);
    }

    /**
     * Test getting latest conversations
     */
    public function test_get_latest_conversations(): void
    {
        // Create messages in different conversations
        Message::create([
            'role' => 'user',
            'content' => 'Message 1',
            'conversation_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        Message::create([
            'role' => 'user',
            'content' => 'Message 2',
            'conversation_id' => '650e8400-e29b-41d4-a716-446655440001',
        ]);

        Message::create([
            'role' => 'user',
            'content' => 'Message 3',
            'conversation_id' => '750e8400-e29b-41d4-a716-446655440002',
        ]);

        $conversations = $this->repository->getLatestConversations(2);

        $this->assertCount(2, $conversations);
    }
}
