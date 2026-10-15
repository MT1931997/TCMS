<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Models\MaintenanceRequest;
use App\Models\TenantInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantMaintenancePortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_submit_maintenance_request_successfully(): void
    {
        $payload = [
            'tenant_name' => 'محمد احمد',
            'national_id' => '1211212121',
            'birth_date' => '1997-03-19',
            'phone' => '01143693590',
            'property_unit' => 'برج الأمل - وحدة 12',
            'title' => 'تسريب مياه في المطبخ',
            'description' => 'يوجد تسريب مياه مستمر في حوض المطبخ يحتاج صيانة فورية.',
            'latitude' => 24.71360000,
            'longitude' => 46.67530000,
            'address' => 'طريق الملك عبدالعزيز الفرعي، المرسلات، الرياض',
        ];

        $response = $this->postJson(route('api.tenant.maintenance-requests.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'تسريب مياه في المطبخ')
            ->assertJsonPath('data.tenant_name', 'محمد احمد')
            ->assertJsonPath('data.status', MaintenanceRequestSupervisorStatus::PendingReview->value)
            ->assertJsonPath('data.status_label', 'بانتظار المراجعة');

        $this->assertDatabaseHas('maintenance_requests', [
            'tenant_name' => 'محمد احمد',
            'national_id' => '1211212121',
            'title' => 'تسريب مياه في المطبخ',
            'supervisor_status' => MaintenanceRequestSupervisorStatus::PendingReview->value,
        ]);
    }

    public function test_tenant_can_submit_request_with_attachments(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('leak1.jpg');
        $file2 = UploadedFile::fake()->create('leak_video.mp4', 1000, 'video/mp4');

        $payload = [
            'tenant_name' => 'سالم الدوسري',
            'national_id' => '1020304050',
            'birth_date' => '1995-05-15',
            'phone' => '0555555555',
            'property_unit' => 'عمارة النخيل - شقة 4',
            'title' => 'عطل في التكييف',
            'description' => 'المكيف لا يبرد وتصدر منه أصوات مزعجة.',
            'attachments' => [$file1, $file2],
        ];

        $response = $this->postJson(route('api.tenant.maintenance-requests.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.attachments');

        $requestModel = MaintenanceRequest::where('national_id', '1020304050')->first();
        $this->assertNotNull($requestModel);
        $this->assertCount(2, $requestModel->attachments);
        Storage::disk('public')->assertExists($requestModel->attachments[0]['path']);
        Storage::disk('public')->assertExists($requestModel->attachments[1]['path']);
    }

    public function test_submitting_request_auto_links_to_registered_tenant_and_owner(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'المالك خالد']);
        $collector = User::factory()->collector()->create();
        $tenantUser = User::factory()->tenant()->create([
            'name' => 'فهد مسعود',
            'phone' => '0566666666',
        ]);

        $tenantInfo = TenantInfo::create([
            'user_id' => $tenantUser->id,
            'tenant_number' => 'T-9999',
            'contract_number' => 'CNT-2026-99',
            'property_unit' => 'برج التحلية - وحدة 8',
            'due_date' => '2026-10-25',
            'monthly_target' => 2000,
            'collector_id' => $collector->id,
            'owner_id' => $owner->id,
            'national_id' => '1088776655',
        ]);

        $payload = [
            'tenant_name' => 'فهد مسعود',
            'national_id' => '1088776655', // same national_id
            'birth_date' => '1990-01-01',
            'phone' => '0566666666',
            'property_unit' => 'برج التحلية - وحدة 8',
            'title' => 'صيانة كهرباء',
            'description' => 'قاطع الكهرباء الرئيسي يفصل باستمرار.',
        ];

        $response = $this->postJson(route('api.tenant.maintenance-requests.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_auto_linked', true)
            ->assertJsonPath('data.tenant.id', $tenantInfo->id)
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.owner.name', 'المالك خالد');

        $this->assertDatabaseHas('maintenance_requests', [
            'national_id' => '1088776655',
            'tenant_id' => $tenantInfo->id,
            'owner_id' => $owner->id,
        ]);
    }

    public function test_validation_fails_when_required_fields_missing(): void
    {
        $response = $this->postJson(route('api.tenant.maintenance-requests.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'tenant_name',
                'national_id',
                'birth_date',
                'phone',
                'property_unit',
                'title',
                'description',
            ]);
    }

    public function test_can_track_requests_by_national_id_or_phone(): void
    {
        MaintenanceRequest::factory()->create([
            'national_id' => '1122334455',
            'phone' => '0511112222',
            'title' => 'صيانة سباكة',
        ]);

        MaintenanceRequest::factory()->create([
            'national_id' => '1122334455',
            'phone' => '0511112222',
            'title' => 'صيانة أبواب',
        ]);

        MaintenanceRequest::factory()->create([
            'national_id' => '9988776655',
            'phone' => '0599998888',
            'title' => 'صيانة أخرى لشخص آخر',
        ]);

        // Track by national_id
        $response = $this->getJson(route('api.tenant.maintenance-requests.track', ['national_id' => '1122334455']));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        // Track by phone
        $responsePhone = $this->getJson(route('api.tenant.maintenance-requests.track', ['phone' => '0599998888']));
        $responsePhone->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'صيانة أخرى لشخص آخر');
    }

    public function test_can_view_single_maintenance_request_by_ticket_number(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'ticket_number' => 'MNT-202610-0999',
            'title' => 'تسريب مياه',
        ]);

        $response = $this->getJson(route('api.tenant.maintenance-requests.show', ['ticketNumber' => 'MNT-202610-0999']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket_number', 'MNT-202610-0999')
            ->assertJsonPath('data.title', 'تسريب مياه');
    }
}
