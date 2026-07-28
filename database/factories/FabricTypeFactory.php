<?php

namespace Database\Factories;

use App\Models\FabricType;
use App\Support\FabricProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FabricType>
 */
class FabricTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'fabric-'.fake()->unique()->numberBetween(1, 999999),
            'name_fa' => fake()->randomElement(['پنبه', 'کتان', 'ابریشم', 'جین', 'جرسی', 'کرپ']).' '.fake()->numberBetween(1, 99),
            'name_en' => fake()->word(),
            'category' => fake()->randomElement(array_keys(FabricType::CATEGORIES)),
            'family' => fake()->randomElement(array_keys(FabricType::FAMILIES)),
            'fiber_composition' => [['name' => 'پنبه', 'percent' => 100]],
            'profile' => FabricProfile::defaults(),
            'is_active' => true,
        ];
    }

    /** پارچه لخت و نازک مانند حریر. */
    public function fluid(): static
    {
        return $this->state(fn () => [
            'profile' => FabricProfile::make([
                'weight_gsm' => 60,
                'thickness_mm' => 0.15,
                'drape' => 0.92,
                'stiffness' => 0.08,
                'transparency' => 0.65,
                'slippage' => 0.9,
                'fraying' => 0.7,
            ])->toArray(),
        ]);
    }

    /** پارچه کشی مانند جرسی. */
    public function stretchy(): static
    {
        return $this->state(fn () => [
            'family' => 'knit',
            'profile' => FabricProfile::make([
                'weight_gsm' => 180,
                'thickness_mm' => 0.7,
                'drape' => 0.6,
                'stiffness' => 0.25,
                'stretch_weft' => 45,
                'stretch_warp' => 15,
                'recovery' => 92,
                'curling' => 0.7,
            ])->toArray(),
        ]);
    }

    /** پارچه سنگین و ایستا مانند جین. */
    public function structured(): static
    {
        return $this->state(fn () => [
            'profile' => FabricProfile::make([
                'weight_gsm' => 340,
                'thickness_mm' => 1.1,
                'drape' => 0.15,
                'stiffness' => 0.85,
                'transparency' => 0,
                'shrinkage' => 5,
            ])->toArray(),
        ]);
    }
}
