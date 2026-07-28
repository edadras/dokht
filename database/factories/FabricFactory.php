<?php

namespace Database\Factories;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fabric>
 */
class FabricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'fabric_type_id' => FabricType::factory(),
            'name' => 'پارچه '.fake()->colorName(),
            'color' => fake()->randomElement(['سرمه‌ای', 'مشکی', 'کرم', 'زیتونی', 'صورتی', 'سفید']),
            'color_hex' => fake()->hexColor(),
            'surface_pattern' => 'plain',
            'width_cm' => fake()->randomElement([90, 110, 140, 150]),
            'gsm' => fake()->numberBetween(60, 350),
            'price_per_meter' => fake()->numberBetween(150, 3000) * 1000,
            'stock_meters' => fake()->randomFloat(1, 0, 40),
            'is_active' => true,
        ];
    }

    public function striped(): static
    {
        return $this->state(fn () => [
            'surface_pattern' => 'stripe',
            'pattern_repeat_cm' => 4.0,
        ]);
    }

    public function plaid(): static
    {
        return $this->state(fn () => [
            'surface_pattern' => 'plaid',
            'pattern_repeat_cm' => 8.0,
        ]);
    }
}
