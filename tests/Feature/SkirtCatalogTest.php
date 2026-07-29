<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ دامن.
 *
 * دامن‌های تازه دو خانواده‌اند و هر کدام جای خطای خودشان را دارند: آن‌ها که با
 * چین و کش کار می‌کنند (پُری ضرب می‌شود و از دست درمی‌رود) و آن‌ها که دو لایه
 * دارند (لایه‌ها باید با هم بخوانند). این آزمون‌ها همان دو جا را می‌پایند.
 */
class SkirtCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete(Measurements::fromSize($size)),
            ['waist' => 3, 'hip' => 5],
            array_merge($generator->defaultParams(), $params),
        );
    }

    protected function piece(array $pieces, string $code): ?array
    {
        foreach ($pieces as $piece) {
            if (str_contains((string) ($piece['code'] ?? ''), $code)) {
                return $piece;
            }
        }

        return null;
    }

    public function test_the_catalogue_grew_without_losing_anything(): void
    {
        $skirts = GeneratorRegistry::group('skirt');

        $this->assertGreaterThanOrEqual(32, count($skirts), 'کاتالوگ دامن باید دست‌کم سی‌ودو مدل داشته باشد.');

        foreach ([
            'skirt_elastic_waist', 'skirt_paperbag', 'skirt_tutu', 'skirt_ball_gown',
            'skirt_train', 'skirt_skort', 'skirt_overlay', 'skirt_cargo',
            // مدل‌های قدیمی نباید از دست رفته باشند
            'skirt_a_line', 'skirt_pencil', 'skirt_tiered', 'skirt_gathered', 'skirt_circle_full',
        ] as $key) {
            $this->assertArrayHasKey($key, $skirts, "«{$key}» در کاتالوگ نیست.");
        }
    }

    public function test_every_skirt_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_keys(GeneratorRegistry::group('skirt')) as $key) {
            foreach (['34', '40', '48', 'کودک', 'بلندقد'] as $size) {
                foreach ($this->build($key, $size) as $piece) {
                    $outline = $piece['outline'] ?? [];

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$key}|{$size}|{$piece['name']} مسیر ندارد.");
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "{$key}|{$size}|{$piece['name']} مسیرش خودش را قطع می‌کند.",
                    );
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  چین و طبقه
     * ------------------------------------------------------------------- */

    public function test_a_tiered_skirt_can_now_go_up_to_eight_tiers(): void
    {
        $pieces = $this->build('skirt_tiered', '40', ['tiers' => 8]);
        $tiers = array_filter($pieces, fn (array $p) => ($p['meta']['part'] ?? '') === 'skirt_tier');

        $this->assertCount(8, $tiers, 'هشت طبقه باید هشت قطعه بدهد.');

        $widths = array_map(fn (array $p) => Geometry::width($p['outline']), array_values($tiers));

        for ($i = 1; $i < count($widths); $i++) {
            $this->assertGreaterThan($widths[$i - 1], $widths[$i], 'هر طبقه باید از طبقهٔ بالای خودش پهن‌تر باشد.');
        }
    }

    public function test_a_deep_stack_of_tiers_warns_about_the_fabric_it_will_eat(): void
    {
        $pieces = $this->build('skirt_tiered', '40', ['tiers' => 8, 'tier_ratio' => 1.6]);
        $notes = implode(' ', $pieces[0]['meta']['notes'] ?? []);

        $this->assertStringContainsString('طبقهٔ آخر', $notes, 'باید دربارهٔ پهنای طبقهٔ آخر هشدار بدهد.');
    }

    public function test_tutu_layers_all_hang_from_the_waist_instead_of_stacking(): void
    {
        $pieces = $this->build('skirt_tutu', '40', ['layers' => 5, 'layer_step' => 4]);
        $layers = array_values(array_filter($pieces, fn (array $p) => ($p['meta']['part'] ?? '') === 'skirt_layer'));

        $this->assertCount(5, $layers);

        // هر لایه از لایهٔ قبلی کوتاه‌تر است، ولی همه یک بالا دارند
        $heights = array_map(fn (array $p) => Geometry::height($p['outline']), $layers);

        for ($i = 1; $i < count($heights); $i++) {
            $this->assertLessThan($heights[$i - 1], $heights[$i], 'هر لایهٔ توتو باید از لایهٔ رویی کوتاه‌تر باشد.');
        }

        foreach ($layers as $layer) {
            $this->assertNotEmpty($layer['meta']['fullness'] ?? [], 'هر لایه باید چین ثبت‌شده داشته باشد.');
            $this->assertSame('waist', ($layer['meta']['edges'] ?? [])[0] ?? null, 'همهٔ لایه‌ها از کمر آویزان‌اند.');
        }
    }

    public function test_a_gathered_skirt_records_the_fabric_it_gathers_away(): void
    {
        foreach (['skirt_elastic_waist', 'skirt_paperbag', 'skirt_tutu'] as $key) {
            $piece = $this->build($key)[0];
            $fullness = $piece['meta']['fullness'][0] ?? null;

            $this->assertNotNull($fullness, "«{$key}» باید چین ثبت‌شده داشته باشد.");
            $this->assertGreaterThan(
                $fullness['finished'],
                $fullness['fabric'],
                "«{$key}»: پارچه باید از اندازهٔ تمام‌شده بیشتر باشد.",
            );
        }
    }

    /**
     * چینِ ثبت‌شده باید در اندازه‌گیری هم دیده شود.
     *
     * meta.fullness را فقط رندر و برگه فنی می‌خوانند؛ هر اندازه‌گیر دیگری در
     * سامانه (PieceOps::seamLength، دوختن دامن به بالاتنه، ممیزی) meta.gathers
     * را می‌بیند. اگر این دو یکی نباشند، دامن چین‌دار پهنای خام پارچه‌اش را
     * اندازهٔ کمر گزارش می‌کند — همان ایرادی که کمر دیرندل را دو برابر نشان می‌داد.
     */
    public function test_a_gathered_waist_measures_the_finished_size_not_the_raw_fabric(): void
    {
        foreach (['skirt_gathered', 'skirt_bubble', 'skirt_tiered', 'skirt_pleat_sunburst'] as $key) {
            $pieces = $this->build($key);
            $raw = 0.0;
            $sewn = 0.0;
            $target = null;

            foreach ($pieces as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['skirt_front', 'skirt_back', 'skirt_panel'], true)) {
                    continue;
                }

                $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
                $edges = Geometry::edgesWithTag($piece, 'waist');

                if ($edges === []) {
                    continue;
                }

                foreach ($edges as $edge) {
                    $raw += Geometry::edgeLength($piece['outline'], $edge) * $repeats;
                }

                $sewn += PieceOps::seamLength($piece, $edges) * $repeats;
                $target ??= (float) ($piece['meta']['waist_target'] ?? 0);
            }

            if ($target === null || $target < 1.0) {
                continue; // طبقه‌ای و آفتابی کمرشان را از پنل بالایی می‌دهند
            }

            $this->assertGreaterThan($target, $raw, "«{$key}» باید پارچهٔ بیشتر از کمر ببرد.");
            $this->assertEqualsWithDelta(
                $target,
                $sewn,
                max(1.0, $target * 0.02),
                "«{$key}»: کمر خام {$raw} است و باید بعد از چین {$target} اندازه گرفته شود، نه {$sewn}.",
            );
        }
    }

    public function test_an_elastic_waist_asks_for_elastic_shorter_than_the_waist(): void
    {
        $pieces = $this->build('skirt_elastic_waist', '40', ['elastic_ratio' => 0.9]);
        $notion = $pieces[0]['meta']['notions'][0] ?? null;

        $this->assertSame('elastic', $notion['type'] ?? null);

        $waist = (float) ($pieces[0]['meta']['waist_target'] ?? 0);
        $this->assertGreaterThan(0, $waist);
        $this->assertLessThan($waist, (float) $notion['length'], 'کش باید کوتاه‌تر از دور کمر بریده شود.');
    }

    /* ---------------------------------------------------------------------
     |  دو لایه و دنباله
     * ------------------------------------------------------------------- */

    public function test_a_skort_hides_its_shorts_under_the_skirt(): void
    {
        $pieces = $this->build('skirt_skort', '40', ['short_gap' => 5]);
        $inner = array_values(array_filter($pieces, fn (array $p) => ! empty($p['meta']['inner_short'])));

        $this->assertNotEmpty($inner, 'اسکورت باید شلوارک زیر داشته باشد.');

        $skirt = array_values(array_filter(
            $pieces,
            fn (array $p) => in_array($p['meta']['part'] ?? '', ['skirt_front', 'skirt_back', 'skirt_panel'], true),
        ));

        $skirtLength = max(array_map(fn (array $p) => Geometry::height($p['outline']), $skirt));
        $shortLength = max(array_map(fn (array $p) => Geometry::height($p['outline']), $inner));

        $this->assertLessThan($skirtLength, $shortLength, 'شلوارک زیر باید کوتاه‌تر از دامن باشد.');
    }

    public function test_an_overlay_is_never_the_same_length_as_the_skirt_under_it(): void
    {
        $pieces = $this->build('skirt_overlay', '40', ['overlay_length' => 0]);

        $under = $this->piece($pieces, 'under-back');
        $over = $this->piece($pieces, 'overlay-back');

        $this->assertNotNull($under);
        $this->assertNotNull($over);

        $this->assertGreaterThan(
            3,
            abs(Geometry::height($over['outline']) - Geometry::height($under['outline'])),
            'دو لایه نباید هم‌قد باشند، وگرنه از دور یک دامن دیده می‌شوند.',
        );
    }

    public function test_a_train_only_lengthens_the_centre_back_not_the_side_seam(): void
    {
        $pieces = $this->build('skirt_train', '40', ['train' => 80]);

        $front = $this->piece($pieces, 'train-front');
        $back = $this->piece($pieces, 'train-back');

        $sideOf = function (array $piece): float {
            $length = 0.0;

            foreach (Geometry::edgesWithTag($piece, 'side') as $edge) {
                $length += Geometry::edgeLength($piece['outline'], $edge);
            }

            return $length;
        };

        $this->assertEqualsWithDelta(
            $sideOf($front),
            $sideOf($back),
            1.0,
            'دنباله نباید درز پهلو را بلند کند؛ وگرنه جلو و پشت به هم نمی‌رسند.',
        );

        $this->assertGreaterThan(
            Geometry::height($front['outline']) + 60,
            Geometry::height($back['outline']),
            'مرکز پشت باید به اندازهٔ دنباله بلندتر باشد.',
        );
    }

    public function test_a_ball_gown_builds_a_petticoat_that_gets_fuller_going_down(): void
    {
        $pieces = $this->build('skirt_ball_gown', '40', ['petticoat_tiers' => 3]);
        $tiers = array_values(array_filter(
            $pieces,
            fn (array $p) => str_contains((string) ($p['code'] ?? ''), 'petticoat'),
        ));

        $this->assertCount(3, $tiers, 'زیردامن باید سه طبقه باشد.');

        $widths = array_map(fn (array $p) => Geometry::width($p['outline']), $tiers);

        for ($i = 1; $i < count($widths); $i++) {
            $this->assertGreaterThan($widths[$i - 1], $widths[$i], 'هر طبقهٔ زیردامن از طبقهٔ بالا پُرتر است.');
        }
    }
}
