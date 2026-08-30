<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\StitchPlanService;
use App\Support\FabricProfile;
use App\Support\Measurements;
use App\Support\Stitches;
use Tests\TestCase;

/**
 * نقشهٔ دوخت باید حرفِ خیاط را بزند، نه یک جدولِ ثابت.
 *
 * سنجهٔ درستش این است: همان الگو با پارچهٔ دیگر، دستورِ دیگری بگیرد. اگر
 * پیراهنِ نخی و پیراهنِ ژرسه یک نسخه بگیرند، این نقشه چیزی به کسی یاد نمی‌دهد.
 */
class StitchPlanTest extends TestCase
{
    protected function pattern(string $key = 'shirt_classic'): Pattern
    {
        $generator = GeneratorRegistry::make($key);
        $body = Measurements::fromSize('40');
        $pieces = $generator->generate($body, [], $generator->defaultParams());

        $pattern = new Pattern(['name' => $key, 'measurements' => $body]);
        $pattern->setRelation('pieces', collect($pieces)->values()->map(function (array $piece, int $i) {
            $model = new PatternPiece;
            $model->code = (string) ($piece['code'] ?? 'piece-'.$i);
            $model->outline = $piece['outline'];
            $model->meta = $piece['meta'] ?? [];
            $model->layer = (string) ($piece['layer'] ?? 'outer');
            $model->cut_quantity = (int) ($piece['cut_quantity'] ?? 1);

            return $model;
        }));

        return $pattern;
    }

    protected function plan(array $fabric = [], string $key = 'shirt_classic'): array
    {
        return (new StitchPlanService)->plan($this->pattern($key), FabricProfile::make($fabric));
    }

    /** @return array<string, array<string, mixed>> */
    protected function byTag(array $plan): array
    {
        $out = [];

        foreach ($plan['edges'] as $edge) {
            $out[$edge['tag']] = $edge;
        }

        return $out;
    }

    public function test_every_stitch_has_a_name_a_purpose_and_a_length(): void
    {
        foreach (Stitches::all() as $family => $list) {
            $this->assertNotEmpty($list, "فهرست «{$family}» خالی است.");

            foreach ($list as $key => $stitch) {
                $this->assertArrayHasKey('name', $stitch, "«{$key}» نام ندارد.");
                $this->assertArrayHasKey('purpose', $stitch, "«{$key}» کارش نوشته نشده.");
                $this->assertCount(3, $stitch['length'] ?? [], "«{$key}» بازهٔ فاصله ندارد.");

                [$min, $best, $max] = $stitch['length'];

                $this->assertLessThanOrEqual($best, $min, "«{$key}»: کمینه از پیشنهادی بیشتر است.");
                $this->assertLessThanOrEqual($max, $best, "«{$key}»: پیشنهادی از بیشینه بیشتر است.");
                $this->assertGreaterThan(0, $min, "«{$key}»: فاصلهٔ صفر یعنی سوزن جا نمی‌رود.");
            }
        }
    }

    /** هر درزی که در قاعده‌ها نام برده شده باید در فهرست درزها باشد. */
    public function test_the_rules_only_name_stitches_and_seams_that_exist(): void
    {
        foreach (Stitches::PLACES as $tag => $place) {
            foreach ($place as $key => $recipe) {
                if (! is_array($recipe) || ! isset($recipe['seam'])) {
                    continue;
                }

                $this->assertArrayHasKey(
                    $recipe['seam'],
                    Stitches::SEAMS,
                    "«{$tag}/{$key}» درزی می‌خواهد که تعریف نشده: {$recipe['seam']}",
                );
                $this->assertNotNull(
                    Stitches::stitch($recipe['stitch']),
                    "«{$tag}/{$key}» کوکی می‌خواهد که تعریف نشده: {$recipe['stitch']}",
                );
            }

            if (! empty($place['before'])) {
                $this->assertNotNull(Stitches::stitch($place['before']), "«{$tag}» پیش‌دوختِ ناشناخته دارد.");
            }
        }
    }

    /**
     * پارچهٔ سنگین‌تر، کوکِ درشت‌تر.
     *
     * روی حریر کوکِ درشت پارچه را موج می‌اندازد و روی پالتویی کوکِ ریز درز را
     * سفت و شکننده می‌کند. این همان چیزی است که در هر کتاب خیاطی نوشته شده.
     */
    public function test_stitch_length_follows_fabric_weight(): void
    {
        $light = Stitches::length('lock', 70);
        $medium = Stitches::length('lock', 200);
        $heavy = Stitches::length('lock', 500);

        $this->assertLessThan($medium, $light, 'حریر باید کوکِ ریزتر از نخی بگیرد.');
        $this->assertLessThan($heavy, $medium, 'پالتویی باید کوکِ درشت‌تر از نخی بگیرد.');
        $this->assertEqualsWithDelta(1.8, $light, 0.31);
        $this->assertEqualsWithDelta(3.5, $heavy, 0.51);
    }

    /** رودوزی همیشه از درزدوزی بلندتر است، وگرنه دیده نمی‌شود. */
    public function test_topstitch_is_always_longer_than_the_construction_stitch(): void
    {
        foreach ([70, 150, 300, 600] as $gsm) {
            $this->assertGreaterThan(
                Stitches::length('lock', $gsm),
                Stitches::length('topstitch', $gsm),
                "با {$gsm} گرم، رودوزی از درزدوزی بلندتر نشد.",
            );
        }
    }

