<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenAI API key from https://platform.openai.com/account/api-keys
    |
    */
    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for OpenAI API
    |
    */
    'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default OpenAI model to use
    |
    */
    'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout for OpenAI API requests in seconds
    |
    */
    'timeout' => env('OPENAI_TIMEOUT', 30),
];
