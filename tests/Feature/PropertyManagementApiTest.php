<?php

namespace Tests\Feature;

use App\Enums\ClaimGenerationMode;
use App\Enums\ContractStatus;
use App\Enums\PaymentCycle;
use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Property;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $generalManager;

    private User $realEstateSupervisor;

    private User $owner1;

    private User $owner2;

    private User $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalManager = User::factory()->generalManager()->create();
        $this->realEstateSupervisor = User::factory()->realEstateSupervisor()->create();
        $this->owner1 = User::factory()->owner()->create(['name' => 'المالك الأول - تيست']);
        $this->owner2 = User::factory()->owner()->create(['name' => 'المالك الثاني - النخيل']);
        $this->collector = User::factory()->collector()->create(['name' => 'المحصل الأول']);
    }

    public function test_can_list_properties_with_statistics_and_owners_list(): void
    {
        $property = Property::factory()->create([
            'name' => 'برج الأمل',
            'location' => 'الرياض - حي النرجس',
            'owner_id' => $this->owner1->id,
            'created_by' => $this->generalManager->id,
        ]);

        $tenantUser = User::factory()->tenant()->create();

        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-101',
            'contract_number' => 'CNT-2026-001',
            'property_unit' => 'شقة 1',
            'property_id' => $property->id,
            'due_date' => now()->format('Y-m-d'),
            'monthly_target' => 2000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'status' => 'regular',
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'collector_id' => $this->collector->id,
            'amount' => 500,
            'payment_date' => now()->format('Y-m-d'),
            'period' => now()->format('Y-m'),
            'payment_method' => 'cash',
            'status' => PaymentStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم جلب قائمة العقارات بنجاح.')
            ->assertJsonPath('data.statistics.properties_count', 1)
            ->assertJsonPath('data.statistics.active_contracts_count', 1)
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'برج الأمل')
            ->assertJsonPath('data.properties.0.owner_name', 'المالك الأول - تيست')
            ->assertJsonPath('data.properties.0.active_contracts_count', 1)
            ->assertJsonPath('data.properties.0.collected_this_month', 500)
            ->assertJsonPath('data.properties.0.overdue_amount', 1500)
            ->assertJsonCount(2, 'data.owners');
    }

    public function test_can_search_properties_by_name_location_or_owner(): void
    {
        Property::factory()->create([
            'name' => 'برج الصفوة',
            'location' => 'الرياض - حي النرجس',
            'owner_id' => $this->owner1->id,
        ]);

        Property::factory()->create([
            'name' => 'عمارة النور',
            'location' => 'جدة - حي الشاطئ',
            'owner_id' => $this->owner2->id,
        ]);

        // Search by property name
        $resName = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.index', ['search' => 'الصفوة']));

        $resName->assertOk()
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'برج الصفوة');

        // Search by location
        $resLoc = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.index', ['search' => 'الشاطئ']));

        $resLoc->assertOk()
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'عمارة النور');

        // Search by owner name
        $resOwner = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.index', ['search' => 'النخيل']));

        $resOwner->assertOk()
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'عمارة النور');
    }

    public function test_can_filter_properties_by_owner_id(): void
    {
        Property::factory()->create([
            'name' => 'عقار المالك الأول',
            'owner_id' => $this->owner1->id,
        ]);

        Property::factory()->create([
            'name' => 'عقار المالك الثاني',
            'owner_id' => $this->owner2->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.index', ['owner_id' => $this->owner1->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'عقار المالك الأول');
    }

    public function test_can_create_a_new_property(): void
    {
        $payload = [
            'owner_id' => $this->owner1->id,
            'name' => 'برج الأمل الجديد',
            'location' => 'الرياض - حي النرجس',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تمت إضافة العقار بنجاح.')
            ->assertJsonPath('data.name', 'برج الأمل الجديد')
            ->assertJsonPath('data.location', 'الرياض - حي النرجس')
            ->assertJsonPath('data.owner_id', $this->owner1->id)
            ->assertJsonPath('data.owner_name', 'المالك الأول - تيست');

        $this->assertDatabaseHas('properties', [
            'name' => 'برج الأمل الجديد',
            'owner_id' => $this->owner1->id,
            'location' => 'الرياض - حي النرجس',
            'created_by' => $this->generalManager->id,
        ]);
    }

    public function test_validation_fails_when_creating_with_invalid_data(): void
    {
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.store'), [
                'name' => '',
                'owner_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'owner_id']);
    }

    public function test_can_show_a_single_property_page_with_statistics_and_the_three_tables(): void
    {
        $property = Property::factory()->create([
            'name' => 'تيست',
            'owner_id' => $this->owner1->id,
        ]);

        // Tenant 1 with active contract
        $user1 = User::factory()->tenant()->create(['name' => 'احمد محمد', 'phone' => '01143693590', 'national_id' => '121212121']);
        $tenant1 = TenantInfo::create([
            'user_id' => $user1->id,
            'tenant_number' => 'T-78',
            'property_unit' => 'تيست - وحدة 78',
            'property_id' => $property->id,
            'due_date' => '2026-10-21',
            'monthly_target' => 833,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'national_id' => '121212121',
            'status' => 'regular',
        ]);

        Contract::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant1->id,
            'unit_number' => '78',
            'start_date' => '2026-10-21',
            'end_date' => '2027-10-20',
            'total_amount' => 10000,
            'payment_cycle' => PaymentCycle::Monthly,
            'installment_amount' => 833,
            'claim_generation_mode' => ClaimGenerationMode::CurrentMonthOnly,
            'status' => ContractStatus::Active,
        ]);

        // Tenant 2 with closed contract (appears in both previous contracts and uncontracted tenants table)
        $user2 = User::factory()->tenant()->create(['name' => 'محمد محمد', 'phone' => '0102849444']);
        $tenant2 = TenantInfo::create([
            'user_id' => $user2->id,
            'tenant_number' => 'T-80',
            'property_unit' => 'تيست - وحدة 80',
            'property_id' => $property->id,
            'due_date' => '2026-10-21',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'status' => 'regular',
        ]);

        Contract::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant2->id,
            'unit_number' => '80',
            'start_date' => '2026-10-21',
            'end_date' => '2027-10-20',
            'total_amount' => 12000,
            'payment_cycle' => PaymentCycle::Monthly,
            'installment_amount' => 1000,
            'claim_generation_mode' => ClaimGenerationMode::CurrentMonthOnly,
            'status' => ContractStatus::Closed,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.show', $property));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.property.name', 'تيست')
            ->assertJsonPath('data.statistics.active_contracts_count', 1)
            ->assertJsonPath('data.statistics.active_contracts_total_value', 10000)
            ->assertJsonPath('data.statistics.total_overdue', 833)
            // Table 1: Active contracts
            ->assertJsonCount(1, 'data.active_contracts')
            ->assertJsonPath('data.active_contracts.0.unit_number', '78')
            ->assertJsonPath('data.active_contracts.0.tenant_name', 'احمد محمد')
            ->assertJsonPath('data.active_contracts.0.status', 'active')
            // Table 2: Tenants without active contract
            ->assertJsonCount(1, 'data.tenants_without_active_contract')
            ->assertJsonPath('data.tenants_without_active_contract.0.tenant_name', 'محمد محمد')
            // Table 3: Previous contracts log
            ->assertJsonCount(1, 'data.previous_contracts')
            ->assertJsonPath('data.previous_contracts.0.unit_number', '80')
            ->assertJsonPath('data.previous_contracts.0.status', 'closed')
            ->assertJsonPath('data.previous_contracts.0.status_label', 'مُغلق');
    }

    public function test_can_get_contract_options(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.properties.contracts.options', $property));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.payment_cycles')
            ->assertJsonPath('data.payment_cycles.0.label', 'شهري')
            ->assertJsonCount(3, 'data.claim_generation_modes')
            ->assertJsonPath('data.claim_generation_modes.0.label', 'من الشهر الحالي فقط')
            ->assertJsonCount(3, 'data.contract_statuses')
            ->assertJsonPath('data.contract_statuses.0.label', 'ساري')
            ->assertJsonPath('data.contract_statuses.1.label', 'مُجدّد')
            ->assertJsonPath('data.contract_statuses.2.label', 'مُغلق');
    }

    public function test_can_create_contract_for_existing_tenant(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);

        $tenantUser = User::factory()->tenant()->create(['name' => 'خالد الشمري']);
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-999',
            'property_unit' => 'قديم',
            'due_date' => '2026-10-10',
            'monthly_target' => 0,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'status' => 'regular',
        ]);

        $payload = [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_number' => '12',
            'start_date' => '2026-10-21',
            'end_date' => '2027-10-20',
            'total_amount' => 24000,
            'payment_cycle' => 'monthly',
            'claim_generation_mode' => 'current_month_only',
            'notes' => 'عقد جديد لمستأجر قائم',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.contracts.store', $property), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unit_number', '12')
            ->assertJsonPath('data.total_amount', 24000)
            ->assertJsonPath('data.installment_amount', 2000)
            ->assertJsonPath('data.payment_cycle', 'monthly')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('contracts', [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_number' => '12',
            'status' => 'active',
        ]);

        $tenant->refresh();
        $this->assertEquals($property->id, $tenant->property_id);
    }

    public function test_can_create_contract_for_new_tenant(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);

        $payload = [
            'is_new_tenant' => true,
            'name' => 'سعد القحطاني',
            'phone' => '0555123456',
            'national_id' => '1099887766',
            'collector_id' => $this->collector->id,
            'unit_number' => '55',
            'start_date' => '2026-10-21',
            'end_date' => '2027-10-20',
            'total_amount' => 40000,
            'payment_cycle' => 'quarterly',
            'claim_generation_mode' => 'from_start_date',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.contracts.store', $property), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_name', 'سعد القحطاني')
            ->assertJsonPath('data.unit_number', '55')
            ->assertJsonPath('data.total_amount', 40000)
            ->assertJsonPath('data.installment_amount', 10000);

        $this->assertDatabaseHas('users', [
            'name' => 'سعد القحطاني',
            'phone' => '0555123456',
        ]);
    }

    public function test_can_update_contract(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);
        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-888',
            'property_unit' => 'وحدة 1',
            'due_date' => '2026-10-10',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'status' => 'regular',
        ]);

        $contract = Contract::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_number' => '1',
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'total_amount' => 12000,
            'payment_cycle' => PaymentCycle::Monthly,
            'installment_amount' => 1000,
            'claim_generation_mode' => ClaimGenerationMode::CurrentMonthOnly,
            'status' => ContractStatus::Active,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.properties.contracts.update', [$property, $contract]), [
                'unit_number' => '10',
                'total_amount' => 15000,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unit_number', '10')
            ->assertJsonPath('data.total_amount', 15000);
    }

    public function test_can_terminate_contract(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);
        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-777',
            'property_unit' => 'وحدة 3',
            'due_date' => '2026-10-10',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'status' => 'regular',
        ]);

        $contract = Contract::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_number' => '3',
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'total_amount' => 12000,
            'payment_cycle' => PaymentCycle::Monthly,
            'installment_amount' => 1000,
            'claim_generation_mode' => ClaimGenerationMode::CurrentMonthOnly,
            'status' => ContractStatus::Active,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.contracts.terminate', [$property, $contract]), [
                'closure_reason' => 'بناءً على طلب المستأجر',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.status_label', 'مُغلق');

        $contract->refresh();
        $this->assertEquals(ContractStatus::Closed, $contract->status);
    }

    public function test_can_renew_contract(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);
        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-666',
            'property_unit' => 'وحدة 9',
            'due_date' => '2026-10-10',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner1->id,
            'status' => 'regular',
        ]);

        $contract = Contract::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_number' => '9',
            'start_date' => '2025-10-01',
            'end_date' => '2026-09-30',
            'total_amount' => 12000,
            'payment_cycle' => PaymentCycle::Monthly,
            'installment_amount' => 1000,
            'claim_generation_mode' => ClaimGenerationMode::CurrentMonthOnly,
            'status' => ContractStatus::Active,
        ]);

        $payload = [
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'total_amount' => 14400,
            'payment_cycle' => 'monthly',
            'claim_generation_mode' => 'current_month_only',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.contracts.renew', [$property, $contract]), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.total_amount', 14400)
            ->assertJsonPath('data.installment_amount', 1200);

        // Previous contract is now marked renewed
        $contract->refresh();
        $this->assertEquals(ContractStatus::Renewed, $contract->status);
    }

    public function test_real_estate_supervisor_can_access_contracts_routes(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner1->id]);

        $response = $this->actingAs($this->realEstateSupervisor, 'sanctum')
            ->getJson(route('api.real-estate-supervisor.properties.contracts.options', $property));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
