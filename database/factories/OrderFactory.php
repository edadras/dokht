<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Workshop;
use App\Support\Jalali;
use App\Support\WorkshopContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        [$jy] = Jalali::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'));

        return [
            'workshop_id' => fn () => app(WorkshopContext::class)->id() ?? Workshop::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'workshop_id' => is_numeric($attributes['workshop_id'] ?? null)
                    ? (int) $attributes['workshop_id']
                    : (app(WorkshopContext::class)->id() ?? Workshop::factory()),
            ])->id,
            'code' => $jy.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 3, '0', STR_PAD_LEFT),
            'title' => fake()->randomElement([
                'مانتو اداری', 'پیراهن مجلسی', 'دامن راسته', 'شلوار پارچه‌ای', 'کت تک', 'تنگ کردن پیراهن',
            ]),
            'status' => 'new',
            'due_date' => now()->addDays(fake()->numberBetween(2, 20))->startOfDay(),
            'price' => fake()->numberBetween(300, 4000) * 1000,
            'deposit' => 0,
            'position' => 0,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /** سفارشی که تاریخ تحویلش گذشته است. */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'sewing',
            'due_date' => now()->subDays(3)->startOfDay(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
