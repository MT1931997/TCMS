<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'unit_number' => (string) fake()->unique()->numberBetween(1, 999),
            'floor' => (string) fake()->numberBetween(1, 10),
            'type' => fake()->randomElement(['apartment', 'office', 'shop']),
            'status' => UnitStatus::Vacant,
            'rent_price' => fake()->numberBetween(1000, 10000),
            'notes' => fake()->sentence(),
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => UnitStatus::Occupied]);
    }

    public function vacant(): static
    {
        return $this->state(fn () => ['status' => UnitStatus::Vacant]);
    }
}
