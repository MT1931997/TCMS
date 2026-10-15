<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceSupervisorMaintenanceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $maintenanceSupervisor;

    private User $generalManager;

    private User $owner;

    private User $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceSupervisor = User::factory()->maintenanceSupervisor()->create([
            'name' => 'مشرف الصيانة فهد',
            'phone' => '0500000010',
        ]);

        $this->generalManager = User::factory()->generalManager()->create([
            'name' => 'المشرف العام',
            'phone' => '0500000011',
        ]);

        $this->owner = User::factory()->owner()->create([
            'name' => 'المالك خالد',
            'phone' => '0500000012',
        ]);

        $this->collector = User::factory()->collector()->create([
            'name' => 'المحصل أحمد',
            'phone' => '0500000013',
        ]);
    }

    public function test_maintenance_supervisor_can_list_maintenance_requests_with_tabs_and_counts(): void
    {
        // 2 pending, 1 approved, 1 rejected
        MaintenanceRequest::factory()->count(2)->pendingReview()->create();
        MaintenanceRequest::factory()->approved()->create();
        MaintenanceRequest::factory()->rejected()->create();

        // Default tab: pending
        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-requests.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.requests')
            ->assertJsonPath('data.tabs.pending_count', 2)
            ->assertJsonPath('data.tabs.all_count', 4)
            ->assertJsonPath('data.tabs.approved_count', 1)
            ->assertJsonPath('data.tabs.rejected_count', 1)
            ->assertJsonPath('data.tabs.active_tab', 'pending');

        // Tab: all
        $responseAll = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-requests.index', ['tab' => 'all']));

        $responseAll->assertOk()
            ->assertJsonCount(4, 'data.requests');
    }

    public function test_maintenance_supervisor_can_filter_by_status_and_search(): void
    {
        $target = MaintenanceRequest::factory()->pendingReview()->create([
            'ticket_number' => 'MNT-202610-9999',
            'title' => 'عطل تكييف في الصالة',
            'property_unit' => 'فيلا الياسمين - وحدة 4',
        ]);

        MaintenanceRequest::factory()->pendingReview()->create([
            'ticket_number' => 'MNT-202610-1111',
            'title' => 'انسداد مجاري في الحمام',
            'property_unit' => 'برج النور - شقة 10',
        ]);

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-requests.index', [
                'tab' => 'all',
                'search' => 'عطل تكييف',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.ticket_number', 'MNT-202610-9999')
            ->assertJsonPath('data.requests.0.title', 'عطل تكييف في الصالة');
    }

    public function test_maintenance_supervisor_can_view_single_maintenance_request_details(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create([
            'title' => 'تسريب مياه بالحمام',
        ]);

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-requests.show', $request));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.title', 'تسريب مياه بالحمام');
    }

    public function test_maintenance_supervisor_can_approve_maintenance_request_and_forward_to_owner(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create([
            'property_unit' => 'برج النرجس - شقة 102',
            'notes' => 'ملاحظة أولية',
        ]);

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->postJson(route('api.maintenance-supervisor.maintenance-requests.approve', $request), [
                'owner_id' => $this->owner->id,
                'property_unit' => 'برج النرجس - شقة 102 (معدلة)',
                'notes' => 'تم التحقق من الضرر وتوجيه الطلب للمالك للاعتماد',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.supervisor_status', MaintenanceRequestSupervisorStatus::Approved->value)
            ->assertJsonPath('data.owner_status', MaintenanceRequestOwnerStatus::Pending->value)
            ->assertJsonPath('data.owner.id', $this->owner->id)
            ->assertJsonPath('data.property_unit', 'برج النرجس - شقة 102 (معدلة)')
            ->assertJsonPath('data.reviewer.id', $this->maintenanceSupervisor->id);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved->value,
            'owner_status' => MaintenanceRequestOwnerStatus::Pending->value,
            'owner_id' => $this->owner->id,
            'property_unit' => 'برج النرجس - شقة 102 (معدلة)',
            'reviewed_by' => $this->maintenanceSupervisor->id,
            'notes' => 'تم التحقق من الضرر وتوجيه الطلب للمالك للاعتماد',
        ]);
    }

    public function test_maintenance_supervisor_approve_validation_requires_owner(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create();

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->postJson(route('api.maintenance-supervisor.maintenance-requests.approve', $request), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['owner_id']);
    }

    public function test_maintenance_supervisor_can_reject_maintenance_request_with_reason(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create();

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->postJson(route('api.maintenance-supervisor.maintenance-requests.reject', $request), [
                'rejection_reason' => 'الصيانة المطلوبة غير مشمولة بضمان المالك وتخص المستأجر مباشرة.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.supervisor_status', MaintenanceRequestSupervisorStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'الصيانة المطلوبة غير مشمولة بضمان المالك وتخص المستأجر مباشرة.')
            ->assertJsonPath('data.reviewer.id', $this->maintenanceSupervisor->id);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Rejected->value,
            'rejection_reason' => 'الصيانة المطلوبة غير مشمولة بضمان المالك وتخص المستأجر مباشرة.',
            'reviewed_by' => $this->maintenanceSupervisor->id,
        ]);
    }

    public function test_maintenance_supervisor_reject_validation_requires_reason(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create();

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->postJson(route('api.maintenance-supervisor.maintenance-requests.reject', $request), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_maintenance_supervisor_can_update_maintenance_request_details(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'title' => 'عنوان قديم',
            'property_unit' => 'وحدة 1',
        ]);

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->putJson(route('api.maintenance-supervisor.maintenance-requests.update', $request), [
                'title' => 'عنوان محدث للصيانة',
                'property_unit' => 'وحدة 1 المحدثة',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'عنوان محدث للصيانة')
            ->assertJsonPath('data.property_unit', 'وحدة 1 المحدثة');
    }

    public function test_maintenance_supervisor_can_delete_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->create();

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->deleteJson(route('api.maintenance-supervisor.maintenance-requests.destroy', $request));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('maintenance_requests', [
            'id' => $request->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_maintenance_supervisor_module(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create();

        // Collector is not allowed
        $response = $this->actingAs($this->collector, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-requests.index'));

        $response->assertStatus(403);

        // Collector cannot approve
        $approveResponse = $this->actingAs($this->collector, 'sanctum')
            ->postJson(route('api.maintenance-supervisor.maintenance-requests.approve', $request), [
                'owner_id' => $this->owner->id,
            ]);

        $approveResponse->assertStatus(403);
    }

    public function test_general_manager_can_also_access_maintenance_supervisor_endpoints(): void
    {
        MaintenanceRequest::factory()->pendingReview()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-requests.index'));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
