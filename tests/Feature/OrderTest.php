<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Workshop;
use App\Support\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'workshop_id' => $this->workshop()->id,
        ], $attributes));
    }

    protected function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $this->customer()->id,
        ], $attributes));
    }

    /** سفارشی در کارگاهی دیگر، برای آزمون جداسازی. */
    protected function foreignOrder(): Order
    {
        $workshop = Workshop::factory()->create();

        return Order::factory()->create([
            'workshop_id' => $workshop->id,
            'customer_id' => Customer::factory()->create(['workshop_id' => $workshop->id])->id,
        ]);
    }

    protected function currentJalaliYear(): int
    {
        return Jalali::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'))[0];
    }

    public function test_the_board_renders_a_column_for_every_status(): void
    {
        $this->actingAsWorkshopUser();

        $this->order(['title' => 'مانتو اداری', 'status' => 'sewing']);

        $response = $this->get(route('orders.index'))->assertOk();

        foreach (Order::STATUSES as $meta) {
            $response->assertSee($meta['label']);
        }

        $response->assertSee('مانتو اداری')->assertSee('data-drop-zone="sewing"', false);
    }

    public function test_the_board_shows_an_empty_state_when_there_is_no_order(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertSee('هنوز سفارشی ثبت نشده');
    }

    public function test_the_list_view_renders_with_filters(): void
    {
        $this->actingAsWorkshopUser();

        $this->order(['title' => 'پیراهن مجلسی', 'status' => 'cutting']);
        $this->order(['title' => 'دامن راسته', 'status' => 'ready']);

        $this->get(route('orders.index', ['view' => 'list']))
            ->assertOk()
            ->assertSee('فهرست سفارش‌ها')
            ->assertSee('پیراهن مجلسی')
            ->assertSee('دامن راسته');

        $this->get(route('orders.index', ['view' => 'list', 'status' => 'ready']))
            ->assertOk()
            ->assertSee('دامن راسته')
            ->assertDontSee('پیراهن مجلسی');
    }

    public function test_the_list_view_can_filter_overdue_orders(): void
    {
        $this->actingAsWorkshopUser();

        $this->order(['title' => 'کار دیرکرده'])->update(['due_date' => now()->subDays(4)]);
        $this->order(['title' => 'کار سر وقت'])->update(['due_date' => now()->addDays(6)]);

        $this->get(route('orders.index', ['view' => 'list', 'overdue' => 1]))
            ->assertOk()
            ->assertSee('کار دیرکرده')
            ->assertDontSee('کار سر وقت');
    }

    public function test_creating_an_order_assigns_a_sequential_jalali_code_per_workshop(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->get(route('orders.create'))->assertOk();

        $this->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'title' => 'مانتو اداری',
            'due_date' => '۱۴۰۵/۰۶/۱۰',
        ])->assertRedirect();

        $this->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'title' => 'دامن راسته',
        ])->assertRedirect();

        $year = $this->currentJalaliYear();
        $codes = Order::orderBy('id')->pluck('code')->all();

        $this->assertSame([$year.'-001', $year.'-002'], $codes);
    }

    public function test_codes_restart_for_a_different_workshop(): void
    {
        $this->actingAsWorkshopUser();
        $this->post(route('orders.store'), [
            'customer_id' => $this->customer()->id,
            'title' => 'سفارش کارگاه یک',
        ])->assertRedirect();

        // کارگاه دوم با کاربر خودش
        $this->actingAsWorkshopUser();
        $this->post(route('orders.store'), [
            'customer_id' => $this->customer()->id,
            'title' => 'سفارش کارگاه دو',
        ])->assertRedirect();

        $year = $this->currentJalaliYear();

        $this->assertSame($year.'-001', Order::where('title', 'سفارش کارگاه دو')->firstOrFail()->code);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_a_jalali_due_date_is_converted_and_persian_amounts_are_stored_as_numbers(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('orders.store'), [
            'customer_id' => $this->customer()->id,
            'title' => 'کت تک',
            'due_date' => '۱۴۰۵/۰۵/۱۲',
            'price' => '۲,۵۰۰,۰۰۰',
            'deposit' => '۵۰۰۰۰۰',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('2026-08-03', $order->due_date->toDateString());
        $this->assertSame(2500000.0, $order->price);
        $this->assertSame(500000.0, $order->deposit);
    }

    public function test_a_bad_jalali_due_date_is_rejected(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('orders.store'), [
            'customer_id' => $this->customer()->id,
            'title' => 'کت تک',
            'due_date' => '۱۴۰۵/۱۳/۴۰',
        ])->assertSessionHasErrors('due_date');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_new_customer_can_be_typed_inline(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('orders.store'), [
            'new_customer_name' => 'زهرا کریمی',
            'title' => 'شلوار پارچه‌ای',
        ])->assertRedirect();

        $customer = Customer::firstOrFail();
        $order = Order::firstOrFail();

        $this->assertSame('زهرا کریمی', $customer->name);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($this->workshop()->id, $customer->workshop_id);
    }

    public function test_an_order_needs_a_customer_and_a_title(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('orders.store'), ['title' => 'بی‌مشتری'])
            ->assertSessionHasErrors('customer_id');

        $this->post(route('orders.store'), ['customer_id' => $this->customer()->id])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_show_page_renders_the_stepper_items_and_payment_form(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order(['title' => 'مانتو اداری', 'price' => 2_000_000, 'deposit' => 300_000]);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('مانتو اداری')
            ->assertSee('بیل مواد و خدمات')
            ->assertSee('ثبت پرداخت')
            ->assertSee('مانده');
    }

    public function test_status_can_be_updated_and_sets_delivered_at(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order(['status' => 'sewing']);

        $this->patch(route('orders.status', $order), ['status' => 'delivered'])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);

        $this->patch(route('orders.status', $order), ['status' => 'fitting']);

        $order->refresh();

        $this->assertSame('fitting', $order->status);
        $this->assertNull($order->delivered_at);
    }

    public function test_status_update_returns_json_for_the_board_drag(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order(['status' => 'new']);

        $this->patchJson(route('orders.status', $order), ['status' => 'cutting'])
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $this->assertSame('cutting', $order->fresh()->status);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order(['status' => 'new']);

        $this->patch(route('orders.status', $order), ['status' => 'flying'])
            ->assertSessionHasErrors('status');

        $this->assertSame('new', $order->fresh()->status);
    }

    public function test_an_order_can_be_edited_and_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $order = $this->order();

        $this->get(route('orders.edit', $order))->assertOk();

        $this->put(route('orders.update', $order), [
            'customer_id' => $order->customer_id,
            'title' => 'عنوان تازه',
            'status' => 'fitting',
        ])->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('عنوان تازه', $order->title);
        $this->assertSame('fitting', $order->status);

        $this->delete(route('orders.destroy', $order))->assertRedirect(route('orders.index'));
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_the_invoice_renders_with_workshop_and_customer_details(): void
    {
        $this->actingAsWorkshopUser();

        $customer = $this->customer(['name' => 'مریم احمدی']);
        $order = $this->order(['customer_id' => $customer->id, 'title' => 'مانتو اداری', 'price' => 1_200_000]);

        $order->items()->create([
            'kind' => 'service',
            'description' => 'دوخت بالاتنه',
            'quantity' => 1,
            'unit' => 'عدد',
            'unit_price' => 400_000,
        ]);

        $this->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('فاکتور فروش و خدمات')
            ->assertSee($this->workshop()->name)
            ->assertSee('مریم احمدی')
            ->assertSee('دوخت بالاتنه')
            ->assertSee('امضای مشتری')
            ->assertSee('۱,۲۰۰,۰۰۰ تومان');
    }

    public function test_an_assignee_from_another_workshop_is_ignored(): void
    {
        $this->actingAsWorkshopUser();

        $foreignUser = User::factory()->withWorkshop()->create();

        $this->post(route('orders.store'), [
            'customer_id' => $this->customer()->id,
            'title' => 'مانتو',
            'assigned_to' => $foreignUser->id,
        ])->assertRedirect();

        $this->assertNull(Order::firstOrFail()->assigned_to);
    }

    public function test_another_workshops_order_is_not_found(): void
    {
        $this->actingAsWorkshopUser();

        $foreign = $this->foreignOrder();

        $this->assertNotSame($this->workshop()->id, $foreign->workshop_id);

        $this->get(route('orders.show', $foreign))->assertNotFound();
        $this->get(route('orders.invoice', $foreign))->assertNotFound();
        $this->patch(route('orders.status', $foreign), ['status' => 'ready'])->assertNotFound();
        $this->delete(route('orders.destroy', $foreign))->assertNotFound();
    }
}
