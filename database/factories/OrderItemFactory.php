<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'kind' => 'service',
            'description' => fake()->randomElement(['دوخت بالاتنه', 'نصب زیپ', 'دوخت آستر', 'اتوکاری نهایی']),
            'quantity' => 1,
            'unit' => 'عدد',
            'unit_price' => fake()->numberBetween(50, 800) * 1000,
        ];
    }

    /** ردیف پارچه با یکای متر. */
    public function fabric(int $fabricId, float $meters = 2.5): static
    {
        return $this->state(fn () => [
            'kind' => 'fabric',
            'fabric_id' => $fabricId,
            'description' => 'پارچه سفارش',
            'quantity' => $meters,
            'unit' => 'متر',
        ]);
    }

    public function material(int $materialId): static
    {
        return $this->state(fn () => [
            'kind' => 'material',
            'material_id' => $materialId,
            'description' => 'متعلقات سفارش',
            'unit' => 'عدد',
        ]);
    }
}
