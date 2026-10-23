<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['برج الأمل', 'عمارة النور', 'مجمع النخيل', 'أبراج الصفوة', 'بناية الياسمين']),
            'owner_id' => User::factory()->owner(),
            'location' => fake()->randomElement(['الرياض - حي النرجس', 'الرياض - حي الملقا', 'جدة - حي الشاطئ', 'الدمام - حي الشاطئ']),
            'created_by' => User::factory()->generalManager(),
        ];
    }
}
