<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserType;
use App\Models\Payment;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CollectorManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $generalManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalManager = User::factory()->generalManager()->create([
            'name' => 'المشرف العام',
            'phone' => '0500000001',
        ]);
    }

    public function test_can_list_collectors_with_statistics_and_tabs_counts(): void
    {
        $collector1 = User::factory()->collector()->create(['name' => 'المحصل الأول']);
        $collector2 = User::factory()->collector()->create(['name' => 'المحصل الثاني', 'is_active' => false]);
        User::factory()->realEstateSupervisor()->create(['name' => 'مشرف عقارات']);
        User::factory()->owner()->create(['name' => 'مالك تجريبي']);

        // Create tenant assigned to collector1
        $tenantUser = User::factory()->tenant()->create();
        $tenantInfo = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-1001',
            'property_unit' => 'شقة 101',
            'due_date' => now()->toDateString(),
            'monthly_target' => 5000,
            'collector_id' => $collector1->id,
        ]);

        Payment::create([
            'tenant_id' => $tenantInfo->id,
            'collector_id' => $collector1->id,
            'amount' => 2000,
            'payment_date' => now()->toDateString(),
            'period' => now()->format('Y-m'),
            'status' => PaymentStatus::Approved->value,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.collectors.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.collectors')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'collectors' => [
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
                            'tenants_count',
                            'collected_this_month',
                            'target_this_month',
                        ],
                    ],
                    'statistics' => [
                        'all_count',
                        'active_count',
                        'inactive_count',
                    ],
                    'tabs_counts' => [
                        'supervisors',
                        'collectors',
                        'owners',
                    ],
                ],
            ]);

        $this->assertEquals(2, $response->json('data.statistics.all_count'));
        $this->assertEquals(1, $response->json('data.statistics.active_count'));
        $this->assertEquals(1, $response->json('data.statistics.inactive_count'));
        $this->assertEquals(2, $response->json('data.tabs_counts.supervisors')); // 1 GM + 1 real estate
        $this->assertEquals(2, $response->json('data.tabs_counts.collectors'));
        $this->assertEquals(1, $response->json('data.tabs_counts.owners'));
    }

    public function test_can_search_collectors_by_name_or_phone(): void
    {
        User::factory()->collector()->create(['name' => 'محمد أحمد القحطاني', 'phone' => '0511111111']);
        User::factory()->collector()->create(['name' => 'خالد السبيعي', 'phone' => '0522222222']);

        $responseByName = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.collectors.index', ['search' => 'القحطاني']));

        $responseByName->assertOk()
            ->assertJsonCount(1, 'data.collectors')
            ->assertJsonPath('data.collectors.0.name', 'محمد أحمد القحطاني');

        $responseByPhone = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.collectors.index', ['search' => '0522222222']));

        $responseByPhone->assertOk()
            ->assertJsonCount(1, 'data.collectors')
            ->assertJsonPath('data.collectors.0.name', 'خالد السبيعي');
    }

    public function test_can_filter_collectors_by_active_status(): void
    {
        User::factory()->collector()->create(['name' => 'محصل نشط', 'is_active' => true]);
        User::factory()->collector()->create(['name' => 'محصل موقوف', 'is_active' => false]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.collectors.index', ['is_active' => 0]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.collectors')
            ->assertJsonPath('data.collectors.0.name', 'محصل موقوف');
    }

    public function test_can_store_collector_with_modal_inputs(): void
    {
        $payload = [
            'name' => 'المحصل التجريبي الجديد',
            'phone' => '0533333333',
            'password' => '1234',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.collectors.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'المحصل التجريبي الجديد')
            ->assertJsonPath('data.phone', '0533333333')
            ->assertJsonPath('data.type', UserType::Collector->value)
            ->assertJsonPath('data.is_active', true);

        $createdCollector = User::where('phone', '0533333333')->first();
        $this->assertNotNull($createdCollector);
        $this->assertTrue(Hash::check('1234', $createdCollector->password));
        $this->assertNotEmpty($createdCollector->username);
    }

    public function test_collector_creation_validations(): void
    {
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.collectors.store'), [
                'name' => '',
                'password' => '12', // min 4 required
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'password']);
    }

    public function test_can_show_collector(): void
    {
        $collector = User::factory()->collector()->create(['name' => 'محصل تفصيلي']);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.collectors.show', $collector));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $collector->id)
            ->assertJsonPath('data.name', 'محصل تفصيلي');
    }

    public function test_cannot_show_non_collector_via_collector_endpoint(): void
    {
        $supervisor = User::factory()->realEstateSupervisor()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.collectors.show', $supervisor));

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_can_update_collector_details_and_password(): void
    {
        $collector = User::factory()->collector()->create([
            'name' => 'الاسم القديم',
            'phone' => '0544444444',
            'password' => Hash::make('oldpass'),
        ]);

        $payload = [
            'name' => 'الاسم المحدث',
            'phone' => '0555555555',
            'password' => 'newpassword123',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.collectors.update', $collector), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'الاسم المحدث')
            ->assertJsonPath('data.phone', '0555555555');

        $collector->refresh();
        $this->assertEquals('الاسم المحدث', $collector->name);
        $this->assertEquals('0555555555', $collector->phone);
        $this->assertTrue(Hash::check('newpassword123', $collector->password));
    }

    public function test_can_toggle_collector_active_status(): void
    {
        $collector = User::factory()->collector()->create(['is_active' => true]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->patchJson(route('api.general-manager.collectors.toggle-status', $collector));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($collector->fresh()->is_active);

        // Toggle back to active
        $responseBack = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.collectors.toggle-status.post', $collector));

        $responseBack->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($collector->fresh()->is_active);
    }

    public function test_cannot_delete_collector_with_assigned_tenants(): void
    {
        $collector = User::factory()->collector()->create();
        $tenantUser = User::factory()->tenant()->create();
        TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-1002',
            'property_unit' => 'شقة 102',
            'due_date' => now()->toDateString(),
            'monthly_target' => 3000,
            'collector_id' => $collector->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.collectors.destroy', $collector));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $collector->id]);
    }

    public function test_can_delete_collector_without_assigned_tenants(): void
    {
        $collector = User::factory()->collector()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.collectors.destroy', $collector));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $collector->id]);
    }
}
