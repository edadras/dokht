<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_a_persian_empty_state_for_a_fresh_workshop(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('هنوز مشتری‌ای ثبت نشده');
    }

    public function test_index_lists_customers_with_their_last_measurement_summary(): void
    {
        $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'مریم احمدی',
        ]);

        MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'values' => ['bust' => 92, 'waist' => 74, 'hip' => 98],
            'is_default' => true,
        ]);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('مریم احمدی')
            ->assertSee('سینه ۹۲');
    }

    public function test_index_can_search_by_name(): void
    {
        $this->actingAsWorkshopUser();

        Customer::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'زهرا کریمی']);
        Customer::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'سارا محمدی']);

        $this->get(route('customers.index', ['q' => 'زهرا']))
            ->assertOk()
            ->assertSee('زهرا کریمی')
            ->assertDontSee('سارا محمدی');
    }

    public function test_a_customer_needs_only_a_name(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('customers.create'))->assertOk();

        $response = $this->post(route('customers.store'), ['name' => 'مریم احمدی']);

        $customer = Customer::firstOrFail();

        $response->assertRedirect(route('customers.show', $customer));

        $this->assertSame('مریم احمدی', $customer->name);
        $this->assertSame('female', $customer->gender);
        $this->assertSame($this->workshop()->id, $customer->workshop_id);
    }

    public function test_persian_digits_in_a_phone_are_stored_as_latin(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('customers.store'), [
            'name' => 'سارا محمدی',
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'birth_date' => '۱۳۷۰/۰۵/۱۲',
        ])->assertRedirect();

        $customer = Customer::firstOrFail();

        $this->assertSame('09123456789', $customer->phone);
        $this->assertSame('1991-08-03', $customer->birth_date->toDateString());
    }

    public function test_name_is_required(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('customers.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_show_displays_the_measurement_book_orders_and_financial_totals(): void
    {
        $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'مریم احمدی',
        ]);

        MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'title' => 'اندازه‌های بهار',
            'is_default' => true,
        ]);

        Order::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'title' => 'مانتو اداری',
            'price' => 2_000_000,
            'deposit' => 500_000,
        ]);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('اندازه‌های بهار')
            ->assertSee('اندازه اصلی')
            ->assertSee('مانتو اداری')
            ->assertSee('کل سفارش‌ها')
            ->assertSee('۱,۵۰۰,۰۰۰ تومان');
    }

    public function test_a_customer_can_be_updated(): void
    {
        $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->get(route('customers.edit', $customer))->assertOk();

        $this->put(route('customers.update', $customer), [
            'name' => 'نام تازه',
            'phone' => '۰۹۳۵۱۱۱۲۲۳۳',
            'gender' => 'male',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();

        $this->assertSame('نام تازه', $customer->name);
        $this->assertSame('09351112233', $customer->phone);
        $this->assertSame('male', $customer->gender);
    }

    public function test_a_customer_is_soft_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_another_workshops_customer_is_not_found(): void
    {
        $this->actingAsWorkshopUser();

        $foreign = Customer::factory()->create();

        $this->assertNotSame($this->workshop()->id, $foreign->workshop_id);

        $this->get(route('customers.show', $foreign))->assertNotFound();
        $this->get(route('customers.edit', $foreign))->assertNotFound();
        $this->delete(route('customers.destroy', $foreign))->assertNotFound();
    }

    public function test_search_finds_a_customer_by_phone(): void
    {
        $this->actingAsWorkshopUser();

        Customer::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'سارا محمدی',
            'phone' => '09123456789',
        ]);

        $this->get(route('search', ['q' => '۰۹۱۲۳۴۵۶۷۸۹']))
            ->assertOk()
            ->assertSee('سارا محمدی');
    }

    public function test_search_with_an_empty_query_shows_recent_items(): void
    {
        $this->actingAsWorkshopUser();

        Customer::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'زهرا کریمی']);

        $this->get(route('search'))
            ->assertOk()
            ->assertSee('تازه‌ترین مشتری‌ها')
            ->assertSee('زهرا کریمی');
    }
}
