<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => Customer::factory()->create(['workshop_id' => $this->workshop()->id])->id,
        ], $attributes));
    }

    protected function foreignOrder(): Order
    {
        $workshop = Workshop::factory()->create();

        return Order::factory()->create([
            'workshop_id' => $workshop->id,
            'customer_id' => Customer::factory()->create(['workshop_id' => $workshop->id])->id,
        ]);
    }

    public function test_remaining_amount_is_price_minus_deposit_and_payments(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order(['price' => 3_000_000, 'deposit' => 500_000]);

        Payment::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'order_id' => $order->id,
            'amount' => 1_000_000,
        ]);

        Payment::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'order_id' => $order->id,
            'amount' => 250_000,
        ]);

        $order->load('payments');

        $this->assertSame(1_750_000.0, $order->paidAmount());
        $this->assertSame(1_250_000.0, $order->remainingAmount());
    }

    public function test_a_payment_can_be_recorded_with_persian_digits_and_a_jalali_date(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order(['price' => 2_000_000]);

        $this->post(route('payments.store', $order), [
            'amount' => '۱,۲۰۰,۰۰۰',
            'method' => 'card',
            'paid_at' => '۱۴۰۵/۰۵/۱۲',
            'note' => 'بیعانه اول',
        ])->assertRedirect(route('orders.show', $order));

        $payment = Payment::firstOrFail();

        $this->assertSame(1_200_000.0, $payment->amount);
        $this->assertSame('card', $payment->method);
        $this->assertSame('2026-08-03', $payment->paid_at->toDateString());
        $this->assertSame($this->workshop()->id, $payment->workshop_id);
        $this->assertSame(800_000.0, $order->fresh()->load('payments')->remainingAmount());
    }

    public function test_a_payment_without_a_date_falls_back_to_today(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $this->post(route('payments.store', $order), ['amount' => '500000', 'method' => 'cash'])
            ->assertRedirect();

        $this->assertSame(now()->toDateString(), Payment::firstOrFail()->paid_at->toDateString());
    }

    public function test_a_payment_needs_an_amount_and_a_known_method(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $this->post(route('payments.store', $order), ['amount' => '', 'method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->post(route('payments.store', $order), ['amount' => '1000', 'method' => 'bitcoin'])
            ->assertSessionHasErrors('method');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_payment_can_be_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $payment = Payment::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'order_id' => $order->id,
        ]);

        $this->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_another_workshops_payment_and_order_are_not_found(): void
    {
        $this->actingAsWorkshopUser();

        $foreign = $this->foreignOrder();

        $this->post(route('payments.store', $foreign), ['amount' => '1000', 'method' => 'cash'])
            ->assertNotFound();

        $foreignPayment = Payment::factory()->create([
            'workshop_id' => $foreign->workshop_id,
            'order_id' => $foreign->id,
        ]);

        $this->delete(route('payments.destroy', $foreignPayment))->assertNotFound();
    }

    public function test_a_fabric_item_fills_the_unit_and_price_from_the_fabric(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $fabric = Fabric::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'کرپ مازراتی',
            'color' => 'سرمه‌ای',
            'price_per_meter' => 450_000,
        ]);

        $this->post(route('order-items.store', $order), [
            'kind' => 'fabric',
            'fabric_id' => $fabric->id,
            'quantity' => '۲٫۵',
        ])->assertRedirect(route('orders.show', $order));

        $item = OrderItem::firstOrFail();

        $this->assertSame('fabric', $item->kind);
        $this->assertSame('متر', $item->unit);
        $this->assertSame(450_000.0, $item->unit_price);
        $this->assertSame(2.5, $item->quantity);
        $this->assertSame('کرپ مازراتی — سرمه‌ای', $item->description);
        $this->assertSame(1_125_000.0, $item->total());
    }

    public function test_a_material_item_uses_the_material_unit_and_price(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $material = Material::create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'زیپ مخفی',
            'kind' => 'zipper',
            'unit' => 'عدد',
            'price' => 25_000,
            'stock' => 40,
        ]);

        $this->post(route('order-items.store', $order), [
            'kind' => 'material',
            'material_id' => $material->id,
            'quantity' => '2',
        ])->assertRedirect();

        $item = OrderItem::firstOrFail();

        $this->assertSame('زیپ مخفی', $item->description);
        $this->assertSame(25_000.0, $item->unit_price);
        $this->assertSame(50_000.0, $item->total());
    }

    public function test_a_service_item_uses_free_text(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $this->post(route('order-items.store', $order), [
            'kind' => 'service',
            'description' => 'دوخت آستر',
            'quantity' => '1',
            'unit_price' => '۳۰۰۰۰۰',
        ])->assertRedirect();

        $item = OrderItem::firstOrFail();

        $this->assertSame('دوخت آستر', $item->description);
        $this->assertSame(300_000.0, $item->unit_price);
        $this->assertSame(300_000.0, $order->fresh()->load('items')->itemsTotal());
    }

    public function test_a_fabric_from_another_workshop_is_refused(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();
        $foreignFabric = Fabric::factory()->create();

        $this->post(route('order-items.store', $order), [
            'kind' => 'fabric',
            'fabric_id' => $foreignFabric->id,
            'quantity' => '2',
        ])->assertRedirect();

        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_an_order_item_can_be_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $item = $order->items()->create([
            'kind' => 'service',
            'description' => 'اتوکاری',
            'quantity' => 1,
            'unit' => 'عدد',
            'unit_price' => 50_000,
        ]);

        $this->delete(route('order-items.destroy', $item))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_another_workshops_order_item_is_not_found(): void
    {
        $this->actingAsWorkshopUser();

        $foreignItem = $this->foreignOrder()->items()->create([
            'kind' => 'service',
            'description' => 'کار کارگاه دیگر',
            'quantity' => 1,
            'unit' => 'عدد',
            'unit_price' => 10_000,
        ]);

        $this->delete(route('order-items.destroy', $foreignItem))->assertNotFound();
    }
}
