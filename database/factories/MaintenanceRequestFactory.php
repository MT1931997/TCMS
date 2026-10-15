<?php

namespace Database\Factories;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => 'MNT-'.now()->format('Ym').'-'.fake()->unique()->numberBetween(1000, 999999),
            'tenant_name' => fake()->name(),
            'national_id' => (string) fake()->numberBetween(1000000000, 2999999999),
            'birth_date' => fake()->date('Y-m-d', '-20 years'),
            'phone' => '05'.fake()->numberBetween(10000000, 99999999),
            'property_unit' => 'برج الأمل - وحدة '.fake()->numberBetween(1, 50),
            'title' => 'تسريب مياه في المطبخ',
            'description' => 'يوجد تسريب مياه مستمر في حوض المطبخ يحتاج صيانة فورية.',
            'latitude' => 24.71360000,
            'longitude' => 46.67530000,
            'address' => 'طريق الملك عبدالعزيز الفرعي، المرسلات، الرياض',
            'attachments' => null,
            'supervisor_status' => MaintenanceRequestSupervisorStatus::PendingReview,
            'owner_status' => null,
            'tenant_id' => null,
            'owner_id' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => null,
            'rejection_reason' => null,
            'owner_decision_at' => null,
            'owner_notes' => null,
            'owner_rejection_reason' => null,
        ];
    }

    /**
     * Indicate that the request is pending supervisor review.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_status' => MaintenanceRequestSupervisorStatus::PendingReview,
            'owner_status' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the request is approved by supervisor and forwarded to owner.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved,
            'owner_status' => MaintenanceRequestOwnerStatus::Pending,
            'owner_id' => User::factory()->owner(),
            'reviewed_by' => User::factory()->generalManager(),
            'reviewed_at' => now(),
            'notes' => 'تم اعتماد الطلب وتعيين المالك المسؤول.',
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the request is pending owner decision.
     */
    public function pendingOwner(): static
    {
        return $this->approved();
    }

    /**
     * Indicate that the owner approved the request.
     */
    public function approvedByOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved,
            'owner_status' => MaintenanceRequestOwnerStatus::Approved,
            'owner_id' => User::factory()->owner(),
            'reviewed_by' => User::factory()->generalManager(),
            'reviewed_at' => now()->subDay(),
            'owner_decision_at' => now(),
            'owner_notes' => 'تمت الموافقة على إجراء الصيانة وتكليف الفني.',
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the owner rejected the request.
     */
    public function rejectedByOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved,
            'owner_status' => MaintenanceRequestOwnerStatus::Rejected,
            'owner_id' => User::factory()->owner(),
            'reviewed_by' => User::factory()->generalManager(),
            'reviewed_at' => now()->subDay(),
            'owner_decision_at' => now(),
            'owner_rejection_reason' => 'الصيانة تقع على عاتق المستأجر وفق بنود العقد.',
            'owner_notes' => 'الصيانة تقع على عاتق المستأجر وفق بنود العقد.',
        ]);
    }

    /**
     * Indicate that the request is rejected by supervisor.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Rejected,
            'owner_status' => null,
            'reviewed_by' => User::factory()->generalManager(),
            'reviewed_at' => now(),
            'rejection_reason' => 'العقار أو الوحدة غير تابعة لمؤسستنا، الرجاء التحقق من البيانات.',
        ]);
    }
}
