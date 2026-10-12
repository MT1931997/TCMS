<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $collector;

    private User $owner;

    private TenantInfo $tenant;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->supervisor()->create(['name' => 'المشرف الأول']);
        $this->collector = User::factory()->collector()->create(['name' => 'المحصل الأول']);
        $this->owner = User::factory()->owner()->create(['name' => 'المالك الأول']);

        $tenantUser = User::factory()->tenant()->create([
            'name' => 'محمد احمد',
            'phone' => '01143693590',
        ]);

        $this->tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => '1212',
            'property_unit' => 'برج الامل',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $this->collector->id,
            'owner_id' => $this->owner->id,
        ]);

        $this->payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'amount' => 500,
            'payment_date' => '2026-10-10',
            'payment_method' => 'كاش بالمكتب',
            'receipt_number' => '12121',
            'period' => '2026-10',
            'status' => 'pending',
            'notes' => 'سداد باليد',
        ]);
    }

    public function test_supervisor_can_view_payment_approvals_with_pending_tab_and_counters(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.payments.approvals.index', ['tab' => 'pending']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.id', $this->payment->id)
            ->assertJsonPath('data.payments.0.status', 'pending')
            ->assertJsonPath('data.payments.0.status_label', 'قيد المراجعة')
            ->assertJsonPath('data.tabs.pending_count', 1)
            ->assertJsonPath('data.tabs.all_count', 1);
    }

    public function test_supervisor_can_view_all_payments_tab(): void
    {
        // Add an approved payment
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'amount' => 500,
            'payment_date' => '2026-10-09',
            'payment_method' => 'تحويل بنكي',
            'receipt_number' => '88888',
            'period' => '2026-10',
            'status' => 'approved',
        ]);

        $pendingResponse = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.payments.approvals.index', ['tab' => 'pending']));

        $pendingResponse->assertOk()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.tabs.pending_count', 1)
            ->assertJsonPath('data.tabs.all_count', 2);

        $allResponse = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.payments.approvals.index', ['tab' => 'all']));

        $allResponse->assertOk()
            ->assertJsonCount(2, 'data.payments');
    }

    public function test_supervisor_can_search_payments_in_approvals(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.payments.approvals.index', ['search' => '12121']));

        $response->assertOk()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.receipt_number', '12121');

        $emptyResponse = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.payments.approvals.index', ['search' => 'غير موجود']));

        $emptyResponse->assertOk()
            ->assertJsonCount(0, 'data.payments');
    }

    public function test_supervisor_can_approve_payment(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.payments.approvals.approve', $this->payment));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم اعتماد السداد بنجاح.')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'معتمد')
            ->assertJsonPath('data.reviewer.name', 'المشرف الأول');

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => 'approved',
            'reviewed_by' => $this->supervisor->id,
            'rejection_reason' => null,
        ]);
    }

    public function test_supervisor_cannot_reject_without_reason(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.payments.approvals.reject', $this->payment), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_supervisor_can_reject_payment_with_reason(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.payments.approvals.reject', $this->payment), [
                'rejection_reason' => 'رقم الإيصال غير مطابق للحساب البنكي',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم رفض السداد وتسجيل سبب الرفض بنجاح.')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'مرفوض')
            ->assertJsonPath('data.rejection_reason', 'رقم الإيصال غير مطابق للحساب البنكي')
            ->assertJsonPath('data.reviewer.name', 'المشرف الأول');

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => 'rejected',
            'rejection_reason' => 'رقم الإيصال غير مطابق للحساب البنكي',
            'reviewed_by' => $this->supervisor->id,
        ]);
    }

    public function test_supervisor_can_update_payment_details_from_modal(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->putJson(route('api.supervisor.payments.approvals.update', $this->payment), [
                'amount' => 600,
                'period' => '2026-11',
                'payment_method' => 'تحويل بنكي',
                'receipt_number' => '99999',
                'status' => 'approved',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 600)
            ->assertJsonPath('data.period', '2026-11')
            ->assertJsonPath('data.payment_method', 'تحويل بنكي')
            ->assertJsonPath('data.receipt_number', '99999')
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'amount' => 600,
            'period' => '2026-11',
            'receipt_number' => '99999',
            'status' => 'approved',
        ]);
    }

    public function test_supervisor_can_delete_payment(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->deleteJson(route('api.supervisor.payments.approvals.destroy', $this->payment));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم حذف السداد بنجاح.');

        $this->assertDatabaseMissing('payments', [
            'id' => $this->payment->id,
        ]);
    }

    public function test_supervisor_can_view_owner_payments_سدادات_الملاك(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson(route('api.supervisor.owner-payments.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.owner.name', 'المالك الأول')
            ->assertJsonPath('data.payments.0.tenant.property_unit', 'برج الامل')
            ->assertJsonPath('data.payments.0.tenant.name', 'محمد احمد')
            ->assertJsonPath('data.payments.0.collector.name', 'المحصل الأول')
            ->assertJsonPath('data.payments.0.amount', 500)
            ->assertJsonPath('data.payments.0.receipt_number', '12121')
            ->assertJsonPath('data.statistics.total_count', 1)
            ->assertJsonPath('data.statistics.total_amount', 500);
    }

    public function test_supervisor_can_update_owner_payment_comments(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->putJson(route('api.supervisor.owner-payments.notes.update', $this->payment), [
                'notes' => 'تم استلام الحوالة من قبل المالك مباشرة',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notes', 'تم استلام الحوالة من قبل المالك مباشرة');

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'notes' => 'تم استلام الحوالة من قبل المالك مباشرة',
        ]);
    }
}
