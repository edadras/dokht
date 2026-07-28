<?php

namespace Database\Factories;

use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guide>
 */
class GuideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scope' => 'general',
            'fabric_type_id' => null,
            'garment_type_id' => null,
            'title' => 'راهنمای '.fake()->randomElement(['برش', 'دوخت', 'اتو', 'اندازه‌گیری']).' '.fake()->numberBetween(1, 99),
            'summary' => 'خلاصه کوتاه راهنما برای آزمون.',
            'body' => "بند اول راهنما.\n\nبند دوم راهنما با توضیح بیشتر.",
            'tips' => ['نکته اول', 'نکته دوم'],
            'sort' => 0,
        ];
    }

    /** راهنمای مربوط به یک نوع پارچه. */
    public function forFabricType(?FabricType $type = null): static
    {
        return $this->state(fn () => [
            'scope' => 'fabric_type',
            'fabric_type_id' => $type?->id ?? FabricType::factory(),
        ]);
    }

    /** راهنمای مربوط به یک نوع لباس. */
    public function forGarmentType(?GarmentType $type = null): static
    {
        return $this->state(fn () => [
            'scope' => 'garment_type',
            'garment_type_id' => $type?->id ?? GarmentType::factory(),
        ]);
    }
}
