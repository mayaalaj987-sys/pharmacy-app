<?php

namespace Tests\Feature\Security;

class AdminEndpointSecurityTest extends SecurityTestCase
{
    public function test_admin_endpoints_require_an_authenticated_admin_session(): void
    {
        $this->getJson('/api/admin/review/applications')
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_x_admin_key_alone_never_authorizes_a_canonical_operation(): void
    {
        $this->withHeader('X-Admin-Key', 'temporary-test-key')
            ->getJson('/api/admin/review/applications')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_removed_admin_routes_are_not_exposed(): void
    {
        $this->withHeader('X-Admin-Key', 'temporary-test-key')
            ->getJson('/api/admin/tickets/all')
            ->assertNotFound();
    }
}
