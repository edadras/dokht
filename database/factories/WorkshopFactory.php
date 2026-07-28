<?php

namespace Database\Factories;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workshop>
 */
class WorkshopFactory extends Factory
{
    public function definition(): array
    {
        $name = 'خیاطی '.fake()->firstName();

        return [
            'name' => $name,
            'slug' => Workshop::uniqueSlug($name.'-'.fake()->unique()->numberBetween(1, 100000)),
            'phone' => '0912'.fake()->numerify('#######'),
            'city' => fake()->randomElement(['تهران', 'مشهد', 'اصفهان', 'شیراز', 'تبریز']),
            'currency' => 'IRT',
            'default_seam_allowance' => 1.0,
            'default_hem_allowance' => 3.0,
        ];
    }
}
