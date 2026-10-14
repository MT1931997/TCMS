<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerManagementApiTest extends TestCase
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

    public function test_can_list_owners_with_statistics_and_tabs_counts(): void
    {
        $owner1 = User::factory()->owner()->create([
            'name' => 'المالك الأول',
            'national_id' => '1000000001',
        ]);
        $owner2 = User::factory()->owner()->create([
            'name' => 'المالك الثاني',
            'national_id' => '1000000002',
            'is_active' => false,
        ]);
        User::factory()->collector()->create(['name' => 'محصل']);

        // Create tenant assigned to owner1
        $tenantUser = User::factory()->tenant()->create();
        $collector = User::factory()->collector()->create();
        TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-2001',
            'property_unit' => 'عمارة النخيل - شقة 10',
            'due_date' => now()->toDateString(),
            'monthly_target' => 5000,
            'collector_id' => $collector->id,
            'owner_id' => $owner1->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.owners.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.owners')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'owners' => [
                        '*' => [
                            'id',
                            'name',
                            'username',
                            'phone',
                            'national_id',
                            'email',
                            'type',
                            'type_label',
                            'type_description',
                            'is_active',
                            'properties_count',
                            'tenants_count',
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
        $this->assertEquals(1, $response->json('data.tabs_counts.supervisors'));
        $this->assertEquals(2, $response->json('data.tabs_counts.collectors'));
        $this->assertEquals(2, $response->json('data.tabs_counts.owners'));
    }

    public function test_can_search_owners_by_name_national_id_or_phone(): void
    {
        User::factory()->owner()->create([
            'name' => 'صالح بن عبدالعزيز الراجحي',
            'national_id' => '1023456789',
            'phone' => '0566666666',
        ]);
        User::factory()->owner()->create([
            'name' => 'سليمان بن علي الدوسري',
            'national_id' => '1098765432',
            'phone' => '0577777777',
        ]);

        $byName = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.owners.index', ['search' => 'الراجحي']));
        $byName->assertOk()
            ->assertJsonCount(1, 'data.owners')
            ->assertJsonPath('data.owners.0.name', 'صالح بن عبدالعزيز الراجحي');

        $byNationalId = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.owners.index', ['search' => '1098765432']));
        $byNationalId->assertOk()
            ->assertJsonCount(1, 'data.owners')
            ->assertJsonPath('data.owners.0.name', 'سليمان بن علي الدوسري');
    }

    public function test_can_filter_owners_by_active_status(): void
    {
        User::factory()->owner()->create(['name' => 'مالك نشط', 'is_active' => true]);
        User::factory()->owner()->create(['name' => 'مالك موقوف', 'is_active' => false]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.owners.index', ['is_active' => 1]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.owners')
            ->assertJsonPath('data.owners.0.name', 'مالك نشط');
    }

    public function test_can_store_owner_with_modal_inputs(): void
    {
        $payload = [
            'name' => 'المالك الجديد',
            'national_id' => '1055555555',
            'phone' => '0588888888',
            'password' => '1234',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.owners.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'المالك الجديد')
            ->assertJsonPath('data.national_id', '1055555555')
            ->assertJsonPath('data.phone', '0588888888')
            ->assertJsonPath('data.type', UserType::Owner->value)
            ->assertJsonPath('data.is_active', true);

        $createdOwner = User::where('national_id', '1055555555')->first();
        $this->assertNotNull($createdOwner);
        $this->assertTrue(Hash::check('1234', $createdOwner->password));
        $this->assertNotEmpty($createdOwner->username);
    }

    public function test_owner_can_login_with_national_id_and_password(): void
    {
        User::factory()->owner()->create([
            'name' => 'المالك التجريبي للدخول',
            'national_id' => '1044444444',
            'password' => Hash::make('secret1234'),
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson(route('api.auth.login'), [
            'login' => '1044444444',
            'password' => 'secret1234',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.national_id', '1044444444')
            ->assertJsonPath('data.user.type', UserType::Owner->value);
    }

    public function test_owner_creation_validations(): void
    {
        User::factory()->owner()->create([
            'national_id' => '1011111111',
            'phone' => '0599999999',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.owners.store'), [
                'name' => '',
                'national_id' => '1011111111', // duplicate
                'phone' => '0599999999', // duplicate
                'password' => '12', // min 4
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'national_id', 'phone', 'password']);
    }

    public function test_can_show_owner(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'مالك تفصيلي']);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.owners.show', $owner));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.name', 'مالك تفصيلي');
    }

    public function test_cannot_show_non_owner_via_owner_endpoint(): void
    {
        $collector = User::factory()->collector()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.owners.show', $collector));

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_can_update_owner_details_and_password(): void
    {
        $owner = User::factory()->owner()->create([
            'name' => 'الاسم القديم',
            'national_id' => '1012312312',
            'phone' => '0512345678',
            'password' => Hash::make('oldpass'),
        ]);

        $payload = [
            'name' => 'الاسم المحدث للمالك',
            'national_id' => '1098798798',
            'phone' => '0587654321',
            'password' => 'newpassword123',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.owners.update', $owner), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'الاسم المحدث للمالك')
            ->assertJsonPath('data.national_id', '1098798798');

        $owner->refresh();
        $this->assertEquals('الاسم المحدث للمالك', $owner->name);
        $this->assertEquals('1098798798', $owner->national_id);
        $this->assertTrue(Hash::check('newpassword123', $owner->password));
    }

    public function test_can_toggle_owner_active_status(): void
    {
        $owner = User::factory()->owner()->create(['is_active' => true]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->patchJson(route('api.general-manager.owners.toggle-status', $owner));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($owner->fresh()->is_active);

        // Toggle back
        $responseBack = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.owners.toggle-status.post', $owner));

        $responseBack->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_cannot_delete_owner_with_assigned_tenants(): void
    {
        $owner = User::factory()->owner()->create();
        $tenantUser = User::factory()->tenant()->create();
        $collector = User::factory()->collector()->create();
        TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-2002',
            'property_unit' => 'عمارة الياسمين - شقة 5',
            'due_date' => now()->toDateString(),
            'monthly_target' => 4000,
            'collector_id' => $collector->id,
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.owners.destroy', $owner));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_can_delete_owner_without_assigned_tenants(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.owners.destroy', $owner));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    }
}
