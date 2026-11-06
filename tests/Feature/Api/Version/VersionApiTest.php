<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Version;

use Tests\Feature\Api\ApiTestCase;

final class VersionApiTest extends ApiTestCase
{
    public function testTheVersionEndpointReportsTheConfiguredVersion(): void
    {
        // Arrange
        config(['app.currentAppVersion' => '9.8.7']);

        // Act
        $response = $this->getJson('/api/version');

        // Assert
        $response->assertOk()->assertExactJson(['currentAppVersion' => '9.8.7']);
        $this->assertMatchesContract($response, '/version', 'get');
    }
}