    /** و مگسی همیشه ریز می‌ماند، هر پارچه‌ای که باشد. */
    public function test_a_bartack_stays_dense_on_any_fabric(): void
    {
        foreach ([70, 300, 800] as $gsm) {
            $this->assertLessThanOrEqual(0.6, Stitches::length('bartack', $gsm));
        }
    }

    public function test_stitches_per_inch_matches_the_millimetres(): void
    {
        $this->assertSame(10, Stitches::perInch(2.5));
        $this->assertSame(13, Stitches::perInch(2.0));
        $this->assertSame(0, Stitches::perInch(0));
    }

    /** ژرسه سردوز می‌خواهد، نه راسته — راسته درزِ کشباف را می‌شکند. */
    public function test_a_knit_gets_an_overlock_and_a_ballpoint_needle(): void
    {
        $plan = $this->plan(['stretch_warp' => 40, 'stretch_weft' => 60]);
        $edges = $this->byTag($plan);

        $this->assertSame('knit', $plan['fabric']['family']);
        $this->assertStringContainsString('سرگرد', $plan['fabric']['needle_kind']);
        $this->assertSame('overlock4', $edges['side']['stitch']);
        $this->assertSame('cover_hem', $edges['hem']['seam']);
        $this->assertSame('taped', $edges['shoulder']['seam'], 'سرشانهٔ ژرسه بی نوارِ تثبیت کش می‌آید.');
    }

    /** پارچهٔ شفاف، درزِ فرانسوی می‌خواهد: لبهٔ خام از رو دیده می‌شود. */
    public function test_a_sheer_fabric_gets_a_french_seam(): void
    {
        $plan = $this->plan(['transparency' => 0.8, 'weight_gsm' => 60]);
        $edges = $this->byTag($plan);

        $this->assertSame('sheer', $plan['fabric']['family']);
        $this->assertSame('french', $edges['side']['seam']);
        $this->assertNotEmpty($edges['side']['note'] ?? null, 'محدودیتِ درز فرانسوی باید گفته شود.');
    }

    /** پیراهنِ تخت، درزِ انگلیسی می‌گیرد — هر دو رویش تمیز است. */
    public function test_a_woven_shirt_gets_a_flat_felled_side_seam(): void
    {
        $edges = $this->byTag($this->plan(['weight_gsm' => 140]));

        $this->assertSame('flat_fell', $edges['side']['seam']);
    }

    /** یقه و حلقه اریب‌اند و باید همان اول تثبیت شوند. */
    public function test_bias_edges_are_staystitched_first(): void
    {
        $edges = $this->byTag($this->plan(['weight_gsm' => 140]));

        $this->assertSame('stay', $edges['neck']['before']['stitch']);
        $this->assertSame('stay', $edges['armhole']['before']['stitch']);
        $this->assertSame('understitch', $edges['neck']['after']['stitch'], 'سجافِ یقه باید زیردوزی شود.');
    }

    /** جای دوختی که الگو با آن بریده شده حرفِ آخر را می‌زند، نه پیشنهادِ درز. */
    public function test_the_pattern_own_seam_allowance_wins(): void
    {
        $pattern = $this->pattern();
        $pattern->seam_allowances = ['side' => 2.2, 'default' => 1.1];

        $plan = (new StitchPlanService)->plan($pattern, FabricProfile::make(['weight_gsm' => 140]));
        $edges = $this->byTag($plan);

        $this->assertSame(2.2, $edges['side']['allowance_cm']);
        $this->assertSame(1.1, $edges['hem']['allowance_cm'], 'لبه‌ای که نامش نیامده، پیش‌فرضِ الگو را می‌گیرد.');
    }

    /** لبه‌های واقعیِ همین الگو، با طولِ واقعیِ خودشان. */
    public function test_the_plan_covers_the_edges_this_pattern_actually_has(): void
    {
        $plan = $this->plan(['weight_gsm' => 140]);
        $edges = $this->byTag($plan);

        foreach (['neck', 'shoulder', 'armhole', 'side', 'hem'] as $tag) {
            $this->assertArrayHasKey($tag, $edges, "لبهٔ «{$tag}» در نقشه نیست.");
            $this->assertGreaterThan(0, $edges[$tag]['total_cm'], "طولِ «{$tag}» صفر است.");
        }

        // ترتیب از بالای لباس به پایین است، نه الفبایی
        $order = array_column($plan['edges'], 'tag');

        $this->assertLessThan(
            array_search('hem', $order, true),
            array_search('neck', $order, true),
            'یقه باید پیش از دم بیاید.',
        );
    }

    /** پارچهٔ لغزنده را باید اول شلال کرد؛ سنجاق کافی نیست. */
    public function test_a_slippery_fabric_asks_for_hand_basting(): void
    {
        $plan = $this->plan(['slippage' => 0.8, 'weight_gsm' => 90]);
        $keys = array_column($plan['hand'], 'stitch');

        $this->assertContains('baste', $keys);
    }

    public function test_fabric_quirks_turn_into_plain_warnings(): void
    {
        $notes = implode(' ', $this->plan(['fraying' => 0.8, 'curling' => 0.7])['notes']);

        $this->assertStringContainsString('ریش', $notes);
        $this->assertStringContainsString('لوله', $notes);
    }
}
