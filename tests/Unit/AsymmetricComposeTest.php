<?php

namespace Tests\Unit;

use App\Services\Pattern\PatternComposer;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لباس نامتقارن: سبکی که فقط روی یک سمت می‌نشیند.
 *
 * تا امروز هر سبک روی هر دو سمت لباس می‌نشست، چون تنه یک بار بریده و آینه
 * می‌شد. برای «جیب فقط سمت چپ» یا «لتِ یک‌طرفه»، آن قطعه باید دو قطعه مستقل شود.
 */
class AsymmetricComposeTest extends TestCase
{
    use RefreshDatabase;

    protected PatternComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsWorkshopUser();
        $this->composer = app(PatternComposer::class);
    }

    /** @return array<string, mixed> */
    protected function compose(array $styles): array
    {
        $recipe = $this->composer->normalizeRecipe([
            'base' => ['kind' => 'garment', 'garment' => 'shirt_classic'],
            'styles' => $styles,
        ]);

        return $this->composer->compose($recipe, Measurements::complete([]), [], []);
    }

    /** @return array<int, string> */
    protected function codes(array $result): array
    {
        return array_column($result['pieces'], 'code');
    }

    public function test_the_recipe_remembers_which_side_a_style_belongs_to(): void
    {
        $styles = $this->composer->normalizeStyles([
            ['key' => 'pocket_patch', 'side' => 'left'],
        ]);

        $this->assertSame('left', $styles[0]['side']);

        $unknown = $this->composer->normalizeStyles([['key' => 'pocket_patch', 'side' => 'بالا']]);
        $this->assertSame('both', $unknown[0]['side'], 'سمت ناشناخته یعنی هر دو طرف.');
    }

    public function test_a_symmetric_garment_keeps_one_mirrored_front(): void
    {
        $codes = $this->codes($this->compose([['key' => 'pocket_patch']]));

        $this->assertContains('shirt-front', $codes);
        $this->assertNotContains('shirt-front-left', $codes);
    }

    public function test_a_one_sided_style_splits_the_front_into_left_and_right(): void
    {
        $result = $this->compose([['key' => 'pocket_patch', 'side' => 'left']]);
        $codes = $this->codes($result);

        $this->assertContains('shirt-front-left', $codes);
        $this->assertContains('shirt-front-right', $codes);
        $this->assertNotContains('shirt-front', $codes);

        // جیب فقط یکی است و مهر همان سمت را دارد
        $pockets = array_values(array_filter($result['pieces'], fn (array $p) => str_contains($p['code'], 'pocket')));

        $this->assertCount(1, $pockets);
        $this->assertSame('left', $pockets[0]['meta']['hand']);
    }

    public function test_each_split_half_is_cut_once_and_is_not_mirrored(): void
    {
        $result = $this->compose([['key' => 'pocket_patch', 'side' => 'left']]);

        foreach ($result['pieces'] as $piece) {
            if (($piece['meta']['hand'] ?? null) === null) {
                continue;
            }

            $this->assertSame(1, (int) $piece['cut_quantity'], $piece['code'].' باید یک بار بریده شود.');
            $this->assertFalse((bool) $piece['mirror'], $piece['code'].' دیگر آینه نمی‌شود.');
        }
    }

    public function test_a_piece_the_style_never_touched_goes_back_to_being_mirrored(): void
    {
        $codes = $this->codes($this->compose([['key' => 'pocket_patch', 'side' => 'left']]));

        // جیب به آستین کاری ندارد، پس آستین نباید بی‌جهت دو قطعه شود
        $this->assertContains('sleeve', $codes);
        $this->assertNotContains('sleeve-left', $codes);
    }

    public function test_the_two_halves_really_differ(): void
    {
        $result = $this->compose([['key' => 'pocket_patch', 'side' => 'left']]);
        $left = $right = null;

        foreach ($result['pieces'] as $piece) {
            if ($piece['code'] === 'shirt-front-left') {
                $left = $piece;
            }

            if ($piece['code'] === 'shirt-front-right') {
                $right = $piece;
            }
        }

        $this->assertNotNull($left);
        $this->assertNotNull($right);

        $marks = fn (array $piece) => count($piece['markers'] ?? []) + count($piece['notches'] ?? []);

        $this->assertNotSame(
            $marks($left),
            $marks($right),
            'سمت چپ باید نشانه جیب داشته باشد و سمت راست نداشته باشد.',
        );
    }

    public function test_the_armhole_is_still_counted_once_after_the_split(): void
    {
        $symmetric = $this->compose([['key' => 'pocket_patch']]);
        $split = $this->compose([['key' => 'pocket_patch', 'side' => 'left']]);

        $armhole = fn (array $result) => $this->composer->armholeLength(array_values(array_filter(
            $result['pieces'],
            fn (array $p) => in_array($p['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true),
        )));

        $this->assertEqualsWithDelta(
            $armhole($symmetric),
            $armhole($split),
            0.05,
            'شکستن قطعه به چپ و راست نباید حلقه آستین را دو برابر بشمارد.',
        );
    }
}
