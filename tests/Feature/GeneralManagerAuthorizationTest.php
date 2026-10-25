<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_general_manager_module(): void
    {
        $response = $this->getJson(route('api.general-manager.supervisors.index'));

        $response->assertUnauthorized();
    }

    public function test_collector_cannot_access_general_manager_module(): void
    {
        $collector = User::factory()->collector()->create();

        $response = $this->actingAs($collector, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة.',
            ]);
    }

    public function test_owner_cannot_access_general_manager_module(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة.',
            ]);
    }

    public function test_tenant_cannot_access_general_manager_module(): void
    {
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($tenant, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة.',
            ]);
    }

    public function test_maintenance_supervisor_cannot_access_general_manager_module(): void
    {
        $maintenanceSupervisor = User::factory()->maintenanceSupervisor()->create();

        $response = $this->actingAs($maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة.',
            ]);
    }

    public function test_general_manager_can_access_general_manager_module(): void
    {
        $generalManager = User::factory()->generalManager()->create();

        $response = $this->actingAs($generalManager, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_collection_supervisor_can_access_general_manager_module(): void
    {
        $collectionSupervisor = User::factory()->collectionSupervisor()->create();

        $response = $this->actingAs($collectionSupervisor, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_real_estate_supervisor_can_access_general_manager_module(): void
    {
        $realEstateSupervisor = User::factory()->realEstateSupervisor()->create();

        $response = $this->actingAs($realEstateSupervisor, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_cross_role_access_is_prevented_between_modules(): void
    {
        $collector = User::factory()->collector()->create();
        $owner = User::factory()->owner()->create();

        // Collector cannot access owner module
        $this->actingAs($collector, 'sanctum')
            ->getJson(route('api.owner.overview'))
            ->assertForbidden()
            ->assertJsonPath('message', 'ليس لديك صلاحية للوصول لهذه الصفحة.');

        // Owner cannot access collector module
        $this->actingAs($owner, 'sanctum')
            ->getJson(route('api.collector.tenants.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'ليس لديك صلاحية للوصول لهذه الصفحة.');
    }
}
