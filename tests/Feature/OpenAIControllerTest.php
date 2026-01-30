<?php

namespace Tests\Feature;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation error when message is missing
     */
    public function test_send_message_requires_message_field(): void
    {
        $response = $this->postJson('/api/openai/message', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * Test validation error for invalid UUID
     */
    public function test_send_message_validates_conversation_id_uuid(): void
    {
        $response = $this->postJson('/api/openai/message', [
            'message' => 'Test message',
            'conversation_id' => 'invalid-uuid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conversation_id']);
    }

    /**
     * Test validation error for invalid model
     */
    public function test_send_message_validates_model(): void
    {
        $response = $this->postJson('/api/openai/message', [
            'message' => 'Test message',
            'model' => 'invalid-model',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['model']);
    }

    /**
     * Test validation error for temperature out of range
     */
    public function test_send_message_validates_temperature_range(): void
    {
        $response = $this->postJson('/api/openai/message', [
            'message' => 'Test message',
            'temperature' => 3.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['temperature']);
    }

    /**
     * Test send message with OpenAI API mocked
     */
    public function test_send_message_successfully(): void
    {
        // Mock OpenAI API response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-3.5-turbo',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'This is a test response from OpenAI.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 15,
                    'total_tokens' => 25,
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/openai/message', [
            'message' => 'Hello, how are you?',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'conversation_id',
                    'message',
                    'role',
                    'metadata',
                ],
            ]);

        // Verify messages were saved to database
        $this->assertDatabaseCount('messages', 2); // user + assistant
        $this->assertDatabaseHas('messages', [
            'role' => 'user',
            'content' => 'Hello, how are you?',
        ]);
        $this->assertDatabaseHas('messages', [
            'role' => 'assistant',
            'content' => 'This is a test response from OpenAI.',
        ]);
    }

    /**
     * Test get conversation history
     */
    public function test_get_conversation_history(): void
    {
        // Create test messages
        $conversationId = '550e8400-e29b-41d4-a716-446655440000';

        Message::create([
            'role' => 'user',
            'content' => 'Hello',
            'conversation_id' => $conversationId,
        ]);

        Message::create([
            'role' => 'assistant',
            'content' => 'Hi there!',
            'conversation_id' => $conversationId,
        ]);

        $response = $this->getJson("/api/openai/conversation/{$conversationId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'conversation_id',
                    'messages',
                ],
            ])
            ->assertJsonCount(2, 'data.messages');
    }

    /**
     * Test get conversation not found
     */
    public function test_get_conversation_not_found(): void
    {
        $conversationId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->getJson("/api/openai/conversation/{$conversationId}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Conversation not found.',
            ]);
    }

    /**
     * Test list conversations
     */
    public function test_list_conversations(): void
    {
        // Create test messages
        Message::create([
            'role' => 'user',
            'content' => 'Hello',
            'conversation_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        Message::create([
            'role' => 'user',
            'content' => 'Test',
            'conversation_id' => '650e8400-e29b-41d4-a716-446655440001',
        ]);

        $response = $this->getJson('/api/openai/conversations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'conversations',
                ],
            ]);
    }
}
