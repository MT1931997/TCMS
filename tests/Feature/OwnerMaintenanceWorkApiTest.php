<?php

namespace Tests\Feature;

use App\Enums\MaintenanceWorkStatus;
use App\Enums\UserType;
use App\Models\MaintenanceWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerMaintenanceWorkApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected User $generalManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'type' => UserType::Owner,
        ]);

        $this->otherOwner = User::factory()->create([
            'type' => UserType::Owner,
        ]);

        $this->generalManager = User::factory()->create([
            'type' => UserType::GeneralManager,
        ]);
    }

    public function test_owner_can_list_maintenance_works_with_pending_and_history(): void
    {
        MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'title' => 'عمل بانتظار القرار',
        ]);

        MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
            'title' => 'عمل تم قبوله',
        ]);

        MaintenanceWork::factory()->rejected()->create([
            'owner_id' => $this->owner->id,
            'title' => 'عمل تم رفضه',
        ]);

        // A work belonging to someone else
        MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->otherOwner->id,
            'title' => 'عمل لمالك آخر',
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-works.index'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.pending_works')
            ->assertJsonCount(2, 'data.history_works')
            ->assertJsonPath('data.counters.pending_count', 1)
            ->assertJsonPath('data.counters.approved_count', 1)
            ->assertJsonPath('data.counters.rejected_count', 1)
            ->assertJsonPath('data.counters.history_count', 2)
            ->assertJsonPath('data.counters.all_count', 3);
    }

    public function test_owner_can_filter_by_pending_or_history_tab(): void
    {
        MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'title' => 'طلب بانتظار الموافقة',
        ]);

        MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
            'title' => 'طلب سابق معتمد',
        ]);

        $pendingResponse = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-works.index', ['tab' => 'pending']));

        $pendingResponse->assertOk()
            ->assertJsonCount(1, 'data.works')
            ->assertJsonPath('data.works.0.title', 'طلب بانتظار الموافقة');

        $historyResponse = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-works.index', ['tab' => 'history']));

        $historyResponse->assertOk()
            ->assertJsonCount(1, 'data.works')
            ->assertJsonPath('data.works.0.title', 'طلب سابق معتمد');
    }

    public function test_owner_can_approve_maintenance_work(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-works.approve', $work), [
                'notes' => 'تم الاطلاع على الصور والعمل ممتاز ومقبول.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', MaintenanceWorkStatus::Approved->value)
            ->assertJsonPath('data.status_label', 'مقبولة')
            ->assertJsonPath('data.owner_notes', 'تم الاطلاع على الصور والعمل ممتاز ومقبول.');

        $this->assertDatabaseHas('maintenance_works', [
            'id' => $work->id,
            'status' => MaintenanceWorkStatus::Approved->value,
            'owner_notes' => 'تم الاطلاع على الصور والعمل ممتاز ومقبول.',
        ]);
    }

    public function test_owner_can_reject_maintenance_work(): void
    {
        $work = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-works.reject', $work), [
                'rejection_reason' => 'الصيانة لم تعالج مشكلة الرطوبة في الجدار.',
                'notes' => 'يرجى إعادة إرسال الفني لمعالجة المشكلة.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', MaintenanceWorkStatus::Rejected->value)
            ->assertJsonPath('data.status_label', 'مرفوضة')
            ->assertJsonPath('data.owner_rejection_reason', 'الصيانة لم تعالج مشكلة الرطوبة في الجدار.');

        $this->assertDatabaseHas('maintenance_works', [
            'id' => $work->id,
            'status' => MaintenanceWorkStatus::Rejected->value,
            'owner_rejection_reason' => 'الصيانة لم تعالج مشكلة الرطوبة في الجدار.',
        ]);
    }

    public function test_owner_can_rate_maintenance_work_quality(): void
    {
        $work = MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-works.rate', $work), [
                'rating' => 5,
                'rating_comment' => 'خدمة ممتازة وفريق عمل سريع.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.rating_comment', 'خدمة ممتازة وفريق عمل سريع.')
            ->assertJsonPath('data.owner_evaluation', '5 نجوم - مقبولة');

        $this->assertDatabaseHas('maintenance_works', [
            'id' => $work->id,
            'rating' => 5,
            'rating_comment' => 'خدمة ممتازة وفريق عمل سريع.',
        ]);
    }

    public function test_rating_validation_requires_between_1_and_5(): void
    {
        $work = MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-works.rate', $work), [
                'rating' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_owner_cannot_modify_work_belonging_to_another_owner(): void
    {
        $otherWork = MaintenanceWork::factory()->pending()->create([
            'owner_id' => $this->otherOwner->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson(route('api.owner.maintenance-works.approve', $otherWork), [
                'notes' => 'محاولة اختراق',
            ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_download_pdf_report_data(): void
    {
        $work = MaintenanceWork::factory()->approved()->create([
            'owner_id' => $this->owner->id,
            'created_by' => $this->generalManager->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson(route('api.owner.maintenance-works.pdf', $work));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.work_order_number', $work->work_order_number)
            ->assertJsonPath('data.title', $work->title);
    }
}
