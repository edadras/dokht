<?php

namespace App\Services\Pattern\Style\Closure;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Detail\DetailStyle;

/**
 * پایه مشترک بست‌ها (دکمه، زیپ، بند).
 *
 * همه بست‌ها روی «لبه مرکز جلو» کار می‌کنند: اگر قطعه تا حالا روی تای پارچه بریده
 * می‌شد، اول اضافه جلو به آن داده می‌شود و بعد جای دکمه‌ها با فاصله واقعی روی خط
 * مرکز علامت می‌خورد — نه به عنوان تزیین، بلکه به عنوان نشانه دوخت.
 */
abstract class BaseClosure extends DetailStyle
{
    public static function group(): string
    {
        return 'closure';
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->frontIndexes($pieces) === []) {
            return 'این بست روی تنه جلو بسته می‌شود؛ این لباس تنه جلو ندارد.';
        }

        return true;
    }

    /** @return array<int, int> */
    protected function frontIndexes(array $pieces): array
    {
        return $this->indexesOfParts($pieces, ['front_bodice']);
    }

    /** لبه عمودی مرکز جلو (لبه‌ای که دو سرش روی کمترین x است). */
    protected function centerEdge(array $piece): ?int
    {
        return $this->foldEdges($piece)[0] ?? null;
    }

    /**
     * بالا و پایین خط مرکز جلو.
     *
     * @return array{0: float, 1: float}
     */
    protected function centerSpan(array $piece): array
    {
        $edge = $this->centerEdge($piece);
        $outline = array_values($piece['outline']);

        if ($edge === null) {
            return [0.0, Geometry::height($piece['outline'])];
        }

        $count = count($outline);
        $a = (float) $outline[$edge % $count]['y'];
        $b = (float) $outline[($edge + 1) % $count]['y'];

        return [min($a, $b), max($a, $b)];
    }

    /**
     * یک ردیف دکمه روی خط عمودی x، با فاصله یکنواخت.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array{x: float, y: float}>}
     */
    protected function buttonRow(
        array $piece,
        float $x,
        float $topY,
        float $bottomY,
        int $count,
        float $size,
        string $label,
        bool $holes = true,
    ): array {
        $count = max(1, $count);
        $span = max(1.0, $bottomY - $topY);
        $step = $count > 1 ? $span / ($count - 1) : 0.0;
        $positions = [];

        for ($i = 0; $i < $count; $i++) {
            $y = $topY + ($step * $i);

            $piece['drills'][] = $this->drill($x, $y, 'button', $label.' '.($i + 1));
            $positions[] = ['x' => round($x, 2), 'y' => round($y, 2)];

            if ($holes) {
                $piece['markers'][] = $this->marker(
                    'buttonhole',
                    'جادکمه '.($i + 1),
                    $x - ($size * 0.35),
                    $y,
                    $x + ($size * 0.8),
                    $y,
                );
            }
        }

        $piece['meta']['buttons'][] = [
            'label' => $label,
            'x' => round($x, 2),
            'count' => $count,
            'size' => round($size, 2),
            'spacing' => round($step, 2),
            'positions' => $positions,
        ];

        $piece['markers'][] = $this->marker('button_line', $label, $x, $topY, $x, $bottomY);

        return $piece;
    }

    /** نوار پاتلت جدا (برای دکمه مخفی و پیراهن). */
    protected function placketStrip(float $width, float $length, string $code, string $name, int $cut = 1): array
    {
        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => $cut,
            'outline' => $this->rect($width, $length),
            'grainline' => $this->grainline($width * 0.5, 1, $length - 1),
            'meta' => [
                'part' => 'placket',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'closure' => static::key(),
            ],
        ]);
    }
}
