<?php

namespace Tests\Feature\Security;

class AdminEndpointSecurityTest extends SecurityTestCase
{
    public function test_admin_endpoints_fail_closed_without_a_configured_key(): void
    {
        config(['admin.key' => '']);

        $this->getJson('/api/admin/pharmacies')->assertUnauthorized();
    }

    public function test_admin_endpoints_require_the_configured_key(): void
    {
        config(['admin.key' => 'temporary-test-key']);

        $this->getJson('/api/admin/pharmacies')->assertUnauthorized();
        $this->withHeader('X-Admin-Key', 'wrong-key')
            ->getJson('/api/admin/pharmacies')
            ->assertUnauthorized();
        $this->withHeader('X-Admin-Key', 'temporary-test-key')
            ->getJson('/api/admin/pharmacies')
            ->assertOk()
            ->assertJsonStructure(['pharmacies']);
    }

    public function test_removed_admin_routes_are_not_exposed(): void
    {
        config(['admin.key' => 'temporary-test-key']);

        $this->withHeader('X-Admin-Key', 'temporary-test-key')
            ->getJson('/api/admin/tickets/all')
            ->assertNotFound();
    }
}
