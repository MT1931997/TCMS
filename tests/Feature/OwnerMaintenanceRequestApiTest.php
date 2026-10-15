<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerMaintenanceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $anotherOwner;

    private User $generalManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create([
            'name' => 'المالك الأول',
            'phone' => '0500000002',
        ]);

        $this->anotherOwner = User::factory()->owner()->create([
            'name' => 'المالك الثاني',
            'phone' => '0500000099',
        ]);

        $this->generalManager = User::factory()->generalManager()->create([
            'name' => 'المشرف العام',
        ]);
    }

    public function test_owner_can_list_maintenance_requests_with_pending_and_history(): void
    {
        // 1 pending for this owner
        MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'تسريب في الحمام',
            'property_unit' => 'تيست',
            'tenant_name' => 'محمد احمد',
            'reviewed_by' => $this->generalManager->id,
            'reviewed_at' => now(),
        ]);

        // 1 approved in history for this owner
        MaintenanceRequest::factory()->approvedByOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'عطل تكييف قديم',
            'property_unit' => 'برج الأمل',
        ]);

        // 1 rejected in history for this owner
        MaintenanceRequest::factory()->rejectedByOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'صيانة مفاتيح كهرباء',
            'property_unit' => 'برج الأمل',
        ]);

        // 1 pending for another owner (should NOT appear)
        MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->anotherOwner->id,
            'title' => 'طلب مالك آخر',
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-requests.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.pending_requests')
            ->assertJsonCount(2, 'data.history_requests')
            ->assertJsonPath('data.counters.pending_count', 1)
            ->assertJsonPath('data.counters.history_count', 2)
            ->assertJsonPath('data.counters.approved_count', 1)
            ->assertJsonPath('data.counters.rejected_count', 1)
            ->assertJsonPath('data.pending_requests.0.title', 'تسريب في الحمام')
            ->assertJsonPath('data.pending_requests.0.owner_status', MaintenanceRequestOwnerStatus::Pending->value)
            ->assertJsonPath('data.pending_requests.0.owner_status_label', 'بانتظار موافقتكم');
    }

    public function test_owner_can_filter_by_tab(): void
    {
        MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'طلب بانتظار الموافقة',
        ]);

        MaintenanceRequest::factory()->approvedByOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'طلب معتمد سابقاً',
        ]);

        // Tab pending
        $responsePending = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-requests.index', ['tab' => 'pending']));

        $responsePending->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.title', 'طلب بانتظار الموافقة')
            ->assertJsonPath('data.counters.pending_count', 1);

        // Tab history
        $responseHistory = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-requests.index', ['tab' => 'history']));

        $responseHistory->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.title', 'طلب معتمد سابقاً')
            ->assertJsonPath('data.counters.history_count', 1);
    }

    public function test_owner_can_view_single_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'تسريب مياه',
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-requests.show', $request));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.title', 'تسريب مياه');
    }

    public function test_owner_cannot_view_another_owners_request(): void
    {
        $request = MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->anotherOwner->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-requests.show', $request));

        $response->assertStatus(403);
    }

    public function test_owner_can_approve_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'تسريب مياه',
        ]);

        $payload = [
            'notes' => 'موافق على أعمال الصيانة، يرجى التنسيق مع المستأجر.',
        ];

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-requests.approve', $request), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.owner_status', MaintenanceRequestOwnerStatus::Approved->value)
            ->assertJsonPath('data.owner_status_label', 'مقبولة')
            ->assertJsonPath('data.owner_notes', 'موافق على أعمال الصيانة، يرجى التنسيق مع المستأجر.');

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'owner_status' => MaintenanceRequestOwnerStatus::Approved->value,
            'owner_notes' => 'موافق على أعمال الصيانة، يرجى التنسيق مع المستأجر.',
        ]);
    }

    public function test_owner_can_reject_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->owner->id,
            'title' => 'تسريب مياه',
        ]);

        $payload = [
            'rejection_reason' => 'هذه الصيانة تقع على عاتق المستأجر وفق العقد.',
        ];

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-requests.reject', $request), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.owner_status', MaintenanceRequestOwnerStatus::Rejected->value)
            ->assertJsonPath('data.owner_status_label', 'مرفوضة')
            ->assertJsonPath('data.owner_rejection_reason', 'هذه الصيانة تقع على عاتق المستأجر وفق العقد.');

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'owner_status' => MaintenanceRequestOwnerStatus::Rejected->value,
            'owner_rejection_reason' => 'هذه الصيانة تقع على عاتق المستأجر وفق العقد.',
        ]);
    }

    public function test_owner_cannot_approve_or_reject_another_owners_request(): void
    {
        $request = MaintenanceRequest::factory()->pendingOwner()->create([
            'owner_id' => $this->anotherOwner->id,
        ]);

        // Attempt approve
        $responseApprove = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-requests.approve', $request), []);

        $responseApprove->assertStatus(403);

        // Attempt reject
        $responseReject = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-requests.reject', $request), []);

        $responseReject->assertStatus(403);
    }
}
