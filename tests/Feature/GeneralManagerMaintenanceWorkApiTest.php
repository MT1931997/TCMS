<?php

namespace Tests\Feature;

use App\Enums\MaintenanceWorkStatus;
use App\Enums\UserType;
use App\Models\MaintenanceWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneralManagerMaintenanceWorkApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $generalManager;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalManager = User::factory()->create([
            'type' => UserType::GeneralManager,
        ]);

        $this->owner = User::factory()->create([
            'type' => UserType::Owner,
        ]);
    }

    public function test_general_manager_can_list_maintenance_works_with_tabs_and_counters(): void
    {
        MaintenanceWork::factory()->count(2)->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        MaintenanceWork::factory()->count(1)->approved()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        MaintenanceWork::factory()->count(1)->rejected()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-works.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.works')
            ->assertJsonPath('data.tabs.all_count', 4)
            ->assertJsonPath('data.tabs.pending_count', 2)
            ->assertJsonPath('data.tabs.approved_count', 1)
            ->assertJsonPath('data.tabs.rejected_count', 1);
    }

    public function test_general_manager_can_filter_maintenance_works_by_tab_and_search(): void
    {
        MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'title' => 'إصلاح تسريب المكيف',
            'property_unit' => 'برج النور - 10',
        ]);

        MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
            'title' => 'صيانة كهرباء عامة',
            'property_unit' => 'برج الأمل - 4',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-works.index', [
                'tab' => 'pending',
                'search' => 'المكيف',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.works')
            ->assertJsonPath('data.works.0.title', 'إصلاح تسريب المكيف');
    }

    public function test_general_manager_can_create_maintenance_work_report_with_attachments(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('repair1.jpg');
        $video = UploadedFile::fake()->create('repair_video.mp4', 500, 'video/mp4');

        $payload = [
            'owner_id' => $this->owner->id,
            'property_unit' => 'برج الأمل - وحدة 12',
            'title' => 'إصلاح تسريب المكيف',
            'description' => 'تم فحص التكييف واستبدال العازل وصيانة مجرى الصرف.',
            'attachments' => [$image, $video],
        ];

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.maintenance-works.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'إصلاح تسريب المكيف')
            ->assertJsonPath('data.property_unit', 'برج الأمل - وحدة 12')
            ->assertJsonPath('data.status', MaintenanceWorkStatus::Pending->value)
            ->assertJsonPath('data.status_label', 'بانتظار قراركم')
            ->assertJsonPath('data.files_count', 2)
            ->assertJsonPath('data.owner.id', $this->owner->id)
            ->assertJsonPath('data.creator.id', $this->generalManager->id);

        $this->assertDatabaseHas('maintenance_works', [
            'owner_id' => $this->owner->id,
            'title' => 'إصلاح تسريب المكيف',
            'status' => MaintenanceWorkStatus::Pending->value,
            'created_by' => $this->generalManager->id,
        ]);
    }

    public function test_creation_validation_requires_owner_property_title_and_description(): void
    {
        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->postJson(route('api.general-manager.maintenance-works.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['owner_id', 'property_unit', 'title', 'description']);
    }

    public function test_general_manager_can_show_maintenance_work_details(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-works.show', $work));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $work->id)
            ->assertJsonPath('data.work_order_number', $work->work_order_number);
    }

    public function test_general_manager_can_update_maintenance_work_report(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
            'title' => 'عنوان قديم',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->putJson(route('api.general-manager.maintenance-works.update', $work), [
                'title' => 'عنوان محدث',
                'description' => 'وصف تفصيلي محدث',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'عنوان محدث')
            ->assertJsonPath('data.description', 'وصف تفصيلي محدث');

        $this->assertDatabaseHas('maintenance_works', [
            'id' => $work->id,
            'title' => 'عنوان محدث',
        ]);
    }

    public function test_general_manager_can_delete_maintenance_work_report(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->deleteJson(route('api.general-manager.maintenance-works.destroy', $work));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('maintenance_works', [
            'id' => $work->id,
        ]);
    }

    public function test_general_manager_can_export_approved_pdf_summary(): void
    {
        MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
            'title' => 'صيانة معتمدة أولى',
        ]);

        MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
            'title' => 'صيانة معتمدة ثانية',
        ]);

        $response = $this->actingAs($this->generalManager, 'sanctum')
            ->getJson(route('api.general-manager.maintenance-works.export-pdf'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_approved_works', 2);
    }
}
