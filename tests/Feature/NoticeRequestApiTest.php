<?php

namespace Tests\Feature;

use App\Enums\NoticeRequestStatus;
use App\Models\NoticeRequest;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoticeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $collector;

    private User $otherCollector;

    private User $supervisor;

    private TenantInfo $tenant;

    private TenantInfo $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->supervisor = User::factory()->supervisor()->create(['name' => 'المشرف العام']);
        $this->collector = User::factory()->collector()->create(['name' => 'المحصل الأول']);
        $this->otherCollector = User::factory()->collector()->create(['name' => 'المحصل الثاني']);

        $tenantUser = User::factory()->tenant()->create([
            'name' => 'محمد عبدالله',
            'phone' => '0500000001',
        ]);

        $this->tenant = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => '121212',
            'contract_number' => '5454',
            'property_unit' => 'برج الأمل - وحدة 12',
            'due_date' => '2026-10-30',
            'monthly_target' => 2000,
            'collector_id' => $this->collector->id,
            'supervisor_id' => $this->supervisor->id,
        ]);

        $otherTenantUser = User::factory()->tenant()->create([
            'name' => 'سالم الدوسري',
            'phone' => '0500000002',
        ]);

        $this->otherTenant = TenantInfo::create([
            'user_id' => $otherTenantUser->id,
            'tenant_number' => '131313',
            'contract_number' => '5555',
            'property_unit' => 'برج النخيل - وحدة 5',
            'due_date' => '2026-10-25',
            'monthly_target' => 3000,
            'collector_id' => $this->otherCollector->id,
            'supervisor_id' => $this->supervisor->id,
        ]);
    }

    public function test_collector_can_create_a_notice_request(): void
    {
        $response = $this->actingAs($this->collector, 'sanctum')
            ->postJson(route('api.collector.notice-requests.store'), [
                'tenant_id' => $this->tenant->id,
                'reason' => 'تأخر السداد لمدة شهرين، الرجاء إصدار إشعار تنبيه للمستأجر.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.name', 'محمد عبدالله')
            ->assertJsonPath('data.tenant.contract_number', '5454')
            ->assertJsonPath('data.status', 'pending_issuance')
            ->assertJsonPath('data.status_label', 'بانتظار الإصدار')
            ->assertJsonPath('data.details', 'بانتظار قيام المشرف بإصدار نص الإشعار');

        $this->assertDatabaseHas('notice_requests', [
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'status' => 'pending_issuance',
            'reason' => 'تأخر السداد لمدة شهرين، الرجاء إصدار إشعار تنبيه للمستأجر.',
        ]);
    }

    public function test_collector_cannot_create_notice_for_tenant_of_another_collector(): void
    {
        $response = $this->actingAs($this->collector, 'sanctum')
            ->postJson(route('api.collector.notice-requests.store'), [
                'tenant_id' => $this->otherTenant->id,
                'reason' => 'محاولة طلب لمستأجر محصل آخر',
            ]);

        $response->assertForbidden();
    }

    public function test_collector_can_list_their_notice_requests_with_statistics(): void
    {
        NoticeRequest::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'supervisor_id' => $this->supervisor->id,
            'reason' => 'تأخر السداد',
            'status' => NoticeRequestStatus::PendingIssuance,
        ]);

        $response = $this->actingAs($this->collector, 'sanctum')
            ->getJson(route('api.collector.notice-requests.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statistics.total_count', 1)
            ->assertJsonPath('data.statistics.pending_issuance_count', 1)
            ->assertJsonCount(1, 'data.requests');
    }

    public function test_supervisor_can_issue_notice_content(): void
    {
        $noticeRequest = NoticeRequest::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'reason' => 'تأخر السداد',
            'status' => NoticeRequestStatus::PendingIssuance,
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.notice-requests.issue', $noticeRequest), [
                'notice_content' => 'نص الإشعار الرسمي: يرجى السداد خلال 48 ساعة تفادياً للإجراءات النظامية.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.status_label', 'صادر - بانتظار التسليم')
            ->assertJsonPath('data.notice_content', 'نص الإشعار الرسمي: يرجى السداد خلال 48 ساعة تفادياً للإجراءات النظامية.');

        $this->assertDatabaseHas('notice_requests', [
            'id' => $noticeRequest->id,
            'status' => 'issued',
            'notice_content' => 'نص الإشعار الرسمي: يرجى السداد خلال 48 ساعة تفادياً للإجراءات النظامية.',
        ]);
    }

    public function test_collector_can_submit_delivery_proof_image(): void
    {
        $noticeRequest = NoticeRequest::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'supervisor_id' => $this->supervisor->id,
            'reason' => 'تأخر السداد',
            'notice_content' => 'نص الإشعار الرسمي',
            'issued_at' => now(),
            'status' => NoticeRequestStatus::Issued,
        ]);

        $proofFile = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($this->collector, 'sanctum')
            ->post(route('api.collector.notice-requests.delivery-proof', $noticeRequest), [
                'delivery_proof' => $proofFile,
                'delivery_date' => '2026-10-11',
                'delivery_notes' => 'تم تسليم الإشعار يدوياً للمستأجر في مقر سكنه',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.status_label', 'بانتظار اعتماد التسليم')
            ->assertJsonPath('data.delivery_notes', 'تم تسليم الإشعار يدوياً للمستأجر في مقر سكنه');

        $this->assertDatabaseHas('notice_requests', [
            'id' => $noticeRequest->id,
            'status' => 'pending_approval',
            'delivery_notes' => 'تم تسليم الإشعار يدوياً للمستأجر في مقر سكنه',
        ]);
    }

    public function test_supervisor_can_approve_delivery_proof(): void
    {
        $noticeRequest = NoticeRequest::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'supervisor_id' => $this->supervisor->id,
            'reason' => 'تأخر السداد',
            'status' => NoticeRequestStatus::PendingApproval,
            'delivery_proof_path' => 'notice_delivery_proofs/sample.jpg',
            'delivery_date' => '2026-10-11',
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.notice-requests.approve-delivery', $noticeRequest));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'معتمد');

        $this->assertDatabaseHas('notice_requests', [
            'id' => $noticeRequest->id,
            'status' => 'approved',
        ]);
    }

    public function test_supervisor_can_reject_notice_request_with_reason(): void
    {
        $noticeRequest = NoticeRequest::create([
            'tenant_id' => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'reason' => 'طلب غير مبرر',
            'status' => NoticeRequestStatus::PendingIssuance,
        ]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.supervisor.notice-requests.reject', $noticeRequest), [
                'rejection_reason' => 'المستأجر لديه وعد سداد قائم ينتهي غداً، يرجى الانتظار.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'مرفوض')
            ->assertJsonPath('data.rejection_reason', 'المستأجر لديه وعد سداد قائم ينتهي غداً، يرجى الانتظار.');

        $this->assertDatabaseHas('notice_requests', [
            'id' => $noticeRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'المستأجر لديه وعد سداد قائم ينتهي غداً، يرجى الانتظار.',
        ]);
    }
}
