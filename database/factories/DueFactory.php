<?php

namespace Database\Factories;

use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Models\TenantInfo;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    protected $model = Due::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 5000);

        return [
            'contract_id' => Contract::factory(),
            'tenant_id' => TenantInfo::factory(),
            'unit_id' => Unit::factory(),
            'due_date' => fake()->date(),
            'amount' => $amount,
            'paid_amount' => 0,
            'period' => fake()->date('Y-m'),
            'status' => DueStatus::Pending,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'paid_amount' => $attrs['amount'],
            'status' => DueStatus::Paid,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subMonth(),
            'status' => DueStatus::Overdue,
        ]);
    }
}
