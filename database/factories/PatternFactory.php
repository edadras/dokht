<?php

namespace Database\Factories;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\Workshop;
use App\Support\Measurements;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pattern>
 */
class PatternFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'garment_type_id' => GarmentType::factory(),
            'name' => 'الگوی '.fake()->randomElement(['پیراهن', 'دامن', 'بلوز', 'شلوار']),
            'base_size' => '40',
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
            'seam_allowances' => ['default' => 1.0, 'hem' => 3.0, 'neck' => 0.7, 'shoulder' => 1.0, 'side' => 1.5],
            'version' => 1,
        ];
    }

    /** الگو به‌همراه دو قطعه ساده مستطیلی؛ برای آزمون‌هایی که به هندسه واقعی نیاز ندارند. */
    public function withSimplePieces(): static
    {
        return $this->afterCreating(function (Pattern $pattern) {
            foreach ([['front', 'جلو', 48, 62], ['back', 'پشت', 46, 62]] as [$code, $name, $width, $height]) {
                $pattern->pieces()->create([
                    'code' => $code,
                    'name' => $name,
                    'layer' => 'outer',
                    'cut_quantity' => 1,
                    'outline' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => $width, 'y' => 0],
                        ['x' => $width, 'y' => $height],
                        ['x' => 0, 'y' => $height],
                    ],
                    'grainline' => ['from' => ['x' => 5, 'y' => 5], 'to' => ['x' => 5, 'y' => $height - 5]],
                ]);
            }
        });
    }
}
