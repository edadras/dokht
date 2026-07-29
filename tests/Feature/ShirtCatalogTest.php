<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\PatternComposer;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bodies;
use Tests\TestCase;

/**
 * کاتالوگ پیراهن و تی‌شرت.
 *
 * جای خطای این خانواده دو تاست و هر دو از یک ریشه می‌آیند — قطعه‌ای که بریده
 * می‌شود (یوک) یا سرشانه‌ای که جابه‌جا می‌شود (اورسایز): حلقهٔ آستین عوض
 * می‌شود و اگر کسی حواسش نباشد، آستین در حلقه نمی‌نشیند.
 */
class ShirtCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected const EASE = ['bust' => 10, 'waist' => 10, 'hip' => 10, 'neck' => 2, 'bicep' => 6];

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Bodies::body($size),
            static::EASE,
            array_merge($generator->defaultParams(), $params),
        );
    }

    protected function part(array $pieces, string $part): ?array
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') === $part) {
                return $piece;
            }
        }

        return null;
    }

    protected function armholeOf(array $piece): float
    {
        $length = 0.0;

        foreach (Geometry::edgesWithTag($piece, 'armhole') as $edge) {
            $length += Geometry::edgeLength($piece['outline'], $edge);
        }

        return $length;
    }

    public function test_the_family_is_registered_and_can_play_the_bodice_role(): void
    {
        $this->assertArrayHasKey('shirt', GeneratorRegistry::GROUPS);
        $this->assertContains('shirt', PatternComposer::BODICE_GROUPS);

        $shirts = GeneratorRegistry::group('shirt');

        $this->assertGreaterThanOrEqual(13, count($shirts), 'خانوادهٔ پیراهن باید دست‌کم سیزده مدل داشته باشد.');

        foreach ([
            'shirt_camp', 'shirt_band_collar', 'shirt_oversized', 'shirt_western',
            'shirt_utility', 'shirt_overshirt', 'shirt_popover', 'shirt_fitted_blouse',
            'tshirt_boxy', 'tshirt_long_sleeve', 'tshirt_ringer', 'polo_shirt', 'henley_shirt',
        ] as $key) {
            $this->assertArrayHasKey($key, $shirts, "«{$key}» در کاتالوگ نیست.");
        }
    }

    public function test_every_shirt_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_keys(GeneratorRegistry::group('shirt')) as $key) {
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
     |  یوک: بریدن نباید حلقه را دو بار بشمارد
     * ------------------------------------------------------------------- */

    public function test_a_yoke_takes_part_of_the_armhole_with_it(): void
    {
        $pieces = $this->build('shirt_western');

        $back = $this->part($pieces, 'back_bodice');
        $yoke = $this->part($pieces, 'yoke');

        $this->assertNotNull($yoke, 'پیراهن وسترن باید یوک داشته باشد.');
        $this->assertNotNull($back);

        // هر دو نیمه باید طول حلقهٔ خودشان را بگویند، نه طول قطعهٔ پیش از برش
        foreach ([$back, $yoke] as $piece) {
            $this->assertEqualsWithDelta(
                $this->armholeOf($piece),
                (float) ($piece['meta']['armhole_length'] ?? -1),
                0.2,
                "«{$piece['name']}» طول حلقه‌ای را ادعا می‌کند که روی خودش نیست.",
            );
        }
    }

    public function test_the_sleeve_cap_matches_the_armhole_it_goes_into(): void
    {
        foreach (array_keys(GeneratorRegistry::group('shirt')) as $key) {
            $pieces = $this->build($key);
            $sleeve = $this->part($pieces, 'sleeve');

            if ($sleeve === null) {
                continue;
            }

            $armhole = 0.0;

            foreach ($pieces as $piece) {
                if (in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice', 'yoke'], true)) {
                    $armhole += $this->armholeOf($piece);
                }
            }

            $cap = (float) ($sleeve['meta']['cap_length'] ?? $this->armholeOf($sleeve));

            $this->assertGreaterThan(0, $armhole, "«{$key}» حلقهٔ آستین ندارد.");
            $this->assertLessThan(
                $armhole * 0.25,
                abs($cap - $armhole),
                "«{$key}»: سرآستین {$cap} و حلقه {$armhole}؛ آستین در حلقه نمی‌نشیند.",
            );
        }
    }

    public function test_a_dropped_shoulder_also_deepens_the_armhole(): void
    {
        $plain = $this->build('shirt_oversized', '40', ['drop_shoulder' => 0]);
        $dropped = $this->build('shirt_oversized', '40', ['drop_shoulder' => 8]);

        $this->assertGreaterThan(
            $this->armholeOf($this->part($plain, 'front_bodice')),
            $this->armholeOf($this->part($dropped, 'front_bodice')),
            'سرشانهٔ افتاده باید حلقه را گودتر کند، وگرنه آستین زیر بغل می‌کشد.',
        );
    }

    /* ---------------------------------------------------------------------
     |  بسته شدن
     * ------------------------------------------------------------------- */

    public function test_a_buttoned_shirt_counts_its_own_buttons(): void
    {
        foreach (['shirt_camp', 'shirt_band_collar', 'shirt_oversized', 'shirt_utility'] as $key) {
            $placket = $this->part($this->build($key), 'placket');

            $this->assertNotNull($placket, "«{$key}» باید پاتلت داشته باشد.");
            $this->assertNotEmpty($placket['drills'] ?? [], 'جای دکمه‌ها باید روی پاتلت علامت بخورد.');

            $notion = $placket['meta']['notions'][0] ?? null;
            $this->assertNotNull($notion);
            $this->assertSame(count($placket['drills']), (int) $notion['count'], 'شمار دکمه با شمار جای دکمه یکی نیست.');
        }
    }

    public function test_a_western_shirt_asks_for_snaps_instead_of_buttons(): void
    {
        $placket = $this->part($this->build('shirt_western', '40', ['snaps' => true]), 'placket');

        $this->assertSame('snap', $placket['meta']['notions'][0]['type'] ?? null, 'پیراهن وسترن دکمه فشاری می‌خواهد.');
    }

    public function test_a_popover_has_no_front_opening_below_the_placket(): void
    {
        $front = $this->part($this->build('shirt_popover'), 'front_bodice');

        $this->assertTrue((bool) $front['on_fold'], 'جلوی پیراهن نیمه‌دکمه یک‌تکه است و روی تای پارچه بریده می‌شود.');
    }

    public function test_a_popover_says_it_goes_over_the_head(): void
    {
        $pieces = $this->build('shirt_popover', '40', ['placket_length' => 12]);
        $notes = implode(' ', $pieces[0]['meta']['notes'] ?? []);

        $this->assertStringContainsString('از سر پوشیده می‌شود', $notes);

        // بازشدگیِ یقه با چاک باید از دور سر بیشتر باشد، وگرنه پیراهن پوشیده نمی‌شود
        $front = $this->part($pieces, 'front_bodice');
        $back = $this->part($pieces, 'back_bodice');
        $opening = ((float) ($front['meta']['neck_length'] ?? 0) + (float) ($back['meta']['neck_length'] ?? 0)) * 2
            + (12 * 2);

        $this->assertGreaterThan(
            (float) (Measurements::complete(Measurements::fromSize('40'))['neck'] ?? 37) * 1.5,
            $opening,
            'بازشدگی یقه با چاک باید از دور سر بیشتر باشد.',
        );
    }

    public function test_a_fitted_blouse_puts_a_button_on_the_bust_line(): void
    {
        $pieces = $this->build('shirt_fitted_blouse');
        $placket = $this->part($pieces, 'placket');
        $front = $this->part($pieces, 'front_bodice');

        // جایی که الگو عمداً دکمه را رویش نشانده: پرحجم‌ترین نقطهٔ سینه، نه خط زیر بغل
        $bustY = (float) ($placket['meta']['button_at_bust'] ?? 0);
        $this->assertGreaterThan(0, $bustY);
        $this->assertGreaterThan((float) ($front['meta']['bust_y'] ?? 0), $bustY);

        $distances = array_map(
            fn (array $drill) => abs(((float) $drill['y']) - $bustY),
            $placket['drills'] ?? [],
        );

        $this->assertNotEmpty($distances);
        $this->assertLessThan(1.0, min($distances), 'یکی از دکمه‌ها باید روی خط سینه بنشیند.');
    }

    /* ---------------------------------------------------------------------
     |  تی‌شرت‌ها
     * ------------------------------------------------------------------- */

    public function test_every_rib_band_is_cut_shorter_than_the_edge_it_sits_on(): void
    {
        foreach (['tshirt_boxy', 'tshirt_long_sleeve', 'tshirt_ringer', 'polo_shirt', 'henley_shirt'] as $key) {
            foreach ($this->build($key) as $piece) {
                if (empty($piece['meta']['rib'])) {
                    continue;
                }

                $this->assertLessThan(
                    (float) $piece['meta']['target_length'],
                    Geometry::width($piece['outline']),
                    "«{$key}» — «{$piece['name']}» باید کوتاه‌تر از لبه‌اش بریده شود، وگرنه لبه موج می‌اندازد.",
                );
            }
        }
    }

    public function test_a_polo_is_longer_at_the_back_than_the_front(): void
    {
        $pieces = $this->build('polo_shirt', '40', ['back_drop' => 5]);

        $this->assertGreaterThan(
            Geometry::height($this->part($pieces, 'front_bodice')['outline']),
            Geometry::height($this->part($pieces, 'back_bodice')['outline']),
            'پشت پولو بلندتر است تا هنگام خم شدن بالا نرود.',
        );
    }

    public function test_a_ringer_tee_marks_its_contrast_pieces(): void
    {
        $contrast = array_filter($this->build('tshirt_ringer'), fn (array $p) => ! empty($p['meta']['contrast']));

        $this->assertCount(2, $contrast, 'رینگر دو قطعهٔ رنگ متضاد دارد: نوار یقه و نوار آستین.');
    }

    public function test_a_shirt_can_be_composed_with_a_skirt_in_the_studio(): void
    {
        $this->actingAsWorkshopUser();

        $result = app(PatternComposer::class)->compose([
            'bodice' => 'tshirt_boxy',
            'sleeve' => 'none',
            'lower' => 'skirt_a_line',
            'collar' => 'none',
            'base_size' => '40',
        ], Measurements::fromSize('40'));

        $this->assertNotEmpty($result['pieces']);

        foreach ($result['pieces'] as $piece) {
            $this->assertFalse(
                Geometry::selfIntersects($piece['outline'] ?? []),
                "«{$piece['name']}» پس از ترکیب خراب شد.",
            );
        }
    }
}
