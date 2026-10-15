<?php

namespace Database\Factories;

use App\Enums\MaintenanceWorkStatus;
use App\Models\MaintenanceWork;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceWork>
 */
class MaintenanceWorkFactory extends Factory
{
    protected $model = MaintenanceWork::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_number' => 'WRK-'.now()->format('Ym').'-'.fake()->unique()->numberBetween(1000, 999999),
            'owner_id' => User::factory()->owner(),
            'created_by' => User::factory()->generalManager(),
            'property_unit' => 'برج الأمل - وحدة 12',
            'title' => 'إصلاح تسريب المكيف في الصالة',
            'description' => 'تم استبدال ماسورة الصرف وتنظيف الفلاتر واختبار التبريد بنجاح.',
            'attachments' => [],
            'status' => MaintenanceWorkStatus::Pending->value,
            'owner_decision_at' => null,
            'owner_notes' => null,
            'owner_rejection_reason' => null,
            'rating' => null,
            'rating_comment' => null,
            'rated_at' => null,
        ];
    }

    /**
     * Indicate that the maintenance work is pending owner review ("بانتظار قراركم").
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceWorkStatus::Pending->value,
            'owner_decision_at' => null,
        ]);
    }

    /**
     * Indicate that the owner approved the maintenance work ("مقبولة").
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceWorkStatus::Approved->value,
            'owner_decision_at' => now(),
            'owner_notes' => 'تمت معاينة الصيانة والموافقة على العمل المنفذ.',
            'owner_rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the owner rejected the maintenance work ("مرفوضة").
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceWorkStatus::Rejected->value,
            'owner_decision_at' => now(),
            'owner_rejection_reason' => 'التبريد ما زال ضعيفاً ويوجد صوت عالي في الوحدة الخارجية.',
            'owner_notes' => 'يرجى إعادة المعاينة من قبل الفني.',
        ]);
    }

    /**
     * Indicate that the owner rated the maintenance work.
     */
    public function rated(int $rating = 5, string $comment = 'عمل ممتاز وفريق محترف.'): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
            'rating_comment' => $comment,
            'rated_at' => now(),
        ]);
    }
}
