<?php

namespace Database\Factories;

use App\Models\GarmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarmentType>
 */
class GarmentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'garment-'.fake()->unique()->numberBetween(1, 999999),
            'name_fa' => fake()->randomElement(['پیراهن', 'بلوز', 'دامن', 'شلوار', 'مانتو']),
            'category' => fake()->randomElement(array_keys(GarmentType::CATEGORIES)),
            'parts' => ['front_bodice', 'back_bodice', 'sleeve'],
            'default_ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
            'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length'],
            'is_active' => true,
        ];
    }
}
