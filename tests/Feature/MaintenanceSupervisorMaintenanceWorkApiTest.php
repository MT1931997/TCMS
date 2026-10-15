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

class MaintenanceSupervisorMaintenanceWorkApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $maintenanceSupervisor;

    protected User $owner;

    protected User $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceSupervisor = User::factory()->create([
            'type' => UserType::MaintenanceSupervisor,
        ]);

        $this->owner = User::factory()->create([
            'type' => UserType::Owner,
        ]);

        $this->collector = User::factory()->create([
            'type' => UserType::Collector,
        ]);
    }

    public function test_maintenance_supervisor_can_list_maintenance_works(): void
    {
        MaintenanceWork::factory()->count(3)->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->maintenanceSupervisor->id,
        ]);

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-works.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.works')
            ->assertJsonPath('data.tabs.all_count', 3);
    }

    public function test_maintenance_supervisor_can_create_work_report_with_attachments(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('plumbing.jpg');

        $payload = [
            'owner_id' => $this->owner->id,
            'property_unit' => 'برج النرجس - شقة 102',
            'title' => 'تغيير خلاطات المطبخ',
            'description' => 'تم تركيب طقم خلاطات جديد مع فحص شبكة التغذية.',
            'attachments' => [$image],
        ];

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->postJson(route('api.maintenance-supervisor.maintenance-works.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'تغيير خلاطات المطبخ')
            ->assertJsonPath('data.status', MaintenanceWorkStatus::Pending->value)
            ->assertJsonPath('data.files_count', 1)
            ->assertJsonPath('data.creator.id', $this->maintenanceSupervisor->id);

        $this->assertDatabaseHas('maintenance_works', [
            'owner_id' => $this->owner->id,
            'title' => 'تغيير خلاطات المطبخ',
            'created_by' => $this->maintenanceSupervisor->id,
        ]);
    }

    public function test_maintenance_supervisor_can_show_and_update_work_report(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->maintenanceSupervisor->id,
        ]);

        $showResponse = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-works.show', $work));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $work->id);

        $updateResponse = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->putJson(route('api.maintenance-supervisor.maintenance-works.update', $work), [
                'title' => 'عنوان محدث بواسطة مشرف الصيانة',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.title', 'عنوان محدث بواسطة مشرف الصيانة');
    }

    public function test_maintenance_supervisor_can_delete_work_report(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->maintenanceSupervisor->id,
        ]);

        $response = $this->actingAs($this->maintenanceSupervisor, 'sanctum')
            ->deleteJson(route('api.maintenance-supervisor.maintenance-works.destroy', $work));

        $response->assertOk();

        $this->assertDatabaseMissing('maintenance_works', [
            'id' => $work->id,
        ]);
    }

    public function test_non_supervisor_cannot_access_maintenance_supervisor_works(): void
    {
        $response = $this->actingAs($this->collector, 'sanctum')
            ->getJson(route('api.maintenance-supervisor.maintenance-works.index'));

        $response->assertStatus(403);
    }
}
