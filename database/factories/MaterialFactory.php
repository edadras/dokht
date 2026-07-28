<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        $kind = fake()->randomElement(array_keys(Material::KINDS));

        return [
            'workshop_id' => Workshop::factory(),
            'name' => Material::KINDS[$kind].' '.fake()->numberBetween(1, 99),
            'kind' => $kind,
            'spec' => fake()->randomElement(['۵۰۰ متری', 'شماره ۲۰', '۲۰ سانتی‌متری', 'عرض ۹۰', null]),
            'unit' => match ($kind) {
                'thread' => 'قرقره',
                'lining', 'interfacing', 'elastic' => 'متر',
                default => 'عدد',
            },
            'price' => fake()->numberBetween(5, 400) * 1000,
            'stock' => fake()->randomFloat(1, 0, 200),
            'color' => fake()->randomElement(['مشکی', 'سفید', 'کرم', 'سرمه‌ای', null]),
        ];
    }

    public function ofKind(string $kind): static
    {
        return $this->state(fn () => ['kind' => $kind, 'name' => Material::KINDS[$kind] ?? $kind]);
    }
}
