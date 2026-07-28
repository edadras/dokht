<?php

namespace Database\Factories;

use App\Models\Pattern;
use App\Models\PatternPiece;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatternPiece>
 */
class PatternPieceFactory extends Factory
{
    public function definition(): array
    {
        $width = fake()->numberBetween(20, 60);
        $height = fake()->numberBetween(30, 80);

        return [
            'pattern_id' => Pattern::factory(),
            'code' => 'piece-'.fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->randomElement(['بالاتنه جلو', 'بالاتنه پشت', 'آستین', 'یقه']),
            'layer' => 'outer',
            'cut_quantity' => 1,
            'outline' => [
                ['x' => 0, 'y' => 0],
                ['x' => $width, 'y' => 0],
                ['x' => $width, 'y' => $height],
                ['x' => 0, 'y' => $height],
            ],
            'grainline' => ['from' => ['x' => 4, 'y' => 4], 'to' => ['x' => 4, 'y' => $height - 4]],
        ];
    }
}
