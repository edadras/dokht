<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Pattern\PatternComposer;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Database\Seeders\GarmentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternComposerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    protected function selection(array $overrides = []): array
    {
        return array_merge([
            'bodice' => 'bodice_block',
            'sleeve' => 'sleeve',
            'lower' => 'skirt_a_line',
            'collar' => 'shirt',
            'base_size' => '40',
        ], $overrides);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('patterns.compose'))->assertRedirect(route('login'));
        $this->post(route('patterns.compose.store'), $this->selection())->assertRedirect(route('login'));
    }

    public function test_the_page_shows_four_visual_pickers_with_thumbnails(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->get(route('patterns.compose'));

        $response->assertOk()
            ->assertSee('ترکیب مدل')
            ->assertSee('۱. بالاتنه')
            ->assertSee('۲. آستین')
            ->assertSee('۳. پایین‌تنه')
            ->assertSee('۴. یقه')
            ->assertSee('بدون آستین')
            ->assertSee('بدون یقه')
            ->assertSee('بالاتنه پایه')
            ->assertSee('دامن A')
            ->assertSee('یقه پیراهنی')
            ->assertSee('بساز')
            ->assertSee('<svg', false)
            ->assertSee('compose\/preview', false); // نشانی پیش‌نمایش زنده داخل x-data
    }

    public function test_the_advanced_settings_hold_the_expert_knobs(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('patterns.compose'))
            ->assertOk()
            ->assertSee('تنظیمات حرفه‌ای')
            ->assertSee('آزادی لباس')
            ->assertSee('چین کمر پایین‌تنه')
            ->assertSee('جای دوخت هر لبه')
            ->assertSee('شیب سرشانه')          // پارامتر بالاتنه
            ->assertSee('آزادی سرآستین')       // پارامتر آستین
            ->assertSee('گشادی دم دامن')       // پارامتر پایین‌تنه
            ->assertSee('برگشت نوک یقه');      // پارامتر یقه
    }

    public function test_the_live_preview_returns_an_svg_with_persian_notes(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->getJson(route('patterns.compose.preview', $this->selection()));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['svg', 'notes', 'metrics', 'pieces', 'name']);

        $this->assertStringContainsString('<svg', $response->json('svg'));
        $this->assertCount(6, $response->json('pieces'));
        $this->assertNotEmpty($response->json('notes'));
        $this->assertStringContainsString('ترکیب:', $response->json('name'));
    }

    public function test_the_preview_reports_the_waist_reconciliation_in_persian(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->getJson(route('patterns.compose.preview', $this->selection([
            'sleeve' => 'none',
            'collar' => 'none',
            'gather' => 10,
        ])));

        $response->assertOk()->assertJsonPath('metrics.waist.method', 'gather');

        $notes = collect($response->json('notes'))->pluck('text')->implode(' ');

        $this->assertStringContainsString('چین', $notes);
        $this->assertStringContainsString('سانتی‌متر', $notes);
    }

    public function test_the_preview_refuses_an_impossible_selection(): void
    {
        $this->actingAsWorkshopUser();

        $this->getJson(route('patterns.compose.preview', [
            'bodice' => 'bodice_block',
            'skirt' => 'skirt_a_line',
            'pants' => 'pants_straight',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'دامن و شلوار هم‌زمان به یک بالاتنه دوخته نمی‌شوند؛ فقط یکی را انتخاب کنید.');
    }

    public function test_it_composes_saves_and_versions_a_normal_pattern(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $response = $this->post(route('patterns.compose.store'), $this->selection(['name' => 'پیراهن ترکیبی خانم رضایی']));

        $pattern = Pattern::latest('id')->first();

        $this->assertNotNull($pattern);
        $response->assertRedirect(route('patterns.show', $pattern));
        $response->assertSessionHas('status');

        $this->assertSame('پیراهن ترکیبی خانم رضایی', $pattern->name);
        $this->assertSame($this->workshop()->id, $pattern->workshop_id);
        $this->assertSame('40', $pattern->base_size);
        $this->assertNull($pattern->pattern_template_id, 'ترکیب به الگوی پایه وصل نیست.');
        $this->assertNotNull($pattern->garment_type_id);

        $codes = $pattern->pieces->pluck('code')->all();
        $this->assertSame(['bodice-front', 'bodice-back', 'skirt-front', 'skirt-back', 'sleeve', 'collar'], $codes);
        $this->assertSame($codes, array_unique($codes));

        $this->assertNotEmpty($pattern->sewing_relations);
        $this->assertSame('bodice_block', $pattern->params['compose']['selection']['bodice']);
        $this->assertSame('skirt_a_line', $pattern->params['compose']['selection']['lower']);
        $this->assertNotEmpty($pattern->params['compose']['notes']);

        // نسخه اول ثبت شده و همه قطعه‌ها در آن هست
        $version = $pattern->versions()->first();
        $this->assertNotNull($version);
        $this->assertSame(1, (int) $version->version);
        $this->assertCount(6, $version->snapshot['pieces']);
        $this->assertStringContainsString('ترکیب مدل‌ها', (string) $version->note);
    }

    public function test_the_composed_pattern_opens_in_the_existing_screens(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $this->post(route('patterns.compose.store'), $this->selection());
        $pattern = Pattern::latest('id')->firstOrFail();

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('<svg', false);
        $this->get(route('patterns.editor', $pattern))->assertOk();
        $this->get(route('patterns.print', $pattern))->assertOk();
        $this->get(route('patterns.versions', $pattern))->assertOk();
    }

    public function test_it_uses_the_measurements_of_a_customer_when_one_is_chosen(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'مریم رضایی',
        ]);
        $set = MeasurementSet::factory()->for($customer)->create([
            'workshop_id' => $this->workshop()->id,
            'is_default' => true,
            'base_size' => '44',
            'values' => array_merge(Measurements::fromSize('44'), ['bust' => 104, 'waist' => 88]),
        ]);

        $this->post(route('patterns.compose.store'), $this->selection([
            'customer_id' => $customer->id,
            'base_size' => null,
        ]))->assertRedirect();

        $pattern = Pattern::latest('id')->firstOrFail();

        $this->assertSame($set->id, $pattern->measurement_set_id);
        $this->assertSame('44', $pattern->base_size);
        $this->assertEqualsWithDelta(104, $pattern->measurements['bust'], 0.1);
    }

    public function test_it_refuses_a_skirt_and_a_pair_of_trousers_at_the_same_time(): void
    {
        $this->actingAsWorkshopUser();

        $this->from(route('patterns.compose'))
            ->post(route('patterns.compose.store'), [
                'bodice' => 'bodice_block',
                'skirt' => 'skirt_a_line',
                'pants' => 'pants_straight',
                'base_size' => '40',
            ])
            ->assertRedirect(route('patterns.compose'))
            ->assertSessionHas('error', 'دامن و شلوار هم‌زمان به یک بالاتنه دوخته نمی‌شوند؛ فقط یکی را انتخاب کنید.');

        $this->assertSame(0, Pattern::count());
    }

    public function test_it_refuses_a_collar_without_a_bodice_and_an_empty_selection(): void
    {
        $this->actingAsWorkshopUser();

        $this->from(route('patterns.compose'))
            ->post(route('patterns.compose.store'), ['bodice' => 'skirt_a_line', 'base_size' => '40'])
            ->assertRedirect(route('patterns.compose'))
            ->assertSessionHas('error');

        $this->post(route('patterns.compose.store'), ['base_size' => '40'])
            ->assertSessionHasErrors('bodice');

        $this->assertSame(0, Pattern::count());
    }

    public function test_a_composed_pattern_stays_inside_its_workshop(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();
        $this->post(route('patterns.compose.store'), $this->selection());

        $mine = Pattern::latest('id')->firstOrFail();

        // کاربر کارگاه دیگر نه آن را می‌بیند نه به آن دسترسی دارد
        $other = Workshop::factory()->create();
        $user = User::factory()->for($other)->create(['role' => 'owner']);
        app(WorkshopContext::class)->set($other);
        $this->actingAs($user);

        $this->get(route('patterns.show', $mine))->assertNotFound();
        $this->get(route('patterns.index'))->assertOk()->assertDontSee($mine->name);
        $this->assertSame(0, Pattern::count());

        $this->post(route('patterns.compose.store'), $this->selection())->assertRedirect();
        $this->assertSame($other->id, Pattern::latest('id')->firstOrFail()->workshop_id);
    }

    public function test_the_composer_service_is_reachable_and_lists_its_options(): void
    {
        $options = app(PatternComposer::class)->options();

        $this->assertArrayHasKey('bodice', $options);
        $this->assertArrayHasKey('none', $options['sleeve']);
        $this->assertArrayHasKey('none', $options['collar']);
        $this->assertSame('بدون آستین', $options['sleeve']['none']['label']);
        $this->assertSame('بدون یقه', $options['collar']['none']['label']);
    }
}
