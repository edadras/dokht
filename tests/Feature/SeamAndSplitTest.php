<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\Style\StyleRegistry;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * برش: هم سبک‌های آماده (یوک، پنل، کالربلاک، مورب) و هم برش دستی در ویرایشگر.
 *
 * تا پیش از این هیچ سبکی در سامانه درز تازه نمی‌ساخت؛ این آزمون‌ها مواظب‌اند که
 * برش، لباس را نادوختنی نکند: مساحت پارچه نه کم شود نه زیاد، مسیر قطعه‌ها سالم
 * بماند، و هر درز تازه دو سر جفت‌شده داشته باشد.
 */
class SeamAndSplitTest extends TestCase
{
    use RefreshDatabase;

    protected const SEAMS = ['seam_yoke', 'seam_panel', 'seam_block', 'seam_diagonal'];

    /** @return array<int, array<string, mixed>> */
    protected function garment(string $key): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete(Measurements::fromSize('40')),
            ['bust' => 6, 'waist' => 4, 'hip' => 5, 'neck' => 1, 'bicep' => 4, 'thigh' => 4],
            $generator->defaultParams(),
        );
    }

    protected function context(array $style): array
    {
        return [
            'measurements' => Measurements::complete(Measurements::fromSize('40')),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 5],
            'params' => $style,
            'garment' => 'test',
        ];
    }

    protected function totalArea(array $pieces): float
    {
        return round(array_sum(array_map(
            fn (array $piece) => abs(Geometry::area($piece['outline'] ?? [])),
            $pieces,
        )), 1);
    }

    /* ---------------------------------------------------------------------
     |  سبک‌های برش
     * ------------------------------------------------------------------- */

    public function test_the_seam_group_is_registered_and_ordered_before_pockets(): void
    {
        $this->assertArrayHasKey('seam', StyleRegistry::GROUPS);

        $order = PatternComposer::STYLE_ORDER;

        $this->assertContains('seam', $order);
        $this->assertLessThan(array_search('pocket', $order, true), array_search('seam', $order, true));
    }

    public function test_a_yoke_can_finally_be_cut_into_a_pair_of_trousers(): void
    {
        $base = $this->garment('pants_straight');
        $style = StyleRegistry::make('seam_yoke');
        $context = $this->context($style->defaultParams());

        $this->assertTrue($style->supports($base, $context));

        $result = $style->apply($base, $context);

        $this->assertCount(count($base) + 1, $result['pieces'], 'یوک باید یک قطعه به شلوار اضافه کند.');
        $this->assertEqualsWithDelta(
            $this->totalArea($base),
            $this->totalArea($result['pieces']),
            1.0,
            'برش نه پارچه می‌سازد نه می‌خورد.',
        );

        $yoke = collect($result['pieces'])->firstWhere('meta.part', 'back_leg_yoke');
        $this->assertNotNull($yoke, 'قطعهٔ یوک باید نام مشتق خودش را بگیرد.');
    }

    public function test_every_seam_style_keeps_the_geometry_sound(): void
    {
        foreach (['pants_straight', 'blazer', 'shirt_classic'] as $garment) {
            $base = $this->garment($garment);

            foreach (static::SEAMS as $key) {
                $style = StyleRegistry::make($key);
                $context = $this->context($style->defaultParams());

                if ($style->supports($base, $context) !== true) {
                    continue;
                }

                $pieces = $style->apply($base, $context)['pieces'];

                // رواداری از گرد کردن مختصات به دو رقم می‌آید، نه از خودِ برش
                $this->assertEqualsWithDelta(
                    $this->totalArea($base),
                    $this->totalArea($pieces),
                    max(1.0, $this->totalArea($base) * 0.0005),
                    "«{$key}» روی «{$garment}» مساحت پارچه را عوض کرد.",
                );

                foreach ($pieces as $piece) {
                    $outline = $piece['outline'] ?? [];

                    $this->assertGreaterThanOrEqual(3, count($outline), $piece['name'].' مسیر ندارد.');
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "«{$key}» روی «{$garment}» مسیر «{$piece['name']}» را قطع کرد.",
                    );
                    $this->assertCount(
                        count($outline),
                        $piece['meta']['edges'] ?? [],
                        "برچسب لبه‌های «{$piece['name']}» با نقطه‌هایش نمی‌خواند.",
                    );
                }
            }
        }
    }

    public function test_a_new_seam_carries_paired_notches_on_both_sides(): void
    {
        $base = $this->garment('blazer');
        $style = StyleRegistry::make('seam_block');
        $pieces = $style->apply($base, $this->context($style->defaultParams()))['pieces'];

        $pairs = [];

        foreach ($pieces as $piece) {
            foreach ($piece['notches'] ?? [] as $notch) {
                if (str_starts_with((string) ($notch['pair'] ?? ''), 'block-')) {
                    $pairs[$notch['pair']] = ($pairs[$notch['pair']] ?? 0) + 1;
                }
            }
        }

        $this->assertNotEmpty($pairs, 'درز تازه باید نشانهٔ جفت داشته باشد.');

        foreach ($pairs as $key => $count) {
            $this->assertGreaterThanOrEqual(2, $count, "نشانهٔ «{$key}» جفت ندارد.");
        }
    }

    public function test_a_lengthwise_cut_is_refused_on_a_piece_that_sits_on_the_fold(): void
    {
        $base = $this->garment('skirt_a_line');
        $style = StyleRegistry::make('seam_panel');

        $reason = $style->supports($base, $this->context($style->defaultParams()));

        $this->assertIsString($reason, 'برش طولی روی قطعهٔ روی تا نباید پذیرفته شود.');
        $this->assertStringContainsString('تای پارچه', $reason);
    }

    public function test_a_diagonal_cut_refuses_to_be_a_horizontal_one(): void
    {
        $style = StyleRegistry::make('seam_diagonal');
        $params = array_merge($style->defaultParams(), ['start' => 50, 'end' => 51]);

        $reason = $style->supports($this->garment('blazer'), $this->context($params));

        $this->assertIsString($reason);
        $this->assertStringContainsString('برش عرضی', $reason);
    }

    public function test_a_seam_style_runs_through_the_whole_studio(): void
    {
        $this->actingAsWorkshopUser();

        $selection = [
            'bodice' => 'bodice_block',
            'sleeve' => 'none',
            'lower' => 'pants_straight',
            'collar' => 'none',
            'base_size' => '40',
            'styles' => [['key' => 'seam_yoke', 'params' => ['where' => 'back', 'depth' => 8]]],
        ];

        $result = app(PatternComposer::class)->compose($selection, Measurements::fromSize('40'));

        $yoke = collect($result['pieces'])->first(
            fn (array $piece) => str_contains((string) ($piece['meta']['part'] ?? ''), '_yoke'),
        );

        $this->assertNotNull($yoke, 'یوک باید در خروجی استودیو باشد.');
        $this->assertFalse(Geometry::selfIntersects($yoke['outline']));

        // جای دوخت هر قطعه پس از برش هم باید ساخته شده باشد
        foreach ($result['pieces'] as $piece) {
            $this->assertNotEmpty($piece['edge_allowances'] ?? [], "«{$piece['name']}» جای دوخت ندارد.");
        }
    }

    /* ---------------------------------------------------------------------
     |  برش دستی در ویرایشگر
     * ------------------------------------------------------------------- */

    protected function pattern(): Pattern
    {
        return Pattern::factory()->withSimplePieces()->create([
            'workshop_id' => auth()->user()->workshop_id,
            'seam_allowances' => ['default' => 1, 'hem' => 4],
        ]);
    }

    public function test_a_drawn_line_splits_a_piece_into_two(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces()->firstOrFail();
        $version = $pattern->version;

        $response = $this->actingAs($user)->postJson(
            route('patterns.pieces.split', [$pattern, $piece]),
            ['path' => [
                ['x' => -2, 'y' => 20],
                ['x' => 24, 'y' => 28],
                ['x' => 50, 'y' => 34],
            ]],
        );

        $response->assertOk()->assertJsonPath('status', 'ok');

        $pattern->refresh()->load('pieces');

        $this->assertCount(3, $pattern->pieces, 'یک قطعه باید به دو قطعه شده باشد.');
        $this->assertGreaterThan($version, $pattern->version, 'پیش از برش باید نسخه ثبت شود.');
        $this->assertDatabaseHas('pattern_versions', ['pattern_id' => $pattern->id]);

        $halves = $pattern->pieces->where('meta.hand_cut', true);
        $this->assertCount(2, $halves);

        foreach ($halves as $half) {
            $this->assertNotEmpty($half->meta['cut_edges'] ?? [], 'لبهٔ برش باید ثبت شده باشد.');
            $this->assertFalse(Geometry::selfIntersects($half->outline), 'مسیر نیمه‌ها باید سالم باشد.');
        }
    }

    public function test_the_two_halves_together_hold_the_same_fabric_as_the_whole(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces()->firstOrFail();
        $before = abs(Geometry::area($piece->outline));

        $this->actingAs($user)->postJson(route('patterns.pieces.split', [$pattern, $piece]), [
            'path' => [['x' => -1, 'y' => 15], ['x' => 49, 'y' => 40]],
        ])->assertOk();

        $after = $pattern->fresh()->pieces->where('meta.hand_cut', true)
            ->sum(fn ($half) => abs(Geometry::area($half->outline)));

        $this->assertEqualsWithDelta($before, $after, 1.0, 'دو نیمه روی هم باید همان‌قدر پارچه باشند.');
    }

    public function test_seam_allowances_are_rebuilt_from_the_edge_tags_not_copied_by_number(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces()->firstOrFail();

        // لبهٔ پایین «دم» است و در تنظیم الگو ۴ سانتی‌متر جای دوخت دارد
        $piece->update(['meta' => array_merge($piece->meta ?? [], [
            'edges' => ['shoulder', 'side', 'hem', 'side'],
        ])]);

        $this->actingAs($user)->postJson(route('patterns.pieces.split', [$pattern, $piece]), [
            'path' => [['x' => -1, 'y' => 30], ['x' => 49, 'y' => 30]],
        ])->assertOk();

        foreach ($pattern->fresh()->pieces->where('meta.hand_cut', true) as $half) {
            $tags = $half->meta['edges'] ?? [];

            foreach ($tags as $index => $tag) {
                $expected = $tag === 'hem' ? 4.0 : 1.0;

                $this->assertEqualsWithDelta(
                    $expected,
                    (float) ($half->edge_allowances[$index] ?? $half->edge_allowances[(string) $index] ?? 0),
                    0.01,
                    "جای دوخت لبهٔ «{$tag}» درست نیست.",
                );
            }
        }
    }

    public function test_a_line_that_would_leave_nothing_behind_is_refused_with_a_reason(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces()->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('patterns.pieces.split', [$pattern, $piece]), [
            'path' => [['x' => 0, 'y' => 0.2], ['x' => 0.2, 'y' => 0]],
        ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('message'));
        $this->assertCount(2, $pattern->fresh()->pieces, 'برش ناموفق نباید قطعه‌ای بسازد.');
    }

    public function test_a_failed_cut_leaves_no_version_behind(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $version = $pattern->version;

        $this->actingAs($user)->postJson(
            route('patterns.pieces.split', [$pattern, $pattern->pieces()->firstOrFail()]),
            ['path' => [['x' => 0, 'y' => 0.2], ['x' => 0.2, 'y' => 0]]],
        )->assertStatus(422);

        $this->assertSame($version, $pattern->fresh()->version, 'برشی که انجام نشد نباید نسخه بسازد.');
        $this->assertDatabaseCount('pattern_versions', 0);
    }

    public function test_a_line_that_crosses_the_outline_is_refused_before_anything_is_saved(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces()->firstOrFail();

        // نقطهٔ میانی بیرون قطعه: مسیر روی خودش برمی‌گردد
        $response = $this->actingAs($user)->postJson(route('patterns.pieces.split', [$pattern, $piece]), [
            'path' => [['x' => -1, 'y' => 20], ['x' => -30, 'y' => 30], ['x' => 49, 'y' => 40]],
        ]);

        $response->assertStatus(422);
        $this->assertCount(2, $pattern->fresh()->pieces);
    }

    public function test_one_point_is_not_a_line(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->actingAs($user)->postJson(
            route('patterns.pieces.split', [$pattern, $pattern->pieces()->firstOrFail()]),
            ['path' => [['x' => 5, 'y' => 5]]],
        )->assertStatus(422);
    }

    public function test_a_piece_of_another_pattern_cannot_be_cut(): void
    {
        $user = $this->actingAsWorkshopUser();
        $mine = $this->pattern();
        $other = $this->pattern();

        $this->actingAs($user)->postJson(
            route('patterns.pieces.split', [$mine, $other->pieces()->firstOrFail()]),
            ['path' => [['x' => -1, 'y' => 20], ['x' => 49, 'y' => 20]]],
        )->assertNotFound();
    }

    public function test_the_editor_hands_the_split_address_to_the_browser(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->actingAs($user)->get(route('patterns.editor', $pattern))
            ->assertOk()
            ->assertSee('splitUrl', false)
            ->assertSee('برش دلخواه');
    }
}
