<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Workshop;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Database\Seeders\GarmentTypeSeeder;
use Database\Seeders\PatternTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternTest extends TestCase
{
    use RefreshDatabase;

    protected function template(string $generator = 'bodice_block'): PatternTemplate
    {
        return PatternTemplate::factory()->generator($generator)->create();
    }

    protected function pattern(array $attributes = []): Pattern
    {
        return app(PatternBuilder::class)->createPattern($this->template(), array_merge([
            'name' => 'بالاتنه آزمون',
            'base_size' => '40',
        ], $attributes), [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);
    }

    public function test_index_lists_patterns_with_thumbnails(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern(['name' => 'پیراهن خانم رضایی']);

        $response = $this->get(route('patterns.index'));

        $response->assertOk()
            ->assertSee('پیراهن خانم رضایی')
            ->assertSee('<svg', false)
            ->assertSee(route('patterns.editor', $pattern), false);
    }

    public function test_index_can_search(): void
    {
        $this->actingAsWorkshopUser();
        $this->pattern(['name' => 'دامن مجلسی']);
        $this->pattern(['name' => 'شلوار راسته']);

        $this->get(route('patterns.index', ['q' => 'دامن']))
            ->assertOk()
            ->assertSee('دامن مجلسی')
            ->assertDontSee('شلوار راسته');
    }

    public function test_create_page_shows_the_template_library(): void
    {
        $this->actingAsWorkshopUser();
        $template = PatternTemplate::factory()->generator('skirt_a_line')->create(['name_fa' => 'دامن A']);

        $this->get(route('patterns.create'))
            ->assertOk()
            ->assertSee('دامن A')
            ->assertSee('تنظیمات حرفه‌ای')
            ->assertSee('value="'.$template->id.'"', false);
    }

    public function test_simple_path_creates_a_pattern_from_template_and_size_only(): void
    {
        $this->actingAsWorkshopUser();
        $template = $this->template();

        $response = $this->post(route('patterns.store'), [
            'pattern_template_id' => $template->id,
            'base_size' => '42',
        ]);

        $pattern = Pattern::latest('id')->first();

        $response->assertRedirect(route('patterns.show', $pattern));
        $this->assertSame($template->name_fa, $pattern->name);
        $this->assertSame('42', $pattern->base_size);
        $this->assertSame(96.0, (float) $pattern->measurements['bust']);
        $this->assertCount(2, $pattern->pieces);
        $this->assertNotEmpty($pattern->seam_allowances);
        $this->assertNotEmpty($pattern->sewing_relations);
        $this->assertSame(1, $pattern->versions()->count());
    }

    public function test_creating_with_a_customer_uses_their_measurements(): void
    {
        $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);
        MeasurementSet::factory()->forSize('46')->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'is_default' => true,
        ]);

        $this->post(route('patterns.store'), [
            'pattern_template_id' => $this->template()->id,
            'customer_id' => $customer->id,
            'name' => 'بالاتنه مشتری',
        ])->assertRedirect();

        $pattern = Pattern::latest('id')->first();

        $this->assertSame('46', $pattern->base_size);
        $this->assertSame(104.0, (float) $pattern->measurements['bust']);
        $this->assertNotNull($pattern->measurement_set_id);
    }

    public function test_store_validates_the_template(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('patterns.store'), ['base_size' => '40'])
            ->assertSessionHasErrors('pattern_template_id');
    }

    public function test_show_displays_pieces_seam_allowances_and_relations(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->get(route('patterns.show', $pattern))
            ->assertOk()
            ->assertSee('بالاتنه جلو')
            ->assertSee('جای دوخت')
            ->assertSee('دوخت مجازی')
            ->assertSee('نقشه الگو')
            ->assertSee('<path', false);
    }

    public function test_update_regenerates_with_new_ease(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $before = $pattern->pieces->first()->width();

        $this->put(route('patterns.update', $pattern), [
            'name' => 'بالاتنه گشاد',
            'base_size' => '40',
            'ease' => ['bust' => 18, 'waist' => 12, 'hip' => 12],
            'regenerate' => 1,
        ])->assertRedirect(route('patterns.show', $pattern));

        $pattern->refresh()->load('pieces');

        $this->assertSame('بالاتنه گشاد', $pattern->name);
        $this->assertGreaterThan($before, $pattern->pieces->first()->width());
        $this->assertSame(2, (int) $pattern->version);
    }

    public function test_update_without_regenerate_only_saves_settings(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $before = $pattern->pieces->first()->width();

        $this->put(route('patterns.update', $pattern), [
            'name' => 'بالاتنه پایه',
            'base_size' => '40',
            'seam_allowances' => ['default' => 1, 'hem' => 4, 'neck' => 0.5, 'side' => 2],
            'regenerate' => 0,
        ])->assertRedirect();

        $pattern->refresh()->load('pieces');
        $piece = $pattern->pieces->first();

        $this->assertSame($before, $piece->width());
        $this->assertSame(4.0, (float) $pattern->seam_allowances['hem']);
        $this->assertSame(0.5, $piece->allowanceFor((int) array_search('neck', $piece->meta['edges'], true)));
    }

    public function test_editor_page_carries_the_geometry_config(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->get(route('patterns.editor', $pattern))
            ->assertOk()
            ->assertSee('patternEditor(', false)
            // @js داده را به شکل JSON.parse با کوتیشن‌های کدشده (\u0022) می‌نویسد
            ->assertSee('\u0022outline\u0022', false)
            ->assertSee('\u0022saveUrl\u0022', false)
            ->assertSee('\u0022bodice-front\u0022', false)
            ->assertSee('geometry', false);
    }

    public function test_update_geometry_saves_points_and_creates_a_version(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces->first();

        $outline = [
            ['x' => 0, 'y' => 0],
            ['x' => 30, 'y' => 0],
            ['x' => 30, 'y' => 40, 'curve' => true, 'cx' => 33, 'cy' => 20],
            ['x' => 0, 'y' => 40],
        ];

        $response = $this->putJson(route('patterns.geometry', $pattern), [
            'pieces' => [[
                'id' => $piece->id,
                'outline' => $outline,
                'edge_allowances' => [0 => 1, 1 => 2, 2 => 3, 3 => 0],
            ]],
        ]);

        $response->assertOk()->assertJson(['status' => 'ok', 'version' => 2, 'pieces' => 1]);

        $piece->refresh();

        $this->assertCount(4, $piece->outline);
        $this->assertSame(30.0, (float) $piece->outline[1]['x']);
        $this->assertTrue($piece->outline[2]['curve']);
        $this->assertSame(2.0, $piece->allowanceFor(1));
        $this->assertSame(1, $pattern->versions()->count());
        $this->assertSame(2, (int) $pattern->fresh()->version);
    }

    public function test_update_geometry_validates_the_outline(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->putJson(route('patterns.geometry', $pattern), [
            'pieces' => [['id' => $pattern->pieces->first()->id, 'outline' => [['x' => 1, 'y' => 1]]]],
        ])->assertStatus(422)->assertJsonValidationErrors('pieces.0.outline');

        $this->putJson(route('patterns.geometry', $pattern), ['pieces' => []])
            ->assertStatus(422)->assertJsonValidationErrors('pieces');
    }

    public function test_duplicate_and_publish(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->post(route('patterns.duplicate', $pattern))->assertRedirect();
        $this->assertSame(2, Pattern::count());

        $this->post(route('patterns.publish', $pattern))->assertRedirect();
        $this->assertTrue($pattern->fresh()->is_published);

        $this->post(route('patterns.publish', $pattern))->assertRedirect();
        $this->assertFalse($pattern->fresh()->is_published);
    }

    public function test_grade_creates_patterns_for_the_requested_sizes(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->post(route('patterns.grade', $pattern), ['sizes' => ['44', '46', '40']])
            ->assertRedirect(route('patterns.index'));

        $this->assertSame(3, Pattern::count()); // مبنا + دو سایز تازه (سایز مبنا دوباره ساخته نمی‌شود)
        $this->assertTrue(Pattern::where('base_size', '44')->exists());
        $this->assertTrue(Pattern::where('base_size', '46')->exists());
    }

    public function test_grade_validates_sizes(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->post(route('patterns.grade', $pattern), ['sizes' => ['99']])
            ->assertSessionHasErrors('sizes.0');
    }

    public function test_destroy_removes_the_pattern(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->delete(route('patterns.destroy', $pattern))->assertRedirect(route('patterns.index'));
        $this->assertSoftDeleted($pattern);
    }

    public function test_another_workshop_pattern_is_not_found(): void
    {
        $other = Workshop::factory()->create();
        $foreign = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $other->id]);

        $this->actingAsWorkshopUser();

        $this->get(route('patterns.show', $foreign))->assertNotFound();
        $this->get(route('patterns.editor', $foreign))->assertNotFound();
        $this->putJson(route('patterns.geometry', $foreign), ['pieces' => []])->assertNotFound();
        $this->post(route('patterns.duplicate', $foreign))->assertNotFound();
        $this->delete(route('patterns.destroy', $foreign))->assertNotFound();
    }

    public function test_seeders_build_the_catalogue_and_the_template_library(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->seed(PatternTemplateSeeder::class);

        $garments = GarmentType::all();
        $this->assertGreaterThanOrEqual(18, $garments->count());

        foreach ($garments as $garment) {
            $this->assertNotEmpty($garment->parts);
            $this->assertSame([], array_diff($garment->parts, array_keys(GarmentType::PART_LABELS)));
            $this->assertArrayHasKey('ideal', $garment->fabric_preferences['drape']);
            $this->assertArrayHasKey('min', $garment->fabric_preferences['weight_gsm']);
            $this->assertArrayHasKey('max', $garment->fabric_preferences['transparency']);
            $this->assertNotEmpty($garment->required_measurements);
        }

        $templates = PatternTemplate::all();
        $this->assertCount(10, $templates);

        foreach ($templates as $template) {
            $this->assertNull($template->workshop_id);
            $this->assertTrue(GeneratorRegistry::has($template->generator));
            $this->assertNotEmpty($template->default_params);
            $this->assertNotEmpty($template->params_schema);
            $this->assertStringContainsString('<svg', (string) $template->preview_svg);
            $this->assertNotEmpty($template->description);
        }

        // سیدرها باید بدون ساختن رکورد تکراری دوباره اجرا شوند
        $this->seed(GarmentTypeSeeder::class);
        $this->seed(PatternTemplateSeeder::class);

        $this->assertSame($garments->count(), GarmentType::count());
        $this->assertSame(10, PatternTemplate::count());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $pattern = Pattern::factory()->create();

        $this->get(route('patterns.index'))->assertRedirect(route('login'));
        $this->get(route('patterns.show', $pattern))->assertRedirect(route('login'));
    }
}
