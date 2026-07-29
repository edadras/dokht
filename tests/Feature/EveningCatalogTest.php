<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bodies;
use Tests\TestCase;

/**
 * کاتالوگ لباس شب، مجلسی و عروس.
 *
 * جای خطای این خانواده یک جاست و همه‌جا همان است: خط کمر. بالاتنه و دامن جدا
 * درفت می‌شوند و اگر دو کمر به هم نرسند، لباس روی کاغذ درست است و روی پارچه
 * دوخته نمی‌شود. بیشتر این آزمون‌ها همان یک نقطه را می‌پایند.
 */
class EveningCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Bodies::body($size),
            [],
            array_merge($generator->defaultParams(), $params),
        );
    }

    protected function notes(array $pieces): string
    {
        $notes = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notes'] ?? [] as $note) {
                $notes[] = $note;
            }
        }

        return implode(' | ', $notes);
    }

    /** دور یک برچسب لبه، با احتساب ساسون، چین و تای پارچه. */
    protected function girth(array $pieces, string $tag, array $parts): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if (! in_array($piece['meta']['part'] ?? '', $parts, true)) {
                continue;
            }

            $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));

            foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
                $length = Geometry::edgeLength($piece['outline'], $edge);

                foreach ($piece['darts'] ?? [] as $dart) {
                    $dartEdge = $dart['edge'] ?? null;

                    if ($dartEdge === null || (int) $dartEdge === $edge) {
                        $length -= (float) ($dart['intake'] ?? 0);
                    }
                }

                foreach ($piece['meta']['gathers'] ?? [] as $gather) {
                    if ((int) ($gather['edge'] ?? -1) === $edge) {
                        $length -= (float) ($gather['amount'] ?? 0);
                    }
                }

                $total += max(0.0, $length) * $repeats;
            }
        }

        return round($total, 2);
    }

    public function test_the_family_covers_evening_party_and_bridal(): void
    {
        $this->assertArrayHasKey('evening', GeneratorRegistry::GROUPS);

        $gowns = GeneratorRegistry::group('evening');

        $this->assertGreaterThanOrEqual(12, count($gowns), 'کاتالوگ لباس شب باید دست‌کم دوازده مدل داشته باشد.');

        foreach ([
            // شب و مجلسی
            'evening_column', 'evening_a_line', 'evening_empire', 'evening_ball_gown',
            'evening_mermaid', 'evening_slip', 'evening_two_piece',
            // عقد و عروسی
            'bridal_princess', 'bridal_a_line', 'bridal_sheath', 'engagement_dress', 'bridal_veil',
        ] as $key) {
            $this->assertArrayHasKey($key, $gowns, "«{$key}» در کاتالوگ نیست.");
        }
    }

    public function test_every_gown_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_keys(GeneratorRegistry::group('evening')) as $key) {
            foreach (['34', '40', '48', 'کودک', 'بلندقد'] as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
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
     |  خط کمر: جایی که این لباس‌ها می‌شکنند
     * ------------------------------------------------------------------- */

    /**
     * هر لباسی که بالاتنه و دامنش جدا درفت می‌شوند، باید در کمر جفت باشد.
     *
     * این آزمون عمداً روی کل خانواده می‌چرخد، نه چند مدل دستچین‌شده: با فهرست
     * دستچین، لباس پرنسسی و بالماسکه از قلم افتاده بودند و کمرِ دامنشان ۳۳
     * سانتی‌متر از بالاتنه کوچک‌تر بود، چون زیردامنی هم جزو کمر دامن شمرده
     * می‌شد و اختلافِ خیالی روی لایهٔ رو چین می‌خورد.
     */
    public function test_the_bodice_waist_and_the_skirt_waist_meet(): void
    {
        foreach (array_keys(GeneratorRegistry::group('evening')) as $key) {
            $pieces = array_filter(
                $this->build($key),
                fn (array $p) => ($p['meta']['girth_role'] ?? 'shell') === 'shell'
                    && ($p['layer'] ?? 'outer') !== 'lining',
            );

            $bodice = $this->girth($pieces, 'waist', ['front_bodice', 'back_bodice']);
            $skirt = $this->girth($pieces, 'waist', ['skirt_front', 'skirt_back', 'skirt_panel']);

            if ($bodice <= 0) {
                continue; // تور عروس بالاتنه ندارد
            }

            if ($skirt <= 0) {
                continue; // دامن ترک‌دار کمرش را از پنل‌ها می‌دهد، نه از یک لبه
            }

            $this->assertEqualsWithDelta(
                $bodice,
                $skirt,
                max(1.5, $bodice * 0.03),
                "«{$key}»: کمر بالاتنه {$bodice} و کمر دامن {$skirt}؛ این دو به هم دوخته می‌شوند.",
            );
        }
    }

    public function test_the_pattern_says_out_loud_how_the_two_waists_were_joined(): void
    {
        foreach (['evening_column', 'evening_a_line', 'evening_ball_gown'] as $key) {
            $notes = $this->notes($this->build($key));

            $this->assertMatchesRegularExpression(
                '/(مستقیم به هم دوخته می‌شوند|چین داده می‌شود|به هم نمی‌رسند)/u',
                $notes,
                "«{$key}» باید بگوید کمر دامن و بالاتنه چطور به هم می‌رسند.",
            );
        }
    }

    public function test_a_fuller_skirt_is_gathered_onto_the_bodice_not_left_mismatched(): void
    {
        $pieces = $this->build('evening_empire');
        $notes = $this->notes($pieces);

        $this->assertStringNotContainsString('به هم نمی‌رسند', $notes, 'کمر امپایر نباید نامتعادل بماند.');
    }

    /* ---------------------------------------------------------------------
     |  آنچه لباس مجلسی بدون آن دوخته نمی‌شود
     * ------------------------------------------------------------------- */

    public function test_every_gown_has_a_back_closure_long_enough_to_clear_the_hip(): void
    {
        $body = Measurements::complete(Measurements::fromSize('40'));

        foreach (['evening_column', 'evening_a_line', 'evening_mermaid', 'bridal_sheath'] as $key) {
            $zip = null;

            foreach ($this->build($key) as $piece) {
                foreach ($piece['meta']['notions'] ?? [] as $notion) {
                    if (($notion['type'] ?? '') === 'zip') {
                        $zip = (float) $notion['length'];
                    }
                }
            }

            $this->assertNotNull($zip, "«{$key}» باید بست پشت داشته باشد.");
            $this->assertGreaterThan(
                (float) $body['waist_to_hip'],
                $zip,
                "زیپ «{$key}» باید از باسن رد شود، وگرنه لباس پوشیده نمی‌شود.",
            );
        }
    }

    public function test_a_gown_is_lined_unless_it_says_otherwise(): void
    {
        $lined = array_filter($this->build('evening_a_line'), fn (array $p) => ($p['meta']['part'] ?? '') === 'lining');
        $this->assertNotEmpty($lined, 'لباس مجلسی پیش‌فرض آستر دارد.');

        $bare = array_filter(
            $this->build('evening_a_line', '40', ['lining' => 'none']),
            fn (array $p) => ($p['meta']['part'] ?? '') === 'lining',
        );
        $this->assertEmpty($bare);

        $this->assertStringContainsString(
            'آستر ندارد',
            $this->notes($this->build('evening_a_line', '40', ['lining' => 'none'])),
            'لباس بی‌آستر باید هشدارش را بدهد.',
        );
    }

    public function test_a_strapless_bodice_gets_boning(): void
    {
        foreach ($this->build('evening_ball_gown', '40', ['neckline' => 'sweetheart', 'boning' => true]) as $piece) {
            if (in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                $this->assertTrue((bool) ($piece['meta']['boning'] ?? false), 'بالاتنهٔ بی‌بند تیغهٔ فنر می‌خواهد.');
            }
        }
    }

    public function test_a_strapless_bodice_has_no_armhole_but_a_strapped_one_does(): void
    {
        $strapless = $this->build('evening_column', '40', ['neckline' => 'straight']);
        $strapped = $this->build('evening_column', '40', ['neckline' => 'strap']);

        $armholeOf = function (array $pieces): int {
            $count = 0;

            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'front_bodice') {
                    $count += count(Geometry::edgesWithTag($piece, 'armhole'));
                }
            }

            return $count;
        };

        $this->assertSame(0, $armholeOf($strapless));
        $this->assertGreaterThan(0, $armholeOf($strapped));
    }

    /* ---------------------------------------------------------------------
     |  عروس
     * ------------------------------------------------------------------- */

    public function test_a_bridal_train_is_not_mistaken_for_the_skirt_back(): void
    {
        $pieces = $this->build('bridal_princess', '40', ['train' => 120]);
        $train = array_values(array_filter($pieces, fn (array $p) => ($p['meta']['part'] ?? '') === 'train'));

        $this->assertNotEmpty($train, 'لباس عروس پرنسسی باید دنباله داشته باشد.');
        $this->assertSame(120.0, (float) $train[0]['meta']['train']);
    }

    public function test_a_bridal_gown_carries_an_inner_waist_stay(): void
    {
        $stay = array_filter(
            $this->build('bridal_princess'),
            fn (array $p) => str_contains((string) ($p['code'] ?? ''), 'waist-stay'),
        );

        $this->assertNotEmpty($stay, 'وزن دامن و دنباله باید روی نوار کمر داخلی بیفتد، نه روی درزها.');
    }

    public function test_the_engagement_dress_has_real_sleeves_that_fit_the_armhole(): void
    {
        $pieces = $this->build('engagement_dress');

        $sleeve = null;
        $armhole = 0.0;

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if ($part === 'sleeve') {
                $sleeve = $piece;
            }

            if (in_array($part, ['front_bodice', 'back_bodice'], true)) {
                foreach (Geometry::edgesWithTag($piece, 'armhole') as $edge) {
                    $armhole += Geometry::edgeLength($piece['outline'], $edge);
                }
            }
        }

        $this->assertNotNull($sleeve, 'لباس عقد آستین دارد.');
        $this->assertNotEmpty(Geometry::edgesWithTag($sleeve, 'armhole'), 'آستین باید لبهٔ حلقه داشته باشد.');
        $this->assertGreaterThan(0, $armhole, 'بالاتنهٔ لباس عقد باید حلقه داشته باشد.');
    }

    public function test_the_veil_is_a_half_circle_whose_radius_is_its_length(): void
    {
        $pieces = $this->build('bridal_veil', '40', ['length' => 180]);
        $main = $pieces[0];

        $this->assertSame('veil', $main['meta']['part']);
        $this->assertEqualsWithDelta(360.0, Geometry::width($main['outline']), 2.0, 'پهنای تور دو برابر بلندی آن است.');
        $this->assertEqualsWithDelta(180.0, Geometry::height($main['outline']), 2.0);
        $this->assertNotEmpty($main['meta']['gathers'] ?? [], 'لبهٔ بالای تور چین می‌خورد.');
    }

    public function test_a_long_veil_without_a_second_layer_is_warned_about(): void
    {
        $notes = $this->notes($this->build('bridal_veil', '40', ['length' => 250, 'blusher' => false]));

        $this->assertStringContainsString('دو لایه', $notes);
    }
}
