<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\GarmentType;
use App\Models\Project;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'customer_id' => Customer::factory(),
            'garment_type_id' => GarmentType::factory(),
            'name' => fake()->randomElement(['پیراهن تابستانی', 'مانتو اداری', 'دامن مجلسی', 'شلوار راسته']),
            'size' => '40',
            'status' => 'draft',
            'step' => 1,
        ];
    }
}
