<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API documentation access
    |--------------------------------------------------------------------------
    |
    | The OpenAPI document and its Swagger UI are served at /docs behind HTTP Basic
    | authentication. Leave the password empty and the documentation stays locked.
    |
    */
    'username' => env('DOCS_USERNAME', 'docs'),
    'password' => env('DOCS_PASSWORD'),

    'spec_path' => base_path('docs/openapi.yaml'),

    /*
    | Requests per minute and client, which also limits password guessing.
    */
    'requests_per_minute' => (int) env('DOCS_REQUESTS_PER_MINUTE', 30),
];
