<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\MaintenanceWork;
use App\Models\Payment;
use App\Models\Property;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestrictFinancialDeletesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    public function test_soft_deletes_columns_exist_on_tenant_info_and_payments_tables(): void
    {
        $this->assertTrue(Schema::hasColumn('tenant_info', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('payments', 'deleted_at'));
    }

    public function test_cannot_delete_collector_who_has_tenants(): void
    {
        $collector = User::factory()->collector()->create();
        $tenantUser = User::factory()->tenant()->create();

        TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-101',
            'property_unit' => 'وحدة 1',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $collector->id,
        ]);

        $this->expectException(QueryException::class);
        $collector->delete();
    }

    public function test_cannot_delete_tenant_who_has_payments(): void
    {
        $collector = User::factory()->collector()->create();
        $tenantUser = User::factory()->tenant()->create();

        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-102',
            'property_unit' => 'وحدة 2',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $collector->id,
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'collector_id' => $collector->id,
            'amount' => 500,
            'payment_date' => '2026-10-10',
            'payment_method' => 'كاش بالمكتب',
            'receipt_number' => 'REC-101',
            'period' => '2026-10',
        ]);

        $this->expectException(QueryException::class);
        $tenant->delete();
    }

    public function test_cannot_delete_property_or_tenant_with_contracts(): void
    {
        $owner = User::factory()->owner()->create();
        $collector = User::factory()->collector()->create();
        $tenantUser = User::factory()->tenant()->create();

        $property = Property::create([
            'name' => 'عقار التجربة',
            'owner_id' => $owner->id,
            'location' => 'الرياض',
        ]);

        $tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-103',
            'property_unit' => 'وحدة 3',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $collector->id,
        ]);

        Contract::create([
            'contract_number' => 'CNT-101',
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_number' => '101',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_amount' => 12000,
            'installment_amount' => 1000,
        ]);

        $this->expectException(QueryException::class);
        $property->delete();
    }

    public function test_cannot_delete_owner_with_properties(): void
    {
        $owner = User::factory()->owner()->create();

        Property::create([
            'name' => 'عقار المالك',
            'owner_id' => $owner->id,
            'location' => 'جدة',
        ]);

        $this->expectException(QueryException::class);
        $owner->delete();
    }

    public function test_cannot_delete_owner_with_maintenance_works(): void
    {
        $owner = User::factory()->owner()->create();

        MaintenanceWork::create([
            'work_order_number' => 'WO-999',
            'owner_id' => $owner->id,
            'property_unit' => 'وحدة 1',
            'title' => 'أعمال سباكة',
            'description' => 'إصلاح تسريب مياه',
        ]);

        $this->expectException(QueryException::class);
        $owner->delete();
    }
}
