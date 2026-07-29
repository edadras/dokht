<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\PatternComposer;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ تاپ.
 *
 * تاپ‌ها یک چیز مشترک دارند که بقیهٔ بلوک‌ها ندارند: خط بالایشان عوض شده و
 * بخشی از سرشانه و حلقه برداشته شده. همان‌جا هم چیزهایی خراب می‌شود که این
 * آزمون‌ها مواظبشان‌اند — درز پهلویی که دیگر با پشتش نمی‌خواند، بلندی منفی که
 * روی بدن کوچک بالای زیر بغل می‌افتد، و بندی که هیچ‌جا شمرده نمی‌شود.
 */
class TopCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete(Measurements::fromSize($size)),
            ['bust' => 4, 'waist' => 3, 'hip' => 4, 'neck' => 1],
            array_merge($generator->defaultParams(), $params),
        );
    }

    public function test_the_top_group_exists_and_can_play_the_bodice_role(): void
    {
        $this->assertArrayHasKey('top', GeneratorRegistry::GROUPS);
        $this->assertContains('top', PatternComposer::BODICE_GROUPS);

        $tops = GeneratorRegistry::group('top');

        $this->assertGreaterThanOrEqual(12, count($tops), 'کاتالوگ تاپ باید دست‌کم دوازده مدل داشته باشد.');

        foreach (['top_camisole', 'top_bandeau', 'top_tank', 'top_racerback', 'top_halter', 'top_backless'] as $key) {
            $this->assertArrayHasKey($key, $tops);
        }
    }

    public function test_every_top_builds_a_sound_pattern_on_five_different_bodies(): void
    {
        foreach (array_keys(GeneratorRegistry::group('top')) as $key) {
            foreach (['34', '40', '48', 'کودک', 'سینه‌درشت'] as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $outline = $piece['outline'] ?? [];

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$key}|{$size}|{$piece['name']} مسیر ندارد.");
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "{$key}|{$size}|{$piece['name']} مسیرش خودش را قطع می‌کند.",
                    );
                    $this->assertGreaterThan(
                        6,
                        abs(Geometry::area($outline)),
                        "{$key}|{$size}|{$piece['name']} تقریباً بی‌مساحت است.",
                    );
                }
            }
        }
    }

    public function test_a_crop_never_ends_above_the_underarm_even_on_a_child(): void
    {
        // منفی ۲۲ روی بدن کودک یعنی بالای زیر بغل؛ باید به تن همان آدم محدود شود
        $pieces = $this->build('top_crop', 'کودک', ['body_length' => -22]);

        foreach ($pieces as $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            $bustY = (float) ($piece['meta']['bust_y'] ?? 0);
            $height = Geometry::bounds($piece['outline'])[3];

            $this->assertGreaterThan(
                $bustY + 2,
                $height,
                'دم کراپ باید پایین‌تر از خط زیر بغل بایستد، وگرنه قطعه دوختنی نیست.',
            );
        }
    }

    public function test_front_and_back_side_seams_of_every_top_match(): void
    {
        foreach (array_keys(GeneratorRegistry::group('top')) as $key) {
            $pieces = $this->build($key);
            $sides = [];

            foreach ($pieces as $piece) {
                $part = (string) ($piece['meta']['part'] ?? '');

                if (! in_array($part, ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $edges = Geometry::edgesWithTag($piece, 'side');
                $length = 0.0;

                foreach ($edges as $edge) {
                    $length += Geometry::edgeLength($piece['outline'], $edge);
                }

                // ساسون سینه روی درز پهلو بسته می‌شود، پس آن مقدار دوخته نمی‌شود
                foreach ($piece['darts'] ?? [] as $dart) {
                    if (in_array((int) ($dart['edge'] ?? -1), $edges, true)) {
                        $length -= (float) ($dart['intake'] ?? 0);
                    }
                }

                if ($length > 0) {
                    $sides[$part] = $length;
                }
            }

            if (count($sides) === 2) {
                $this->assertEqualsWithDelta(
                    $sides['front_bodice'],
                    $sides['back_bodice'],
                    0.75,
                    "درز پهلوی جلو و پشت «{$key}» به هم نمی‌رسند.",
                );
            }
        }
    }

    public function test_a_strapless_top_says_so_instead_of_pretending_to_have_an_armhole(): void
    {
        foreach (['top_bandeau', 'top_off_shoulder'] as $key) {
            foreach ($this->build($key) as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $this->assertSame([], Geometry::edgesWithTag($piece, 'armhole'), "«{$key}» نباید حلقه داشته باشد.");
                $this->assertTrue((bool) ($piece['meta']['sleeveless'] ?? false), "«{$key}» باید خودش را بی‌آستین اعلام کند.");
            }
        }
    }

    public function test_a_tank_keeps_its_armhole_so_a_sleeve_could_still_go_in(): void
    {
        foreach ($this->build('top_tank') as $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            $this->assertNotEmpty(Geometry::edgesWithTag($piece, 'armhole'), 'تانک‌تاپ حلقه دارد.');
            $this->assertFalse((bool) ($piece['meta']['sleeveless'] ?? false));
        }
    }

    public function test_a_camisole_carries_its_straps_as_real_pieces(): void
    {
        $strap = collect($this->build('top_camisole'))->firstWhere('meta.strap', true);

        $this->assertNotNull($strap, 'کمیزول باید قطعهٔ بند داشته باشد.');
        $this->assertSame(2, (int) $strap['cut_quantity'], 'دو بند بریده می‌شود.');
        $this->assertContains('strap', $strap['meta']['edges'] ?? []);
    }

    public function test_a_one_shoulder_top_is_cut_open_not_on_the_fold(): void
    {
        foreach ($this->build('top_one_shoulder') as $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            $this->assertFalse((bool) $piece['on_fold'], 'قطعهٔ تاپ یک‌شانه نباید روی تای پارچه باشد.');
        }
    }

    public function test_the_bodysuit_reads_the_crotch_length_from_the_body(): void
    {
        $short = $this->build('top_bodysuit', '34');
        $tall = $this->build('top_bodysuit', 'بلندقد');

        $heightOf = fn (array $pieces) => Geometry::bounds(
            collect($pieces)->firstWhere('meta.part', 'front_bodice')['outline'],
        )[3];

        $this->assertGreaterThan(
            $heightOf($short),
            $heightOf($tall),
            'قد فاق بادی باید با قد بدن بلندتر شود، نه اینکه عدد ثابتی باشد.',
        );
    }

    public function test_a_top_can_be_composed_with_a_skirt_in_the_studio(): void
    {
        $this->actingAsWorkshopUser();

        $result = app(PatternComposer::class)->compose([
            'bodice' => 'top_tank',
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
