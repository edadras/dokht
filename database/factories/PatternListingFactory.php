<?php

namespace Database\Factories;

use App\Models\Pattern;
use App\Models\PatternListing;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatternListing>
 */
class PatternListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_workshop_id' => Workshop::factory(),
            'pattern_id' => Pattern::factory(),
            'garment_type_id' => null,
            'title' => 'الگوی '.fake()->randomElement(['مانتو جلوباز', 'پیراهن مجلسی', 'دامن ترک', 'شلوار دمپاگشاد']),
            'description' => 'الگوی آماده، با همه قطعه‌ها و جای دوخت.',
            'price' => fake()->numberBetween(1, 20) * 50000,
            'currency' => 'تومان',
            'is_active' => true,
            'preview' => ['piece_count' => 2, 'cut_count' => 2, 'base_size' => '40', 'size_range' => '۳۴ تا ۴۸'],
            'tags' => [],
        ];
    }

    /** آگهی برای الگوی مشخصی از یک کارگاه مشخص. */
    public function forPattern(Pattern $pattern): static
    {
        return $this->state(fn () => [
            'pattern_id' => $pattern->id,
            'seller_workshop_id' => $pattern->workshop_id,
            'garment_type_id' => $pattern->garment_type_id,
            'title' => $pattern->name,
        ]);
    }

    /** آگهی برداشته‌شده از ویترین. */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }
}
