<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollectorTenantApiTest extends TestCase
{
    use RefreshDatabase;

    private User $collectorA;

    private User $collectorB;

    private TenantInfo $tenantA;

    private TenantInfo $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collectorA = User::factory()->collector()->create(['name' => 'المحصل الأول']);
        $this->collectorB = User::factory()->collector()->create(['name' => 'المحصل الثاني']);

        $userA = User::factory()->tenant()->create([
            'name' => 'محمد احمد',
            'phone' => '01143693590',
        ]);

        $this->tenantA = TenantInfo::create([
            'user_id' => $userA->id,
            'tenant_number' => '1212',
            'property_unit' => 'برج الامل',
            'due_date' => '2026-10-30',
            'monthly_target' => 1000,
            'collector_id' => $this->collectorA->id,
        ]);

        $userB = User::factory()->tenant()->create([
            'name' => 'سالم الدوسري',
            'phone' => '0555555555',
        ]);

        $this->tenantB = TenantInfo::create([
            'user_id' => $userB->id,
            'tenant_number' => '1313',
            'property_unit' => 'برج النخيل',
            'due_date' => '2026-10-25',
            'monthly_target' => 1500,
            'collector_id' => $this->collectorB->id,
        ]);
    }

    public function test_collector_only_sees_their_assigned_tenants_in_index(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->getJson(route('api.collector.tenants.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tenants')
            ->assertJsonPath('data.tenants.0.tenant_number', '1212')
            ->assertJsonPath('data.tenants.0.name', 'محمد احمد');
    }

    public function test_collector_can_search_their_tenants(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->getJson(route('api.collector.tenants.index', ['search' => '1212']));

        $response->assertOk()
            ->assertJsonCount(1, 'data.tenants')
            ->assertJsonPath('data.tenants.0.tenant_number', '1212');

        $notFoundResponse = $this->actingAs($this->collectorA, 'sanctum')
            ->getJson(route('api.collector.tenants.index', ['search' => 'سالم']));

        $notFoundResponse->assertOk()
            ->assertJsonCount(0, 'data.tenants');
    }

    public function test_collector_can_view_their_tenant_details(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->getJson(route('api.collector.tenants.show', $this->tenantA));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_info.tenant_number', '1212')
            ->assertJsonPath('data.tenant_info.name', 'محمد احمد')
            ->assertJsonPath('data.current_month_target.target_amount', 1000);
    }

    public function test_collector_cannot_view_another_collectors_tenant(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->getJson(route('api.collector.tenants.show', $this->tenantB));

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'ليس لديك صلاحية للوصول لبيانات هذا المستأجر.');
    }

    public function test_collector_can_add_promise_to_their_tenant(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->postJson(route('api.collector.tenants.promises.store', $this->tenantA), [
                'promise_date' => '2026-10-10',
                'note' => 'خلال يومين',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.note', 'خلال يومين')
            ->assertJsonPath('data.created_by.name', 'المحصل الأول');

        $this->assertDatabaseHas('tenant_promises', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->collectorA->id,
            'note' => 'خلال يومين',
        ]);
    }

    public function test_collector_can_add_note_to_their_tenant(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->postJson(route('api.collector.tenants.notes.store', $this->tenantA), [
                'content' => 'كويس',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content', 'كويس')
            ->assertJsonPath('data.author_name', 'المحصل الأول');

        $this->assertDatabaseHas('tenant_notes', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->collectorA->id,
            'content' => 'كويس',
        ]);
    }

    public function test_collector_can_record_payment_for_their_tenant(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->postJson(route('api.collector.tenants.payments.store', $this->tenantA), [
                'amount' => 500,
                'payment_date' => '2026-10-10',
                'payment_method' => 'كاش بالمكتب',
                'receipt_number' => '12121',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $this->tenantA->id,
            'collector_id' => $this->collectorA->id,
            'amount' => 500,
            'receipt_number' => '12121',
            'status' => 'pending',
        ]);
    }

    public function test_collector_can_view_my_payments_list_سداداتي(): void
    {
        // Create payment for collector A
        Payment::create([
            'tenant_id' => $this->tenantA->id,
            'collector_id' => $this->collectorA->id,
            'amount' => 500,
            'payment_date' => '2026-10-10',
            'payment_method' => 'كاش بالمكتب',
            'receipt_number' => '12121',
            'period' => '2026-10',
            'status' => 'pending',
        ]);

        // Create payment for collector B
        Payment::create([
            'tenant_id' => $this->tenantB->id,
            'collector_id' => $this->collectorB->id,
            'amount' => 750,
            'payment_date' => '2026-10-10',
            'payment_method' => 'تحويل بنكي',
            'receipt_number' => '99999',
            'period' => '2026-10',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->getJson(route('api.collector.payments.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.receipt_number', '12121')
            ->assertJsonPath('data.payments.0.status_label', 'قيد المراجعة')
            ->assertJsonPath('data.payments.0.tenant.name', 'محمد احمد')
            ->assertJsonPath('data.statistics.total_count', 1)
            ->assertJsonPath('data.statistics.total_amount', 500);
    }

    public function test_collector_can_upload_new_payment_directly_رفع_سداد_جديد_cash(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->postJson(route('api.collector.payments.store-direct'), [
                'tenant_id' => $this->tenantA->id,
                'amount' => 500,
                'period' => '2026-10',
                'payment_method' => 'كاش بالمكتب',
                'receipt_number' => '12121',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.period', '2026-10')
            ->assertJsonPath('data.status_label', 'قيد المراجعة');

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $this->tenantA->id,
            'collector_id' => $this->collectorA->id,
            'amount' => 500,
            'receipt_number' => '12121',
            'status' => 'pending',
        ]);
    }

    public function test_collector_upload_payment_cash_requires_receipt_number(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->postJson(route('api.collector.payments.store-direct'), [
                'tenant_id' => $this->tenantA->id,
                'amount' => 500,
                'period' => '2026-10',
                'payment_method' => 'كاش بالمكتب',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['receipt_number']);
    }

    public function test_collector_can_upload_payment_with_receipt_attachment(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->post(route('api.collector.payments.store-direct'), [
                'tenant_id' => $this->tenantA->id,
                'amount' => 600,
                'period' => '2026-10',
                'payment_method' => 'تحويل بنكي',
                'receipt_attachment' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'تحويل بنكي');

        $payment = Payment::where('amount', 600)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->attachment_path);
        Storage::disk('public')->assertExists($payment->attachment_path);
    }

    public function test_collector_cannot_upload_payment_for_unassigned_tenant(): void
    {
        $response = $this->actingAs($this->collectorA, 'sanctum')
            ->postJson(route('api.collector.payments.store-direct'), [
                'tenant_id' => $this->tenantB->id,
                'amount' => 500,
                'period' => '2026-10',
                'payment_method' => 'كاش بالمكتب',
                'receipt_number' => '12121',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id']);
    }
}
