<?php

namespace Tests\Feature;

use App\Enums\ClaimGenerationMode;
use App\Enums\ContractStatus;
use App\Enums\DueStatus;
use App\Enums\PaymentCycle;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Payment;
use App\Models\Property;
use App\Models\TenantInfo;
use App\Models\Unit;
use App\Models\User;
use App\Services\DueScheduleService;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitsAndDuesArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $generalManager;

    private User $owner;

    private User $collector;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalManager = User::factory()->generalManager()->create();
        $this->owner = User::factory()->owner()->create(['name' => 'مالك تجريبي']);
        $this->collector = User::factory()->collector()->create(['name' => 'محصل تجريبي']);

        $this->property = Property::factory()->create([
            'name' => 'برج النرجس',
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);
    }

    public function test_creating_contract_creates_unit_entity_and_marks_it_occupied(): void
    {
        $tenantUser = User::factory()->tenant()->create(['name' => 'خالد بن فهد']);
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-1001',
            'property_id' => $this->property->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $payload = [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_number' => '101',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'total_amount' => 24000,
            'payment_cycle' => 'monthly',
            'installment_amount' => 2000,
            'claim_generation_mode' => 'from_start_date',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.contracts.store', $this->property), $payload);

        $response->assertStatus(201);

        // 1. Assert unit was created and linked to property
        $this->assertDatabaseHas('units', [
            'property_id' => $this->property->id,
            'unit_number' => '101',
            'status' => UnitStatus::Occupied->value,
        ]);

        $unit = Unit::where('property_id', $this->property->id)
            ->where('unit_number', '101')
            ->firstOrFail();

        // 2. Assert contract has unit_id foreign key
        $this->assertDatabaseHas('contracts', [
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'unit_number' => '101',
            'status' => ContractStatus::Active->value,
        ]);

        // 3. Test Property relations
        $this->assertEquals(1, $this->property->units()->count());
        $this->assertEquals(1, $this->property->occupiedUnits()->count());
        $this->assertEquals(0, $this->property->vacantUnits()->count());
    }

    public function test_terminating_contract_marks_unit_vacant(): void
    {
        $propertyService = app(PropertyService::class);

        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'property_id' => $this->property->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $contract = $propertyService->createContract($this->property, [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_number' => '202',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'total_amount' => 36000,
            'payment_cycle' => 'monthly',
            'installment_amount' => 3000,
            'claim_generation_mode' => 'from_start_date',
        ], $this->generalManager);

        $unit = $contract->unit;
        $this->assertNotNull($unit);
        $this->assertEquals(UnitStatus::Occupied, $unit->fresh()->status);

        // Terminate the contract
        $propertyService->terminateContract($contract, 'انتهاء مدة الإيجار وخروج المستأجر');

        // Unit should now be marked vacant
        $this->assertEquals(UnitStatus::Vacant, $unit->fresh()->status);
        $this->assertEquals(1, $this->property->vacantUnits()->count());
        $this->assertEquals(0, $this->property->occupiedUnits()->count());
    }

    public function test_creating_contract_generates_dues_schedule_for_monthly_cycle(): void
    {
        $propertyService = app(PropertyService::class);

        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'property_id' => $this->property->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $contract = $propertyService->createContract($this->property, [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_number' => '303',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_amount' => 12000,
            'payment_cycle' => 'monthly',
            'installment_amount' => 1000,
            'claim_generation_mode' => 'from_start_date',
        ], $this->generalManager);

        // 12 monthly dues should be created in the dues table
        $dues = $contract->dues()->get();
        $this->assertCount(12, $dues);

        $firstDue = $dues->first();
        $this->assertEquals('2026-01-01', $firstDue->due_date->format('Y-m-d'));
        $this->assertEquals('2026-01', $firstDue->period);
        $this->assertEquals(1000.0, (float) $firstDue->amount);
        $this->assertEquals(0.0, (float) $firstDue->paid_amount);
        $this->assertEquals(DueStatus::Pending, $firstDue->status);

        $lastDue = $dues->last();
        $this->assertEquals('2026-12-01', $lastDue->due_date->format('Y-m-d'));
        $this->assertEquals('2026-12', $lastDue->period);
        $this->assertEquals(1000.0, (float) $lastDue->amount);
    }

    public function test_creating_contract_generates_dues_schedule_for_quarterly_cycle(): void
    {
        $propertyService = app(PropertyService::class);

        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'property_id' => $this->property->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $contract = $propertyService->createContract($this->property, [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_number' => '404',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_amount' => 20000,
            'payment_cycle' => 'quarterly',
            'installment_amount' => 5000,
            'claim_generation_mode' => 'from_start_date',
        ], $this->generalManager);

        // 4 quarterly dues
        $this->assertEquals(4, $contract->dues()->count());

        $dues = $contract->dues()->orderBy('due_date')->get();
        $this->assertEquals('2026-01-01', $dues[0]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-04-01', $dues[1]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-07-01', $dues[2]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-10-01', $dues[3]->due_date->format('Y-m-d'));

        foreach ($dues as $due) {
            $this->assertEquals(5000.0, (float) $due->amount);
        }
    }

    public function test_recording_and_approving_payment_allocates_to_dues_auditably(): void
    {
        $propertyService = app(PropertyService::class);
        $dueScheduleService = app(DueScheduleService::class);

        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'property_id' => $this->property->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $contract = $propertyService->createContract($this->property, [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_number' => '505',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_amount' => 12000,
            'payment_cycle' => 'monthly',
            'installment_amount' => 1000,
            'claim_generation_mode' => 'from_start_date',
        ], $this->generalManager);

        // Pay 1500 for 2026-01
        $payment = Payment::create([
            'tenant_id' => $tenant->id,
            'collector_id' => $this->collector->id,
            'amount' => 1500,
            'payment_date' => '2026-01-15',
            'period' => '2026-01',
            'status' => PaymentStatus::Pending->value,
            'payment_method' => 'cash',
        ]);

        // Approve payment via API
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.payments.approvals.approve', $payment));

        $response->assertOk();

        // Due for 2026-01 should be fully paid (1000)
        $due1 = $contract->dues()->where('period', '2026-01')->firstOrFail();
        $this->assertEquals(1000.0, (float) $due1->paid_amount);
        $this->assertEquals(DueStatus::Paid, $due1->status);

        // Overflow of 500 should be allocated to next due (2026-02) as partial payment
        $due2 = $contract->dues()->where('period', '2026-02')->firstOrFail();
        $this->assertEquals(500.0, (float) $due2->paid_amount);
        $this->assertEquals(DueStatus::Partial, $due2->status);
        $this->assertEquals(500.0, $due2->remainingAmount());

        // Payment should be linked to due1
        $this->assertEquals($due1->id, $payment->fresh()->due_id);
    }

    public function test_can_create_contract_using_unit_id_directly(): void
    {
        $unit = Unit::factory()->create([
            'property_id' => $this->property->id,
            'unit_number' => '707',
            'status' => UnitStatus::Vacant,
        ]);

        $tenantUser = User::factory()->tenant()->create();
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $payload = [
            'is_new_tenant' => false,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'total_amount' => 30000,
            'payment_cycle' => 'monthly',
            'installment_amount' => 2500,
            'claim_generation_mode' => 'from_start_date',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.properties.contracts.store', $this->property), $payload);

        $response->assertStatus(201);
        $this->assertEquals(UnitStatus::Occupied, $unit->fresh()->status);
        $this->assertDatabaseHas('contracts', [
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_tenant_info_accessors_dynamically_read_from_contract_and_unit_as_single_source_of_truth(): void
    {
        $unit = Unit::factory()->create([
            'property_id' => $this->property->id,
            'unit_number' => '808',
            'status' => UnitStatus::Vacant,
        ]);

        $tenantUser = User::factory()->tenant()->create();
        // Create tenant WITHOUT any redundant contract values (all null)
        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'property_id' => $this->property->id,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
            'status' => 'regular',
        ]);

        $contract = Contract::create([
            'contract_number' => 'CNT-202611-9999',
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'total_amount' => 24000,
            'payment_cycle' => PaymentCycle::Monthly,
            'installment_amount' => 2000,
            'claim_generation_mode' => ClaimGenerationMode::FromStartDate,
            'status' => ContractStatus::Active,
        ]);

        // Generate dues
        app(DueScheduleService::class)->generateForContract($contract);

        $tenant->refresh();

        // 1. Contract number dynamically resolved
        $this->assertEquals('CNT-202611-9999', $tenant->contract_number);

        // 2. Property unit dynamically resolved from unit entity
        $this->assertEquals('برج النرجس - وحدة 808', $tenant->property_unit);

        // 3. Monthly target dynamically resolved from contract
        $this->assertEquals(2000.0, (float) $tenant->monthly_target);

        // 4. Due date dynamically resolved from earliest due
        $this->assertEquals('2026-11-01', $tenant->due_date->format('Y-m-d'));
    }
}
