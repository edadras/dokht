<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Workshop;
use App\Support\WorkshopContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => fn () => app(WorkshopContext::class)->id() ?? Workshop::factory(),
            'order_id' => Order::factory(),
            'amount' => fake()->numberBetween(100, 1500) * 1000,
            'method' => fake()->randomElement(array_keys(Payment::METHODS)),
            'paid_at' => now()->startOfDay(),
        ];
    }

    public function on(mixed $date): static
    {
        return $this->state(fn () => ['paid_at' => $date]);
    }
}
