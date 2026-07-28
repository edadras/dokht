<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementSetTest extends TestCase
{
    use RefreshDatabase;

    protected function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'workshop_id' => $this->workshop()->id,
        ], $attributes));
    }

    /** شش عدد ضروری. */
    protected function essentials(): array
    {
        return [
            'height' => '168',
            'bust' => '92',
            'waist' => '74',
            'hip' => '98',
            'shoulder_width' => '39',
            'arm_length' => '58',
        ];
    }

    public function test_the_form_shows_the_six_essential_fields_and_hides_the_rest(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('measurements.create', $this->customer()))
            ->assertOk()
            ->assertSee('شش عددی که همیشه لازم است')
            ->assertSee('چطور اندازه بگیرم؟')
            ->assertSee('اندازه‌های تکمیلی (اختیاری)')
            ->assertSee('سایز استاندارد پیشنهادی');
    }

    public function test_a_set_saved_with_only_the_six_essentials_stores_exactly_those_values(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), ['values' => $this->essentials()])
            ->assertRedirect(route('customers.show', $customer));

        $set = MeasurementSet::firstOrFail();

        $this->assertSame(array_keys(Measurements::essential()), array_keys($set->values));
        $this->assertEqualsWithDelta(168.0, $set->values['height'], 0.01);
        $this->assertEqualsWithDelta(92.0, $set->values['bust'], 0.01);
        $this->assertSame($this->workshop()->id, $set->workshop_id);
        $this->assertSame('40', $set->base_size);
    }

    public function test_completed_fills_the_remaining_measurements(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), ['values' => $this->essentials()]);

        $set = MeasurementSet::firstOrFail();
        $completed = $set->completed();

        $this->assertCount(count(Measurements::FIELDS), $completed);
        $this->assertArrayNotHasKey('neck', $set->values);
        $this->assertEqualsWithDelta(92 * 0.40, $completed['neck'], 0.1);
        $this->assertEqualsWithDelta(98 * 0.58, $completed['thigh'], 0.1);
    }

    public function test_persian_digits_in_measurements_are_stored_as_latin_numbers(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), [
            'values' => [
                'height' => '۱۷۰',
                'bust' => '۹۶٫۵',
                'waist' => '۷۸',
                'hip' => '۱۰۲',
                'shoulder_width' => '۴۰',
                'arm_length' => '۵۹',
            ],
        ])->assertRedirect();

        $set = MeasurementSet::firstOrFail();

        $this->assertEqualsWithDelta(170.0, $set->values['height'], 0.01);
        $this->assertEqualsWithDelta(96.5, $set->values['bust'], 0.01);
        $this->assertSame('42', $set->base_size);
    }

    public function test_optional_measurements_are_saved_when_provided(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), [
            'values' => $this->essentials() + ['neck' => '36', 'wrist' => '16'],
        ])->assertRedirect();

        $set = MeasurementSet::firstOrFail();

        $this->assertEqualsWithDelta(36.0, $set->values['neck'], 0.01);
        $this->assertEqualsWithDelta(16.0, $set->values['wrist'], 0.01);
    }

    public function test_essential_measurements_are_required(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), ['values' => ['height' => '168']])
            ->assertSessionHasErrors(['values.bust', 'values.waist', 'values.hip']);

        $this->assertDatabaseCount('measurement_sets', 0);
    }

    public function test_each_measurement_is_validated_against_its_own_range(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), [
            'values' => ['bust' => '400'] + $this->essentials(),
        ])->assertSessionHasErrors('values.bust');

        $this->post(route('measurements.store', $customer), [
            'values' => ['height' => '10'] + $this->essentials(),
        ])->assertSessionHasErrors('values.height');
    }

    public function test_only_one_default_set_remains_per_customer(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $this->post(route('measurements.store', $customer), ['values' => $this->essentials()]);
        $this->post(route('measurements.store', $customer), [
            'values' => $this->essentials(),
            'is_default' => '1',
        ]);
        $this->post(route('measurements.store', $customer), [
            'values' => $this->essentials(),
            'is_default' => '1',
        ]);

        $this->assertSame(3, MeasurementSet::where('customer_id', $customer->id)->count());
        $this->assertSame(1, MeasurementSet::where('customer_id', $customer->id)->where('is_default', true)->count());

        $latest = MeasurementSet::where('customer_id', $customer->id)->orderByDesc('id')->firstOrFail();
        $this->assertTrue($latest->is_default);
    }

    public function test_a_set_can_be_edited(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $set = MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
        ]);

        $this->get(route('measurements.edit', $set))->assertOk();

        $this->put(route('measurements.update', $set), [
            'title' => 'اندازه‌های تازه',
            'values' => $this->essentials(),
        ])->assertRedirect(route('customers.show', $customer));

        $set->refresh();

        $this->assertSame('اندازه‌های تازه', $set->title);
        $this->assertEqualsWithDelta(168.0, $set->values['height'], 0.01);
    }

    public function test_deleting_the_default_set_promotes_another_one(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $older = MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'is_default' => false,
        ]);

        $default = MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'is_default' => true,
        ]);

        $this->delete(route('measurements.destroy', $default))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseMissing('measurement_sets', ['id' => $default->id]);
        $this->assertTrue($older->fresh()->is_default);
    }

    public function test_the_form_can_start_from_a_standard_size(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('measurements.create', ['customer' => $this->customer(), 'size' => '44']))
            ->assertOk()
            ->assertSee('value="100"', false);
    }

    public function test_the_form_can_copy_the_previous_set(): void
    {
        $this->actingAsWorkshopUser();
        $customer = $this->customer();

        $previous = MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'values' => ['bust' => 97, 'waist' => 79, 'hip' => 103],
        ]);

        $this->get(route('measurements.create', ['customer' => $customer, 'copy' => $previous->id]))
            ->assertOk()
            ->assertSee('value="97"', false);
    }

    public function test_another_workshops_measurement_set_is_not_found(): void
    {
        $this->actingAsWorkshopUser();

        $foreign = MeasurementSet::factory()->create();

        $this->get(route('measurements.edit', $foreign))->assertNotFound();
        $this->delete(route('measurements.destroy', $foreign))->assertNotFound();
    }
}
