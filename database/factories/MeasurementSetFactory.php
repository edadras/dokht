<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Models\Workshop;
use App\Support\Measurements;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeasurementSet>
 */
class MeasurementSetFactory extends Factory
{
    public function definition(): array
    {
        $size = fake()->randomElement(Measurements::sizes());

        return [
            'workshop_id' => Workshop::factory(),
            'customer_id' => Customer::factory(),
            'title' => 'اندازه‌های '.fake()->monthName(),
            'unit' => 'cm',
            'base_size' => $size,
            'values' => Measurements::SIZE_TABLE[$size],
            'is_default' => true,
        ];
    }

    public function forSize(string $size): static
    {
        return $this->state(fn () => [
            'base_size' => $size,
            'values' => Measurements::SIZE_TABLE[$size] ?? Measurements::SIZE_TABLE['40'],
        ]);
    }
}
