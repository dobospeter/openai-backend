<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Credentials and endpoint
    |--------------------------------------------------------------------------
    */
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('OPENAI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Conversation defaults
    |--------------------------------------------------------------------------
    |
    | Applied to conversations created without an explicit model / temperature.
    |
    */
    'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
    'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Models a client may pick (comma separated in the environment)
    |--------------------------------------------------------------------------
    */
    'allowed_models' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPENAI_ALLOWED_MODELS', 'gpt-3.5-turbo,gpt-4,gpt-4-turbo-preview'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | History window
    |--------------------------------------------------------------------------
    |
    | How many of the most recent messages are sent along with a new question.
    |
    */
    'history_limit' => (int) env('OPENAI_HISTORY_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | Rate limit of the message endpoint (requests per minute and client)
    |--------------------------------------------------------------------------
    */
    'messages_per_minute' => (int) env('OPENAI_MESSAGES_PER_MINUTE', 20),
];
