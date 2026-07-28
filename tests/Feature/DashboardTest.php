<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_first_run_guide_for_a_brand_new_workshop(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($this->workshop()->name)
            ->assertSee('سه گام برای شروع')
            ->assertSee('مشتری‌تان را ثبت کنید')
            ->assertSee('سفارش جدید')
            ->assertSee('اندازه جدید');
    }

    public function test_it_renders_for_a_populated_workshop(): void
    {
        $this->actingAsWorkshopUser();
        $workshopId = $this->workshop()->id;

        $customer = Customer::factory()->create(['workshop_id' => $workshopId, 'name' => 'مریم احمدی']);

        $soon = Order::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'title' => 'مانتو اداری',
            'status' => 'sewing',
            'price' => 2_000_000,
            'deposit' => 400_000,
        ]);
        $soon->update(['due_date' => now()->addDays(2)]);

        $late = Order::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'title' => 'دامن راسته',
            'status' => 'cutting',
        ]);
        $late->update(['due_date' => now()->subDays(3)]);

        Order::factory()->delivered()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'title' => 'کت تک',
        ]);

        Payment::factory()->create([
            'workshop_id' => $workshopId,
            'order_id' => $soon->id,
            'amount' => 600_000,
        ]);

        Project::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'name' => 'پیراهن تابستانی',
            'status' => 'in_progress',
        ]);

        Fabric::factory()->create([
            'workshop_id' => $workshopId,
            'name' => 'کتان نازک',
            'stock_meters' => 0.8,
        ]);

        $response = $this->get(route('dashboard'))->assertOk();

        $response
            ->assertDontSee('سه گام برای شروع')
            ->assertSee('کارهای امروز و این هفته')
            ->assertSee('مانتو اداری')
            ->assertSee('دامن راسته')
            ->assertSee('پروژه‌های در جریان')
            ->assertSee('پیراهن تابستانی')
            ->assertSee('کم‌موجودی پارچه')
            ->assertSee('کتان نازک')
            ->assertSee('سفارش‌های باز')
            ->assertSee('دیرکرد')
            ->assertSee('تخته تولید');
    }

    public function test_open_and_overdue_counts_ignore_delivered_orders(): void
    {
        $this->actingAsWorkshopUser();
        $workshopId = $this->workshop()->id;
        $customer = Customer::factory()->create(['workshop_id' => $workshopId]);

        $open = Order::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'status' => 'sewing',
        ]);
        $open->update(['due_date' => now()->subDays(5)]);

        $delivered = Order::factory()->delivered()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
        ]);
        $delivered->update(['due_date' => now()->subDays(9)]);

        $this->get(route('dashboard'))->assertOk();

        $this->assertSame(1, Order::open()->count());
        $this->assertFalse($delivered->fresh()->isOverdue());
    }

    public function test_the_dashboard_only_counts_the_current_workshop(): void
    {
        $this->actingAsWorkshopUser();

        Customer::factory()->count(3)->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('سه گام برای شروع');

        $this->assertSame(0, Customer::query()->count());
    }
}
