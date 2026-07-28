<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_an_empty_state_without_data(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('هنوز داده‌ای برای گزارش نیست');
    }

    public function test_it_renders_every_section_with_data(): void
    {
        $this->actingAsWorkshopUser();
        $workshopId = $this->workshop()->id;

        $customer = Customer::factory()->create(['workshop_id' => $workshopId, 'name' => 'مریم احمدی']);

        $delivered = Order::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'title' => 'مانتو اداری',
            'status' => 'delivered',
            'price' => 3_000_000,
            'deposit' => 1_000_000,
            'delivered_at' => now(),
        ]);

        $delivered->forceFill(['created_at' => now()->subDays(6)])->save();

        $open = Order::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'title' => 'دامن راسته',
            'status' => 'sewing',
            'price' => 1_500_000,
            'deposit' => 0,
        ]);

        Payment::factory()->create([
            'workshop_id' => $workshopId,
            'order_id' => $delivered->id,
            'amount' => 500_000,
        ]);

        $fabric = Fabric::factory()->create([
            'workshop_id' => $workshopId,
            'name' => 'کرپ مازراتی',
            'color' => 'مشکی',
        ]);

        $delivered->items()->create([
            'kind' => 'fabric',
            'fabric_id' => $fabric->id,
            'description' => 'کرپ مازراتی — مشکی',
            'quantity' => 3.5,
            'unit' => 'متر',
            'unit_price' => 400_000,
        ]);

        $response = $this->get(route('reports.index'))->assertOk();

        $response
            ->assertDontSee('هنوز داده‌ای برای گزارش نیست')
            ->assertSee('سفارش‌ها و درآمد شش ماه گذشته')
            ->assertSee('وضعیت سفارش‌ها')
            ->assertSee('مشتری‌های پرکار')
            ->assertSee('مریم احمدی')
            ->assertSee('پارچه‌های پرمصرف')
            ->assertSee('کرپ مازراتی — مشکی')
            ->assertSee('میانگین زمان تحویل')
            ->assertSee('مانده حساب‌ها')
            ->assertSee('دامن راسته');

        // مانده = (۳٬۰۰۰٬۰۰۰ − ۱٬۵۰۰٬۰۰۰) + ۱٬۵۰۰٬۰۰۰
        $response->assertSee('۳,۰۰۰,۰۰۰ تومان');
    }

    public function test_cancelled_orders_are_left_out_of_the_balances(): void
    {
        $this->actingAsWorkshopUser();
        $workshopId = $this->workshop()->id;
        $customer = Customer::factory()->create(['workshop_id' => $workshopId]);

        Order::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'title' => 'سفارش لغوشده',
            'status' => 'cancelled',
            'price' => 900_000,
        ]);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('۰ تومان');
    }

    public function test_reports_do_not_leak_other_workshops(): void
    {
        $this->actingAsWorkshopUser();

        Order::factory()->create([
            'workshop_id' => Workshop::factory()->create()->id,
            'title' => 'سفارش بیگانه',
        ]);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('هنوز داده‌ای برای گزارش نیست')
            ->assertDontSee('سفارش بیگانه');
    }
}
