<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('05########'),
            'type' => UserType::Tenant,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is a general manager.
     */
    public function generalManager(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::GeneralManager,
        ]);
    }

    /**
     * Indicate that the user is an owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Owner,
        ]);
    }

    /**
     * Indicate that the user is a supervisor.
     */
    public function supervisor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Supervisor,
        ]);
    }

    /**
     * Indicate that the user is a collector.
     */
    public function collector(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Collector,
        ]);
    }

    /**
     * Indicate that the user is a maintenance supervisor.
     */
    public function maintenanceSupervisor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::MaintenanceSupervisor,
        ]);
    }

    /**
     * Indicate that the user is a tenant.
     */
    public function tenant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Tenant,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
