<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\TenantInfo;
use App\Models\TenantNote;
use App\Models\TenantPromise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTenantApiTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $collector;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->supervisor()->create();
        $this->collector = User::factory()->collector()->create();
        $this->owner = User::factory()->owner()->create();
    }

    public function test_supervisor_can_list_tenants_with_search_and_filters(): void
    {
        $user1 = User::factory()->tenant()->create([
            'name' => 'محمد عبدالله',
            'phone' => '0555555555',
        ]);

        TenantInfo::create([
            'user_id' => $user1->id,
            'tenant_number' => 'T-1024',
            'property_unit' => 'برج الأمل - وحدة 12',
            'due_date' => '2026-10-15',
            'monthly_target' => 1500,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
        ]);

        $user2 = User::factory()->tenant()->create([
            'name' => 'خالد العتيبي',
            'phone' => '0544444444',
        ]);

        TenantInfo::create([
            'user_id' => $user2->id,
            'tenant_number' => 'T-2000',
            'property_unit' => 'عمارة النور - شقة 3',
            'due_date' => '2026-10-20',
            'monthly_target' => 2000,
            'collector_id' => $this->collector->id,
        ]);

        // Search test by name
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.tenants.index', ['search' => 'محمد']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tenants')
            ->assertJsonPath('data.tenants.0.tenant_number', 'T-1024')
            ->assertJsonPath('data.tenants.0.name', 'محمد عبدالله');

        // Collector filter test
        $responseAll = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.tenants.index', ['collector_id' => $this->collector->id]));

        $responseAll->assertOk()
            ->assertJsonCount(2, 'data.tenants');
    }

    public function test_supervisor_can_create_a_tenant(): void
    {
        $payload = [
            'name' => 'محمد عبدالله',
            'tenant_number' => 'T-1024',
            'phone' => '0512345678',
            'property_unit' => 'برج الأمل - وحدة 12',
            'due_date' => '2026-10-10',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'national_id' => '1023456789',
            'birth_date' => '1995-05-15',
            'notes' => 'مستأجر جديد',
        ];

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.tenants.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_info.name', 'محمد عبدالله')
            ->assertJsonPath('data.tenant_info.tenant_number', 'T-1024')
            ->assertJsonPath('data.current_month_target.target_amount', 1000);

        // Assert user created in users table
        $this->assertDatabaseHas('users', [
            'name' => 'محمد عبدالله',
            'phone' => '0512345678',
            'type' => UserType::Tenant->value,
        ]);

        // Assert details created in tenant_info table
        $this->assertDatabaseHas('tenant_info', [
            'tenant_number' => 'T-1024',
            'property_unit' => 'برج الأمل - وحدة 12',
        ]);
    }

    public function test_supervisor_can_view_tenant_details(): void
    {
        $user = User::factory()->tenant()->create([
            'name' => 'محمد احمد',
            'phone' => '01143693590',
        ]);

        $tenant = TenantInfo::create([
            'user_id' => $user->id,
            'tenant_number' => '1212',
            'property_unit' => 'برج الامل',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
        ]);

        TenantNote::create([
            'tenant_id' => $tenant->id,
            'user_id' => $this->supervisor->id,
            'content' => 'ملاحظة أولى',
        ]);

        TenantPromise::create([
            'tenant_id' => $tenant->id,
            'user_id' => $this->supervisor->id,
            'promise_date' => '2026-10-25',
            'note' => 'وعد بالسداد',
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.tenants.show', $tenant));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_info.tenant_number', '1212')
            ->assertJsonPath('data.tenant_info.name', 'محمد احمد')
            ->assertJsonCount(1, 'data.notes_list')
            ->assertJsonCount(1, 'data.promises')
            ->assertJsonPath('data.current_month_target.target_amount', 1000);
    }

    public function test_supervisor_can_update_tenant(): void
    {
        $user = User::factory()->tenant()->create([
            'name' => 'محمد احمد',
            'phone' => '01143693590',
        ]);

        $tenant = TenantInfo::create([
            'user_id' => $user->id,
            'tenant_number' => '1212',
            'property_unit' => 'برج الامل',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->putJson(route('api.supervisor.tenants.update', $tenant), [
                'name' => 'محمد أحمد المعدل',
                'monthly_target' => 1200,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_info.name', 'محمد أحمد المعدل')
            ->assertJsonPath('data.current_month_target.target_amount', 1200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'محمد أحمد المعدل',
        ]);
    }

    public function test_supervisor_can_add_promise_appointment(): void
    {
        $user = User::factory()->tenant()->create([
            'name' => 'سعيد الغامدي',
            'phone' => '0500000000',
        ]);

        $tenant = TenantInfo::create([
            'user_id' => $user->id,
            'tenant_number' => 'T-999',
            'property_unit' => 'وحدة 5',
            'due_date' => '2026-10-10',
            'monthly_target' => 800,
            'collector_id' => $this->collector->id,
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.tenants.promises.store', $tenant), [
                'promise_date' => '2026-10-28',
                'note' => 'تم الاتفاق على السداد يوم 28',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.note', 'تم الاتفاق على السداد يوم 28');

        $this->assertDatabaseHas('tenant_promises', [
            'tenant_id' => $tenant->id,
            'note' => 'تم الاتفاق على السداد يوم 28',
        ]);
    }

    public function test_supervisor_can_add_tenant_note(): void
    {
        $user = User::factory()->tenant()->create([
            'name' => 'سعيد الغامدي',
            'phone' => '0500000000',
        ]);

        $tenant = TenantInfo::create([
            'user_id' => $user->id,
            'tenant_number' => 'T-999',
            'property_unit' => 'وحدة 5',
            'due_date' => '2026-10-10',
            'monthly_target' => 800,
            'collector_id' => $this->collector->id,
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.tenants.notes.store', $tenant), [
                'content' => 'ملاحظة هامة: تم تغيير رقم الجوال البديل',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content', 'ملاحظة هامة: تم تغيير رقم الجوال البديل');

        $this->assertDatabaseHas('tenant_notes', [
            'tenant_id' => $tenant->id,
            'content' => 'ملاحظة هامة: تم تغيير رقم الجوال البديل',
        ]);
    }

    public function test_supervisor_can_record_payment_and_target_updates(): void
    {
        $user = User::factory()->tenant()->create([
            'name' => 'سعيد الغامدي',
            'phone' => '0500000000',
        ]);

        $tenant = TenantInfo::create([
            'user_id' => $user->id,
            'tenant_number' => 'T-999',
            'property_unit' => 'وحدة 5',
            'due_date' => '2026-10-10',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
        ]);

        $currentPeriod = now()->format('Y-m');

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.tenants.payments.store', $tenant), [
                'amount' => 400,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'تحويل بنكي',
                'receipt_number' => 'REC-1001',
                'period' => $currentPeriod,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 400);

        // Verify show tenant returns updated target progress
        $showResponse = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.tenants.show', $tenant));

        $showResponse->assertOk()
            ->assertJsonPath('data.current_month_target.target_amount', 1000)
            ->assertJsonPath('data.current_month_target.collected_amount', 400)
            ->assertJsonPath('data.current_month_target.remaining_amount', 600)
            ->assertJsonPath('data.current_month_target.percentage', 40)
            ->assertJsonCount(1, 'data.payments_ledger');
    }
}
