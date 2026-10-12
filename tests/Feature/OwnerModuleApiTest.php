<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerModuleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private User $ownerB;

    private User $collector;

    private TenantInfo $tenantA;

    private TenantInfo $tenantB;

    private Payment $paymentA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = User::factory()->owner()->create(['name' => 'المالك الأول']);
        $this->ownerB = User::factory()->owner()->create(['name' => 'المالك الثاني']);
        $this->collector = User::factory()->collector()->create(['name' => 'المحصل الأول']);

        $userTenantA = User::factory()->tenant()->create([
            'name' => 'محمد احمد',
            'phone' => '01143693590',
        ]);

        $this->tenantA = TenantInfo::create([
            'user_id' => $userTenantA->id,
            'tenant_number' => '1212',
            'property_unit' => 'برج الامل',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->ownerA->id,
        ]);

        $this->paymentA = Payment::create([
            'tenant_id' => $this->tenantA->id,
            'collector_id' => $this->collector->id,
            'amount' => 500,
            'payment_date' => '2026-10-10',
            'payment_method' => 'كاش بالمكتب',
            'receipt_number' => '12121',
            'period' => '2026-10',
            'status' => 'pending',
            'notes' => 'سداد قيد المراجعة',
        ]);

        $userTenantB = User::factory()->tenant()->create([
            'name' => 'سعد الغامدي',
            'phone' => '0555555555',
        ]);

        $this->tenantB = TenantInfo::create([
            'user_id' => $userTenantB->id,
            'tenant_number' => '1414',
            'property_unit' => 'برج النخيل',
            'due_date' => '2026-10-25',
            'monthly_target' => 2000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->ownerB->id,
        ]);

        Payment::create([
            'tenant_id' => $this->tenantB->id,
            'collector_id' => $this->collector->id,
            'amount' => 2000,
            'payment_date' => '2026-10-10',
            'payment_method' => 'تحويل بنكي',
            'receipt_number' => '99999',
            'period' => '2026-10',
            'status' => 'approved',
        ]);
    }

    public function test_owner_can_view_their_unit_payments_سدادات_وحداتي(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.payments.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.id', $this->paymentA->id)
            ->assertJsonPath('data.payments.0.receipt_number', '12121')
            ->assertJsonPath('data.payments.0.tenant.property_unit', 'برج الامل')
            ->assertJsonPath('data.payments.0.tenant.name', 'محمد احمد')
            ->assertJsonPath('data.payments.0.amount', 500)
            ->assertJsonPath('data.payments.0.status_label', 'قيد المراجعة')
            ->assertJsonPath('data.statistics.total_count', 1)
            ->assertJsonPath('data.statistics.total_amount', 500);
    }

    public function test_owner_cannot_see_other_owners_payments(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.payments.index'));

        $response->assertOk();
        $paymentIds = collect($response->json('data.payments'))->pluck('id');
        $this->assertContains($this->paymentA->id, $paymentIds);
        $this->assertCount(1, $paymentIds);
    }

    public function test_owner_can_search_payments(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.payments.index', ['search' => '12121']));

        $response->assertOk()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.receipt_number', '12121');

        $emptyResponse = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.payments.index', ['search' => 'غير موجود']));

        $emptyResponse->assertOk()
            ->assertJsonCount(0, 'data.payments');
    }

    public function test_owner_can_view_payment_notes_modal(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.payments.notes', $this->paymentA));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.property_unit', 'برج الامل')
            ->assertJsonPath('data.payment.amount', 500)
            ->assertJsonPath('data.payment.period', '2026-10')
            ->assertJsonCount(0, 'data.notes');
    }

    public function test_owner_can_send_note_on_payment(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->postJson(route('api.owner.payments.notes.store', $this->paymentA), [
                'content' => 'يرجى مراجعة إشعار التحويل البنكي والتأكيد',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content', 'يرجى مراجعة إشعار التحويل البنكي والتأكيد')
            ->assertJsonPath('data.author_name', 'المالك الأول')
            ->assertJsonPath('data.author_role', 'مالك');

        $this->assertDatabaseHas('payment_notes', [
            'payment_id' => $this->paymentA->id,
            'user_id' => $this->ownerA->id,
            'content' => 'يرجى مراجعة إشعار التحويل البنكي والتأكيد',
        ]);
    }

    public function test_owner_cannot_access_or_comment_on_another_owners_payment(): void
    {
        $response = $this->actingAs($this->ownerB, 'sanctum')
            ->getJson(route('api.owner.payments.show', $this->paymentA));

        $response->assertStatus(403);

        $commentResponse = $this->actingAs($this->ownerB, 'sanctum')
            ->postJson(route('api.owner.payments.notes.store', $this->paymentA), [
                'content' => 'محاولة اختراق',
            ]);

        $commentResponse->assertStatus(403);
    }

    public function test_owner_can_view_properties_management_إدارة_أملاكي(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.properties.index', ['period' => '2026-10']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_properties', true)
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'برج الامل')
            ->assertJsonPath('data.properties.0.units_count', 1)
            ->assertJsonCount(1, 'data.tenants')
            ->assertJsonPath('data.tenants.0.property_unit', 'برج الامل')
            ->assertJsonPath('data.tenants.0.tenant_name', 'محمد احمد')
            ->assertJsonPath('data.tenants.0.collector_name', 'المحصل الأول')
            ->assertJsonPath('data.tenants.0.monthly_target', 1000)
            ->assertJsonPath('data.tenants.0.paid_amount', 0)
            ->assertJsonPath('data.tenants.0.status_label', 'لم يسدد')
            ->assertJsonPath('data.statistics.total_units', 1)
            ->assertJsonPath('data.statistics.total_target', 1000);
    }

    public function test_owner_with_no_properties_returns_empty_state(): void
    {
        $newOwner = User::factory()->owner()->create(['name' => 'مالك جديد بدون عقارات']);

        $response = $this->actingAs($newOwner, 'sanctum')
            ->getJson(route('api.owner.properties.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_properties', false)
            ->assertJsonCount(0, 'data.properties')
            ->assertJsonCount(0, 'data.tenants')
            ->assertJsonPath('data.statistics.total_units', 0);
    }

    public function test_owner_can_view_overview_dashboard_نظرة_عامة(): void
    {
        // Mark payment A as approved so it counts towards collected amounts
        $this->paymentA->update(['status' => 'approved']);

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson(route('api.owner.overview', ['period' => '2026-10']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', '2026-10')
            ->assertJsonPath('data.kpis.collected_this_month', 500)
            ->assertJsonPath('data.kpis.target_this_month', 1000)
            ->assertJsonPath('data.kpis.collection_percentage', 50)
            ->assertJsonPath('data.kpis.total_units_count', 1)
            ->assertJsonPath('data.kpis.total_collected_all_time', 500)
            ->assertJsonPath('data.monthly_progress.percentage', 50)
            ->assertJsonCount(1, 'data.assigned_collectors')
            ->assertJsonPath('data.assigned_collectors.0.name', 'المحصل الأول')
            ->assertJsonPath('data.assigned_collectors.0.units_count', 1)
            ->assertJsonPath('data.assigned_collectors.0.target_amount', 1000)
            ->assertJsonPath('data.assigned_collectors.0.collected_amount', 500)
            ->assertJsonPath('data.assigned_collectors.0.percentage', 50)
            ->assertJsonCount(6, 'data.chart_last_6_months')
            ->assertJsonPath('data.chart_last_6_months.5.period', '2026-10')
            ->assertJsonPath('data.chart_last_6_months.5.collected_amount', 500);
    }
}
