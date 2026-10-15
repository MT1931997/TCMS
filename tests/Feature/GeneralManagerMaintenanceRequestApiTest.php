<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralManagerMaintenanceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $generalManager;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalManager = User::factory()->generalManager()->create([
            'name' => 'المشرف العام',
            'phone' => '0500000001',
        ]);

        $this->owner = User::factory()->owner()->create([
            'name' => 'المالك سعود',
            'phone' => '0500000002',
        ]);
    }

    public function test_supervisor_can_list_maintenance_requests_with_tabs_and_counts(): void
    {
        // 2 pending, 1 approved, 1 rejected
        MaintenanceRequest::factory()->count(2)->pendingReview()->create();
        MaintenanceRequest::factory()->approved()->create();
        MaintenanceRequest::factory()->rejected()->create();

        // Default tab: pending
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-requests.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.requests')
            ->assertJsonPath('data.tabs.pending_count', 2)
            ->assertJsonPath('data.tabs.all_count', 4)
            ->assertJsonPath('data.tabs.approved_count', 1)
            ->assertJsonPath('data.tabs.rejected_count', 1)
            ->assertJsonPath('data.tabs.active_tab', 'pending');

        // Tab: all
        $responseAll = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-requests.index', ['tab' => 'all']));

        $responseAll->assertOk()
            ->assertJsonCount(4, 'data.requests');
    }

    public function test_supervisor_can_search_maintenance_requests(): void
    {
        MaintenanceRequest::factory()->create([
            'tenant_name' => 'محمد أحمد الصالح',
            'title' => 'عطل تكييف غير معتاد',
            'national_id' => '1010101010',
        ]);

        MaintenanceRequest::factory()->create([
            'tenant_name' => 'سعيد الغامدي',
            'title' => 'تسريب مياه بالحمام',
            'national_id' => '2020202020',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-requests.index', [
                'tab' => 'all',
                'search' => 'تكييف',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.title', 'عطل تكييف غير معتاد');
    }

    public function test_supervisor_can_view_single_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'title' => 'تسريب مياه',
            'description' => 'وصف المشكلة بالتفصيل',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-requests.show', $request));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.title', 'تسريب مياه');
    }

    public function test_supervisor_can_approve_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create([
            'property_unit' => 'تيست',
            'title' => 'تسريب',
        ]);

        $payload = [
            'owner_id' => $this->owner->id,
            'property_unit' => 'برج الأمل - وحدة 15', // Modified by supervisor
            'notes' => 'تم التحقق من الوحدة والمالك المسؤول، صيانة معتمدة.',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.maintenance-requests.approve', $request), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', MaintenanceRequestSupervisorStatus::Approved->value)
            ->assertJsonPath('data.status_label', 'معتمد')
            ->assertJsonPath('data.owner.id', $this->owner->id)
            ->assertJsonPath('data.property_unit', 'برج الأمل - وحدة 15')
            ->assertJsonPath('data.notes', 'تم التحقق من الوحدة والمالك المسؤول، صيانة معتمدة.')
            ->assertJsonPath('data.reviewer.id', $this->generalManager->id);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved->value,
            'owner_id' => $this->owner->id,
            'property_unit' => 'برج الأمل - وحدة 15',
            'reviewed_by' => $this->generalManager->id,
        ]);
    }

    public function test_cannot_approve_maintenance_request_without_owner_id(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.maintenance-requests.approve', $request), [
                'notes' => 'اعتماد بدون مالك',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['owner_id']);
    }

    public function test_supervisor_can_reject_maintenance_request_with_reason(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create([
            'title' => 'تسريب',
        ]);

        $payload = [
            'rejection_reason' => 'العقار المذكور لا يتبع إدارة أملاكنا، يرجى مراجعة إدارة البرج مباشرة.',
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.maintenance-requests.reject', $request), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', MaintenanceRequestSupervisorStatus::Rejected->value)
            ->assertJsonPath('data.status_label', 'مرفوض')
            ->assertJsonPath('data.rejection_reason', 'العقار المذكور لا يتبع إدارة أملاكنا، يرجى مراجعة إدارة البرج مباشرة.')
            ->assertJsonPath('data.reviewer.id', $this->generalManager->id);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Rejected->value,
            'rejection_reason' => 'العقار المذكور لا يتبع إدارة أملاكنا، يرجى مراجعة إدارة البرج مباشرة.',
            'reviewed_by' => $this->generalManager->id,
        ]);
    }

    public function test_cannot_reject_maintenance_request_without_reason(): void
    {
        $request = MaintenanceRequest::factory()->pendingReview()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.maintenance-requests.reject', $request), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_supervisor_can_update_maintenance_request_details(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'title' => 'عنوان قديم',
            'property_unit' => 'وحدة 1',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.maintenance-requests.update', $request), [
                'title' => 'عنوان محدث للصيانة',
                'property_unit' => 'وحدة 1 المحدثة',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'عنوان محدث للصيانة')
            ->assertJsonPath('data.property_unit', 'وحدة 1 المحدثة');
    }

    public function test_supervisor_can_delete_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->create();

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.maintenance-requests.destroy', $request));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('maintenance_requests', [
            'id' => $request->id,
        ]);
    }
}
