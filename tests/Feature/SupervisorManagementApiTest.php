<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupervisorManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $generalManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalManager = User::factory()->generalManager()->create([
            'name' => 'المشرف العام الأساسي',
            'phone' => '0500000001',
        ]);
    }

    public function test_can_list_supervisors_with_statistics_and_types(): void
    {
        User::factory()->realEstateSupervisor()->create(['name' => 'مشرف عقارات تجريبي']);
        User::factory()->collectionSupervisor()->create(['name' => 'مشرف تحصيل تجريبي']);
        User::factory()->maintenanceSupervisor()->create(['name' => 'مشرف صيانة تجريبي']);
        User::factory()->collector()->create(['name' => 'محصل عادي']); // should not appear

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.supervisors') // 1 GM + 3 supervisors
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'supervisors' => [
                        '*' => [
                            'id',
                            'name',
                            'username',
                            'phone',
                            'email',
                            'type',
                            'type_label',
                            'type_description',
                            'is_active',
                            'is_current_user',
                        ],
                    ],
                    'statistics' => [
                        'all_count',
                        'general_manager_count',
                        'real_estate_count',
                        'collection_count',
                        'maintenance_count',
                        'active_count',
                        'inactive_count',
                    ],
                    'types' => [
                        '*' => ['value', 'label', 'description'],
                    ],
                ],
            ]);

        $this->assertEquals(4, $response->json('data.statistics.all_count'));
    }

    public function test_can_search_supervisors_by_name(): void
    {
        User::factory()->realEstateSupervisor()->create(['name' => 'سعد الشهري']);
        User::factory()->collectionSupervisor()->create(['name' => 'عبدالله القحطاني']);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index', ['search' => 'الشهري']));

        $response->assertOk()
            ->assertJsonCount(1, 'data.supervisors')
            ->assertJsonPath('data.supervisors.0.name', 'سعد الشهري');
    }

    public function test_can_filter_supervisors_by_type(): void
    {
        User::factory()->realEstateSupervisor()->create(['name' => 'مشرف عقارات 1']);
        User::factory()->collectionSupervisor()->create(['name' => 'مشرف تحصيل 1']);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.index', ['type' => 'real_estate_supervisor']));

        $response->assertOk()
            ->assertJsonCount(1, 'data.supervisors')
            ->assertJsonPath('data.supervisors.0.name', 'مشرف عقارات 1');
    }

    public function test_can_store_new_supervisor(): void
    {
        $payload = [
            'name' => 'فهد المطيري',
            'type' => UserType::RealEstateSupervisor->value,
            'phone' => '0555123456',
            'password' => 'secret123',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.supervisors.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'فهد المطيري')
            ->assertJsonPath('data.type', 'real_estate_supervisor')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'name' => 'فهد المطيري',
            'phone' => '0555123456',
            'type' => 'real_estate_supervisor',
            'is_active' => true,
        ]);
    }

    public function test_store_supervisor_validates_required_fields(): void
    {
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.supervisors.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'phone', 'password']);
    }

    public function test_can_show_supervisor_details(): void
    {
        $supervisor = User::factory()->maintenanceSupervisor()->create([
            'name' => 'سالم الصيانة',
            'phone' => '0566000000',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.supervisors.show', $supervisor));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $supervisor->id)
            ->assertJsonPath('data.name', 'سالم الصيانة')
            ->assertJsonPath('data.type', 'maintenance_supervisor');
    }

    public function test_can_update_supervisor_details(): void
    {
        $supervisor = User::factory()->collectionSupervisor()->create([
            'name' => 'مشرف قديم',
            'phone' => '0511111111',
        ]);

        $payload = [
            'name' => 'مشرف جديد معدل',
            'type' => UserType::RealEstateSupervisor->value,
            'phone' => '0522222222',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.supervisors.update', $supervisor), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'مشرف جديد معدل')
            ->assertJsonPath('data.type', 'real_estate_supervisor')
            ->assertJsonPath('data.phone', '0522222222');

        $this->assertDatabaseHas('users', [
            'id' => $supervisor->id,
            'name' => 'مشرف جديد معدل',
            'type' => 'real_estate_supervisor',
            'phone' => '0522222222',
        ]);
    }

    public function test_can_update_supervisor_password(): void
    {
        $supervisor = User::factory()->collectionSupervisor()->create([
            'password' => Hash::make('old_pass'),
            'phone' => '0511111111',
        ]);

        $payload = [
            'name' => $supervisor->name,
            'type' => $supervisor->type->value,
            'phone' => $supervisor->phone,
            'password' => 'new_secret_pass',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.supervisors.update', $supervisor), $payload);

        $response->assertOk();

        $supervisor->refresh();
        $this->assertTrue(Hash::check('new_secret_pass', $supervisor->password));
    }

    public function test_can_toggle_supervisor_status(): void
    {
        $supervisor = User::factory()->realEstateSupervisor()->create([
            'is_active' => true,
        ]);

        // 1. Deactivate
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.supervisors.toggle-status', $supervisor));

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'تم إيقاف حساب المشرف بنجاح.');

        $this->assertFalse($supervisor->fresh()->is_active);

        // 2. Reactivate
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.supervisors.toggle-status', $supervisor));

        $response->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'تم تفعيل حساب المشرف بنجاح.');

        $this->assertTrue($supervisor->fresh()->is_active);
    }

    public function test_general_manager_cannot_suspend_or_delete_own_account(): void
    {
        // Attempt suspend own account
        $toggleResponse = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.supervisors.toggle-status', $this->generalManager));

        $toggleResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'لا يمكنك إيقاف أو حذف حسابك أنت.');

        // Attempt delete own account
        $deleteResponse = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.supervisors.destroy', $this->generalManager));

        $deleteResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'لا يمكنك إيقاف أو حذف حسابك أنت.');

        $this->assertDatabaseHas('users', ['id' => $this->generalManager->id]);
    }

    public function test_can_delete_other_supervisor(): void
    {
        $supervisor = User::factory()->maintenanceSupervisor()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.supervisors.destroy', $supervisor));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم حذف حساب المشرف بنجاح.');

        $this->assertDatabaseMissing('users', ['id' => $supervisor->id]);
    }
}
