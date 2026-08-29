<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Hem\LengthAdjustHem;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * تغییر قد لباس به دستِ خیاط.
 *
 * سه چیز باید درست باشد، وگرنه لباس دوخته نمی‌شود: قد واقعاً عوض شود، درزِ پهلوی
 * جلو و پشت باز هم هم‌اندازه بمانند، و شیبِ کلوش ادامه پیدا کند نه اینکه دامن از
 * جایی به بعد لوله شود.
 */
class LengthAdjustHemTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    protected function build(string $key): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete(Measurements::fromSize('40')),
            [],
            $generator->defaultParams(),
        );
    }

    /** @param array<string, mixed> $params */
    protected function adjust(array $pieces, array $params): array
    {
        return (new LengthAdjustHem)->apply($pieces, ['params' => $params]);
    }

    protected function hemHeight(array $pieces, string $part): float
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') === $part) {
                return Geometry::height($piece['outline']);
            }
        }

        return 0.0;
    }

    protected function sideSeam(array $pieces, string $part): float
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') !== $part) {
                continue;
            }

            $total = 0.0;

            foreach ($piece['meta']['edges'] ?? [] as $index => $tag) {
                if ($tag === 'side') {
                    $total += Geometry::edgeLength($piece['outline'], (int) $index);
                }
            }

            return round($total, 2);
        }

        return 0.0;
    }

    public function test_lengthening_a_skirt_makes_it_longer(): void
    {
        $pieces = $this->build('skirt_a_line');
        $before = $this->hemHeight($pieces, 'skirt_front');

        $after = $this->adjust($pieces, ['change' => 10])['pieces'];

        $this->assertEqualsWithDelta(
            $before + 10,
            $this->hemHeight($after, 'skirt_front'),
            0.6,
            'دامن باید دقیقاً ده سانتی‌متر بلندتر شود.',
        );
    }

    public function test_shortening_a_skirt_makes_it_shorter(): void
    {
        $pieces = $this->build('skirt_a_line');
        $before = $this->hemHeight($pieces, 'skirt_front');

        $after = $this->adjust($pieces, ['change' => -12])['pieces'];

        $this->assertEqualsWithDelta(
            $before - 12,
            $this->hemHeight($after, 'skirt_front'),
            0.6,
            'دامن باید دقیقاً دوازده سانتی‌متر کوتاه‌تر شود.',
        );
    }

    /**
     * درزِ پهلوی جلو و پشت پس از تغییر قد همان‌قدر به هم می‌خورند که پیش از آن.
     *
     * برابریِ مطلق ادعا نمی‌شود: بعضی مدل‌ها عمداً پشت را کوتاه‌تر می‌برند (شیفت و
     * ترنچ‌کت هر دو سه و نیم سانتی‌متر اختلاف دارند و درست هم هست). آن‌چه تغییرِ
     * قد نباید بکند، *بیشتر کردن* همان اختلاف است — چون آن‌وقت دو لبه‌ای که به هم
     * دوخته می‌شوند از هم دور می‌شوند و لباس روی تن می‌چرخد.
     */
    public function test_the_front_and_back_side_seams_keep_their_relation(): void
    {
        foreach (['skirt_a_line', 'dress_shift', 'skirt_circle_half', 'coat_trench'] as $key) {
            $before = $this->build($key);
            $after = $this->adjust($before, ['change' => 8])['pieces'];

            foreach ([['skirt_front', 'skirt_back'], ['front_bodice', 'back_bodice']] as [$front, $back]) {
                $was = $this->sideSeam($before, $front) - $this->sideSeam($before, $back);
                $now = $this->sideSeam($after, $front) - $this->sideSeam($after, $back);

                if ($this->sideSeam($after, $front) < 1.0 || $this->sideSeam($after, $back) < 1.0) {
                    continue;
                }

                $this->assertEqualsWithDelta(
                    $was,
                    $now,
                    0.2,
                    "«{$key}»: اختلاف درز پهلوی جلو و پشت نباید با تغییر قد بیشتر شود.",
                );
            }
        }
    }

    public function test_a_flared_skirt_keeps_flaring_when_lengthened(): void
    {
        $pieces = $this->build('skirt_a_line');
        $wide = fn (array $set) => Geometry::width($this->pieceOf($set, 'skirt_front')['outline']);

        $before = $wide($pieces);
        $after = $wide($this->adjust($pieces, ['change' => 15])['pieces']);

        $this->assertGreaterThan(
            $before + 1.0,
            $after,
            'دامن کلوش که بلندتر می‌شود باید پهن‌تر هم بشود، وگرنه از آن‌جا به بعد لوله می‌شود.',
        );
    }

    public function test_shortening_stops_before_the_piece_disappears(): void
    {
        $pieces = $this->build('skirt_a_line');
        $result = $this->adjust($pieces, ['change' => -200]);

        $this->assertGreaterThan(
            10.0,
            $this->hemHeight($result['pieces'], 'skirt_front'),
            'کوتاه‌کردن نباید قطعه را ناپدید کند.',
        );

        $warnings = array_filter($result['notes'], fn (array $note) => $note['type'] === 'warning');

        $this->assertNotEmpty($warnings, 'وقتی خواسته کامل اجرا نشد باید صادقانه گفته شود.');
    }

    public function test_the_sleeve_can_be_changed_on_its_own(): void
    {
        $pieces = $this->build('shirt_classic');
        $before = $this->hemHeight($pieces, 'sleeve');

        $after = $this->adjust($pieces, ['change' => 0, 'sleeve_change' => -6])['pieces'];

        $this->assertEqualsWithDelta(
            $before - 6,
            $this->hemHeight($after, 'sleeve'),
            0.6,
            'آستین باید جدا از تنه کوتاه شود.',
        );
    }

    public function test_it_works_on_every_garment_that_has_a_hem(): void
    {
        $broken = [];

        foreach (['dress_shift', 'pants_chino', 'coat_trench', 'blouse_classic', 'skirt_pleat_knife'] as $key) {
            $pieces = $this->build($key);
            $style = new LengthAdjustHem;

            if ($style->supports($pieces, []) !== true) {
                $broken[] = $key.' پشتیبانی نشد';

                continue;
            }

            foreach ($style->apply($pieces, ['params' => ['change' => -5]])['pieces'] as $piece) {
                if (Geometry::selfIntersects($piece['outline'] ?? [])) {
                    $broken[] = $key.'|'.$piece['code'].' مسیرش را قطع کرد';
                }
            }
        }

        $this->assertSame([], $broken);
    }

    /** @return array<string, mixed> */
    protected function pieceOf(array $pieces, string $part): array
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') === $part) {
                return $piece;
            }
        }

        return ['outline' => []];
    }
}
