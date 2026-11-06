<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Osteel\OpenApi\Testing\ValidatorInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\TickingClock;
use Tests\TestCase;

/**
 * Base class of the HTTP tests. Besides asserting on responses, tests can check them against
 * docs/openapi.yaml, so the published documentation cannot drift away from the real behaviour.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected const CONVERSATIONS = '/api/v1/conversations';

    private ValidatorInterface $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ClockInterface::class, new TickingClock());
        $this->contract = ValidatorBuilder::fromYamlFile(base_path('docs/openapi.yaml'))->getValidator();
    }

    /**
     * @param TestResponse<Response> $response
     * @param string $path OpenAPI path template, relative to the server URL (e.g. /v1/conversations/{conversationId})
     */
    protected function assertMatchesContract(TestResponse $response, string $path, string $method): void
    {
        $this->assertTrue($this->contract->validate($response->baseResponse, $path, $method));
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed> the "data" member of the created conversation
     */
    protected function createConversation(array $attributes = []): array
    {
        return $this->postJson(self::CONVERSATIONS, $attributes)->assertCreated()->json('data');
    }

    protected function fakeOpenAiAnswer(string $content = 'Hello from OpenAI'): void
    {
        Http::fake([
            'openai.test/*' => Http::response([
                'model' => 'gpt-4-0613',
                'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8, 'total_tokens' => 20],
            ]),
        ]);
    }

    /**
     * @return list<HttpClientRequest>
     */
    protected function sentToOpenAi(): array
    {
        return Http::recorded()->map(static fn (array $pair): HttpClientRequest => $pair[0])->values()->all();
    }
}
