<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\Style\StyleRegistry;
use App\Services\Pattern\SvgRenderer;
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

    public function test_the_studio_shows_the_three_steps_and_the_first_pack_of_models(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->get(route('patterns.compose'));

        $response->assertOk()
            ->assertSee('کارگاه دوخت')
            ->assertSee('۱. پایه لباس')
            ->assertSee('۲. سبک‌ها')
            ->assertSee('۳. اندازه‌های لباس')
            ->assertSee('۴. اندازه‌ها برای چه کسی؟')
            ->assertSee('یک لباس کامل')
            ->assertSee('از بلوک بساز')
            ->assertSee('بدون آستین')
            ->assertSee('بدون یقه')
            ->assertSee('بساز')
            ->assertSee('<svg', false)
            ->assertSee('compose/preview', false);

        /*
         * فهرستِ مدل‌ها دیگر یک‌جا در صفحه نیست — با هزاران مدل، صفحه چند مگابایت
         * می‌شد. فقط بستهٔ اولِ هر نقش می‌آید و بقیه از سرور. پس این‌جا بستهٔ اول
         * را می‌بینیم و در آزمونِ بعدی می‌بینیم که جستجو به بقیه هم می‌رسد.
         */
        foreach (['bodice', 'garment'] as $group) {
            foreach (array_slice(app(PatternComposer::class)->catalogue()['base'][$group], 0, 3) as $item) {
                $response->assertSee($item['label']);
            }
        }

        // سبک‌ها کم‌شمارند و همه در صفحه‌اند
        foreach (StyleRegistry::grouped() as $row) {
            $response->assertSee($row['label']);

            foreach ($row['styles'] as $style) {
                $response->assertSee($style->label());
            }
        }
    }

    public function test_a_style_the_base_cannot_take_is_shown_disabled_with_its_persian_reason(): void
    {
        $this->actingAsWorkshopUser();

        $composer = app(PatternComposer::class);
        $selection = ['bodice' => 'bodice_block', 'sleeve' => 'none', 'lower' => 'none', 'collar' => 'none'];
        $pieces = $composer->compose($selection, Measurements::fromSize('40'))['pieces'];
        $refused = collect($composer->styleAvailability($pieces, ['measurements' => Measurements::fromSize('40')]))
            ->reject(fn (array $row) => $row['ok']);

        $this->assertNotEmpty($refused, 'بدون آستین باید دست‌کم یک سبک رد شود.');

        $response = $this->get(route('patterns.compose', $selection));
        $response->assertOk();

        foreach ($refused->take(3) as $key => $row) {
            $response->assertSee(StyleRegistry::make($key)->label());
            $response->assertSee($row['reason']);
        }
    }

    public function test_the_page_can_be_searched_and_stays_grouped(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('patterns.compose'))
            ->assertOk()
            ->assertSee('جستجوی نام مدل…')
            ->assertSee('مدل‌های بیشتر')
            ->assertSee('سبک‌های سازگار با این پایه');
    }

    /**
     * فهرست بسته‌بسته از سرور می‌آید، پس جستجو باید به مدلی هم برسد که در صفحه نیست.
     */
    public function test_the_model_list_endpoint_pages_and_searches_the_whole_role(): void
    {
        $this->actingAsWorkshopUser();

        $bodices = app(PatternComposer::class)->catalogue()['base']['bodice'];
        $this->assertGreaterThan(12, count($bodices), 'این آزمون فقط با نقشِ پرمدل معنا دارد.');

        $first = $this->getJson(route('patterns.compose.models', ['group' => 'bodice']))->assertOk();
        $first->assertJsonPath('total', count($bodices))->assertJsonPath('more', true);
        $this->assertCount(12, $first->json('rows'));

        // بستهٔ دوم باید مدل‌های *دیگری* باشد، نه همان‌ها
        $second = $this->getJson(route('patterns.compose.models', ['group' => 'bodice', 'page' => 2]))->assertOk();
        $this->assertEmpty(array_intersect(
            array_column($first->json('rows'), 'k'),
            array_column($second->json('rows'), 'k'),
        ), 'بستهٔ دوم همان بستهٔ اول است؛ صفحه‌بندی کار نمی‌کند.');

        // و جستجو باید آخرین مدلِ فهرست را هم پیدا کند — همانی که در هیچ بسته‌ای نیامده
        $lastKey = array_key_last($bodices);
        $found = $this->getJson(route('patterns.compose.models', [
            'group' => 'bodice',
            'q' => $bodices[$lastKey]['label'],
        ]))->assertOk();
        $this->assertContains($lastKey, array_column($found->json('rows'), 'k'));

        // نقشی که وجود ندارد نباید فهرستِ خالی بدهد، باید نبودنش را بگوید
        $this->getJson(route('patterns.compose.models', ['group' => 'no_such_role']))->assertNotFound();
    }

    /**
     * صفحهٔ کارگاه نباید با بزرگ‌شدن کاتالوگ سنگین شود.
     *
     * تلهٔ واقعی این بود: صفحه برای *هر* مدل یک بندانگشتی می‌ساخت و همان‌جا در
     * HTML می‌گذاشت. با چهل مدل کند بود؛ با هزاران مدل یعنی هزاران بار ساختِ
     * الگو در یک درخواست و صفحه‌ای چند ده مگابایتی.
     *
     * حالا بندانگشتی نشانی دارد و مرورگر آن‌هایی را می‌گیرد که می‌بیند. تلهٔ دوم
     * ظریف‌تر بود: خودِ *فهرست* — فقط نام و کلید و توضیح — هم با هفده هزار مدل
     * سه و نیم مگابایت می‌شد. آن هم رفت؛ صفحه بستهٔ اول را دارد و بس.
     *
     * پس این آزمون سه چیز را می‌پاید: نشانیِ بندانگشتی در صفحه باشد، شمارِ
     * نقشه‌های SVG انگشت‌شمار بماند، و حجم صفحه *مطلقاً* کوچک بماند — نه اینکه
     * فقط کندتر از کاتالوگ رشد کند.
     */
    public function test_the_studio_page_does_not_grow_with_the_catalogue(): void
    {
        $this->actingAsWorkshopUser();

        $models = count(GeneratorRegistry::all());
        $this->assertGreaterThan(500, $models, 'این آزمون فقط با کاتالوگ بزرگ معنا دارد.');

        $html = $this->get(route('patterns.compose'))->assertOk()->getContent();

        $this->assertStringContainsString('compose/thumb/', $html, 'بندانگشتی‌ها باید نشانی داشته باشند.');

        // آیکون‌های خود صفحه SVG‌اند و اشکالی ندارد؛ آنچه نباید باشد، یک نقشهٔ
        // الگو به ازای هر مدل است
        $svgs = substr_count($html, '<svg');
        $this->assertLessThan(
            200,
            $svgs,
            "صفحه {$svgs} نقشهٔ SVG با خودش دارد در حالی که کاتالوگ {$models} مدل دارد؛"
                .' یعنی بندانگشتی‌ها دوباره در خود صفحه ساخته می‌شوند.',
        );

        // حجم صفحه باید به تعداد مدل‌ها بی‌اعتنا باشد
        $kilobytes = strlen($html) / 1024;
        $this->assertLessThan(
            400,
            $kilobytes,
            'صفحه '.round($kilobytes)." کیلوبایت است با {$models} مدل در کاتالوگ؛"
                .' یعنی فهرست دوباره یک‌جا در صفحه نوشته می‌شود.',
        );

        // و برای اطمینان: مدلی که در بستهٔ اول نیست نباید در صفحه باشد
        $bodices = app(PatternComposer::class)->catalogue()['base']['bodice'];
        $last = end($bodices);
        $this->assertStringNotContainsString(
            (string) $last['label'],
            $html,
            'آخرین مدلِ بالاتنه در صفحه هست؛ پس همهٔ فهرست فرستاده شده.',
        );
    }

    public function test_a_thumbnail_is_served_on_its_own_and_a_broken_one_does_not_break_the_page(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('patterns.compose.thumb', ['group' => 'bodice', 'key' => 'bodice_block']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);

        // مدلی که وجود ندارد باید نشانِ خالی بگیرد، نه خطای پانصد
        $this->get(route('patterns.compose.thumb', ['group' => 'bodice', 'key' => 'no_such_model']))
            ->assertOk()
            ->assertSee('<svg', false);
    }

    public function test_the_advanced_settings_hold_the_expert_knobs(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->get(route('patterns.compose'));

        $response->assertOk()
            ->assertSee('تنظیمات حرفه‌ای')
            ->assertSee('آزادی لباس')
            ->assertSee('چین کمر پایین‌تنه')
            ->assertSee('جای دوخت هر لبه')
            ->assertSee('تنظیم‌های ریزِ مدل‌ها')
            ->assertSee('پارامترهای سبک‌ها')
            // توضیح پارامترهای همان چیزی که انتخاب شده، در داده صفحه
            ->assertSee('شیب سرشانه', false)      // تنظیم ریزِ بالاتنه
            ->assertSee('آزادی سرآستین', false);  // تنظیم ریزِ آستین
    }

    public function test_the_live_preview_returns_an_svg_with_persian_notes(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->getJson(route('patterns.compose.preview', $this->selection()));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['svg', 'notes', 'metrics', 'pieces', 'name']);

        $this->assertStringContainsString('<svg', $response->json('svg'));
        $this->assertGreaterThanOrEqual(6, count($response->json('pieces')));
        $this->assertNotEmpty($response->json('notes'));
        $this->assertStringContainsString('ترکیب:', $response->json('name'));
    }

    public function test_the_preview_reports_the_waist_reconciliation_in_persian(): void
    {
        $this->actingAsWorkshopUser();

        // اندازه چین را از روی همین ترکیب حساب می‌کنیم تا آزمون به اندازه بلوک‌ها گره نخورد
        $selection = $this->selection(['sleeve' => 'none', 'collar' => 'none']);
        $waist = app(PatternComposer::class)
            ->compose($selection, Measurements::fromSize('40'))['metrics']['waist'];
        $gather = round(($waist['bodice'] - $waist['lower']) + 8, 1);

        $response = $this->getJson(route('patterns.compose.preview', $selection + ['gather' => $gather]));

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
        $response->assertRedirect(route('patterns.compose', ['pattern' => $pattern->id]));
        $response->assertSessionHas('status');
        $response->assertSessionHas('composed');

        $this->assertSame('پیراهن ترکیبی خانم رضایی', $pattern->name);
        $this->assertSame($this->workshop()->id, $pattern->workshop_id);
        $this->assertSame('40', $pattern->base_size);
        $this->assertNull($pattern->pattern_template_id, 'ترکیب به الگوی پایه وصل نیست.');
        $this->assertNotNull($pattern->garment_type_id);

        $codes = $pattern->pieces->pluck('code')->all();
        $this->assertSame($codes, array_unique($codes));
        $this->assertGreaterThanOrEqual(6, count($codes));
        $this->assertSame(
            ['bodice', 'lower', 'sleeve', 'collar'],
            $pattern->pieces->pluck('meta.group')->unique()->values()->all(),
            'هر چهار بخش باید در الگو باشد و به همین ترتیب.',
        );

        $this->assertNotEmpty($pattern->sewing_relations);
        $this->assertSame('bodice_block', $pattern->params['compose']['selection']['bodice']);
        $this->assertSame('skirt_a_line', $pattern->params['compose']['selection']['lower']);
        $this->assertNotEmpty($pattern->params['compose']['notes']);

        // نسخه اول ثبت شده و همه قطعه‌ها در آن هست
        $version = $pattern->versions()->first();
        $this->assertNotNull($version);
        $this->assertSame(1, (int) $version->version);
        $this->assertCount($pattern->pieces->count(), $version->snapshot['pieces']);
        $this->assertStringContainsString('ترکیب مدل‌ها', (string) $version->note);
    }

    public function test_after_composing_the_studio_reports_what_it_did_and_links_to_the_pattern(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $this->post(route('patterns.compose.store'), $this->selection())->assertRedirect();
        $pattern = Pattern::latest('id')->firstOrFail();

        $response = $this->followingRedirects()->post(route('patterns.compose.store'), $this->selection());
        $made = Pattern::latest('id')->firstOrFail();

        $response->assertOk()
            ->assertSee('ساخته شد: '.$made->name)
            ->assertSee('باز کردن الگو')
            ->assertSee(route('patterns.show', $made), false);

        // یادداشت‌های جورکردن، به فارسی ساده، همان‌جا زیر گزارش می‌آیند
        foreach (collect($made->params['compose']['notes'])->take(3) as $note) {
            $response->assertSee($note['text']);
        }

        $this->assertNotSame($pattern->id, $made->id);
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

        // دستور الگوی کارگاه دیگر هم در کارگاه ترکیب باز نمی‌شود
        $this->get(route('patterns.compose', ['pattern' => $mine->id]))
            ->assertOk()
            ->assertDontSee('از روی دستور یک الگوی ساخته‌شده باز شد');

        $this->post(route('patterns.compose.store'), $this->selection())->assertRedirect();
        $this->assertSame($other->id, Pattern::latest('id')->firstOrFail()->workshop_id);
    }

    /* ---------------------------------------------------------------------
     |  دستور کامل: پایه + سبک‌ها
     * ------------------------------------------------------------------- */

    /** @return array<int, string> سه سبک از سه گروه که روی این پایه می‌نشینند */
    protected function styles(array $selection): array
    {
        $composer = app(PatternComposer::class);
        $pieces = $composer->compose($selection, Measurements::fromSize('40'))['pieces'];
        $availability = $composer->styleAvailability($pieces, ['measurements' => Measurements::fromSize('40')]);
        $picked = [];

        foreach (['neckline', 'hem', 'pocket'] as $group) {
            foreach (StyleRegistry::group($group) as $key => $style) {
                if ($availability[$key]['ok'] ?? false) {
                    $picked[] = $key;

                    break;
                }
            }
        }

        return $picked;
    }

    public function test_the_live_preview_answers_a_partial_selection_and_reports_the_styles(): void
    {
        $this->actingAsWorkshopUser();

        // فقط یک بالاتنه، بدون آستین و پایین‌تنه و یقه
        $partial = $this->getJson(route('patterns.compose.preview', ['bodice' => 'bodice_block']));

        $partial->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['svg', 'notes', 'metrics', 'pieces', 'name', 'availability', 'schemas']);

        $this->assertCount(2, $partial->json('pieces'));
        $this->assertStringContainsString('<svg', $partial->json('svg'));
        $this->assertArrayHasKey('bodice', $partial->json('schemas.roles'));

        // و همان بالاتنه با چند سبک
        $selection = ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'lower' => 'skirt_a_line'];
        $styles = $this->styles($selection);

        $this->assertGreaterThanOrEqual(2, count($styles));

        $response = $this->getJson(route('patterns.compose.preview', $selection + [
            'styles' => array_map(fn (string $key) => ['key' => $key], $styles),
        ]));

        $response->assertOk()->assertJsonPath('status', 'ok');

        $this->assertSame($styles, array_column($response->json('styles'), 'key'));
        $this->assertSame(
            array_fill(0, count($styles), 'applied'),
            array_column($response->json('styles'), 'status'),
        );
        $this->assertGreaterThan(count($partial->json('pieces')), count($response->json('pieces')));

        foreach ($styles as $key) {
            $this->assertArrayHasKey($key, $response->json('schemas.styles'));
        }
    }

    public function test_the_preview_says_which_styles_this_base_can_take(): void
    {
        $this->actingAsWorkshopUser();

        $withSleeve = $this->getJson(route('patterns.compose.preview', ['bodice' => 'bodice_block', 'sleeve' => 'sleeve']));
        $without = $this->getJson(route('patterns.compose.preview', ['bodice' => 'bodice_block', 'sleeve' => 'none']));

        $cuff = array_key_first(StyleRegistry::group('detail'));

        if ($cuff === null) {
            $this->markTestSkipped('هنوز سبک جزئیات (مچ) در کاتالوگ نیست.');
        }

        $this->assertTrue($withSleeve->json("availability.{$cuff}.ok"), 'با آستین، مچ باید ممکن باشد.');
        $this->assertFalse($without->json("availability.{$cuff}.ok"), 'بدون آستین، مچ نباید ممکن باشد.');
        $this->assertStringContainsString('آستین', $without->json("availability.{$cuff}.reason"));
    }

    public function test_a_refused_style_is_skipped_in_persian_and_the_pattern_is_still_built(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $cuff = array_key_first(StyleRegistry::group('detail'));

        if ($cuff === null) {
            $this->markTestSkipped('هنوز سبک جزئیات (مچ) در کاتالوگ نیست.');
        }

        $this->post(route('patterns.compose.store'), [
            'kind' => 'blocks',
            'bodice' => 'bodice_block',
            'sleeve' => 'none',
            'lower' => 'none',
            'base_size' => '40',
            'styles' => [['key' => $cuff]],
        ])->assertRedirect();

        $pattern = Pattern::latest('id')->firstOrFail();
        $report = collect($pattern->params['compose']['metrics']['styles']);

        $this->assertSame('skipped', $report->firstWhere('key', $cuff)['status']);
        $this->assertNotEmpty($pattern->pieces, 'لباس باید با وجود رد شدن سبک ساخته شود.');
        $this->assertNotEmpty(collect($pattern->params['compose']['notes'])
            ->filter(fn (array $note) => $note['type'] === 'warning' && str_contains($note['text'], 'اجرا نشد')));
    }

    public function test_it_composes_a_whole_garment_base_with_styles_and_stores_the_recipe(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $garment = array_key_first(GeneratorRegistry::group('garment'));
        $styles = $this->styles(['kind' => 'garment', 'garment' => $garment]);

        $this->post(route('patterns.compose.store'), [
            'kind' => 'garment',
            'garment' => $garment,
            'base_size' => '40',
            'styles' => array_map(fn (string $key) => ['key' => $key], $styles),
        ])->assertRedirect();

        $pattern = Pattern::latest('id')->firstOrFail();
        $recipe = $pattern->params['compose']['recipe'];

        $this->assertSame('garment', $recipe['base']['kind']);
        $this->assertSame($garment, $recipe['base']['garment']);
        $this->assertSame($styles, array_column($recipe['styles'], 'key'));
        $this->assertNotEmpty($pattern->pieces);
    }

    public function test_a_stored_recipe_reopens_the_studio_ready_to_change(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->actingAsWorkshopUser();

        $selection = ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'lower' => 'skirt_pencil'];
        $styles = $this->styles($selection);

        $this->post(route('patterns.compose.store'), $selection + [
            'kind' => 'blocks',
            'base_size' => '40',
            'styles' => array_map(fn (string $key) => ['key' => $key, 'params' => []], $styles),
        ])->assertRedirect();

        $pattern = Pattern::latest('id')->firstOrFail();

        // بعداً، در یک نشست تازه، همان دستور دوباره باز می‌شود
        $this->flushSession();

        $response = $this->get(route('patterns.compose', ['pattern' => $pattern->id]));

        $response->assertOk()->assertSee('از روی دستور یک الگوی ساخته‌شده باز شد');

        foreach ($styles as $key) {
            $response->assertSee('"key":"'.$key.'"', false);
        }

        $response->assertSee('"bodice":"bodice_block"', false)
            ->assertSee('"lower":"skirt_pencil"', false);
    }

    public function test_a_broken_model_in_the_registry_does_not_break_the_studio(): void
    {
        $this->actingAsWorkshopUser();

        // مدلی که هنگام ساخت بندانگشتی خطا می‌دهد نباید صفحه را از کار بیندازد
        $this->mock(SvgRenderer::class, function ($mock) {
            $mock->shouldReceive('renderPieces')->andThrow(new \RuntimeException('نقشه ساخته نشد'));
        });

        $this->get(route('patterns.compose'))->assertOk()->assertSee('کارگاه دوخت');
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

    /**
     * قد لباس و قد آستین باید جلوی چشم باشند، نه پشتِ «تنظیمات حرفه‌ای».
     *
     * این‌ها همان پارامترهای مدل‌اند، ولی چیزی نیستند که خیاط «اگر لازم شد»
     * بازشان کند؛ همان اول می‌خواهد ببیندشان. پس گام سه شدند و تنظیم‌های ریز
     * (شیب سرشانه، گودی حلقه) سرِ جای خودشان ماندند.
     */
    public function test_the_garment_dimensions_have_their_own_step_apart_from_the_expert_knobs(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->get(route('patterns.compose'))->assertOk();

        $response->assertSee('۳. اندازه‌های لباس')
            ->assertSee('قد لباس، قد آستین')
            ->assertSee('sizeFields(role)', false)
            ->assertSee('fineFields(role)', false);

        // و بخش حرفه‌ای باید بگوید اندازه‌ها جای دیگرند، نه اینکه دوباره بیاوردشان
        $response->assertSee('اندازه‌ها (قد لباس، قد آستین و…) در گام سه‌اند');
    }

    /**
     * گروه‌های سبک باید به ترتیبِ اجرای واقعی بیایند، نه ترتیبِ رجیستری.
     *
     * و آن دو گروهی که با نقش‌های گام یک هم‌نام‌اند باید بگویند فرقشان چیست،
     * وگرنه کاربر فکر می‌کند یک چیز دو جا تکرار شده.
     */
    public function test_the_style_groups_follow_the_order_they_are_applied_in(): void
    {
        $this->actingAsWorkshopUser();

        $html = $this->get(route('patterns.compose'))->assertOk()->getContent();

        $order = app(PatternComposer::class)->styleCatalogue()['style_order'] ?? [];
        $this->assertNotEmpty($order);

        /*
         * جای *برچسب* را نمی‌شود گرفت: برچسب‌ها در بلوکِ دادهٔ بالای صفحه هم
         * هستند و آن‌جا به ترتیبِ رجیستری‌اند. پس روی دکمهٔ بازشوی خودِ گروه
         * می‌ایستیم، که فقط یک بار و دقیقاً همان‌جا که نشان داده می‌شود می‌آید.
         */
        $seen = [];

        foreach ($order as $group) {
            $at = mb_strpos($html, "openGroups['{$group}']");

            if ($at !== false) {
                $seen[$group] = $at;
            }
        }

        $this->assertGreaterThan(4, count($seen), 'دکمه‌های بازشوی گروه‌ها در صفحه پیدا نشد.');
        $positions = array_values($seen);
        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'گروه‌های سبک به ترتیب اجرا در صفحه نیامده‌اند: '.implode('، ', array_keys($seen)));

        // هم‌نامی با گام یک باید توضیح داده شده باشد
        $this->assertStringContainsString('در گام یک گفتید کدام آستین', $html);
        $this->assertStringContainsString('در گام یک گفتید کدام یقهٔ دوخته‌شده', $html);
    }
}
