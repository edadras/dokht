<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/**
 * دکمه مخفی: جادکمه‌ها روی یک نوار جدا پشت مرکز جلو می‌نشینند و از بیرون دیده نمی‌شوند.
 */
class ConcealedClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_concealed';
    }

    public function label(): string
    {
        return 'دکمه مخفی';
    }

    public function description(): string
    {
        return 'جادکمه‌ها پشت یک نوار پنهان می‌شوند و از رو هیچ دکمه‌ای دیده نمی‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'buttons' => ['label' => 'تعداد دکمه', 'min' => 2, 'max' => 12, 'step' => 1, 'default' => 6],
            'button_size' => ['label' => 'قطر دکمه', 'min' => 0.8, 'max' => 3, 'step' => 0.1, 'default' => 1.4, 'unit' => 'سانتی‌متر'],
            'placket_width' => [
                'label' => 'پهنای نوار مخفی', 'min' => 2.5, 'max' => 7, 'step' => 0.5, 'default' => 3.5, 'unit' => 'سانتی‌متر',
            ],
            'top_gap' => ['label' => 'فاصله دکمه اول از یقه', 'min' => 0.5, 'max' => 12, 'step' => 0.5, 'default' => 3, 'unit' => 'سانتی‌متر'],
            'bottom_gap' => ['label' => 'فاصله دکمه آخر از دم', 'min' => 0.5, 'max' => 25, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر'],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(2, (int) $this->num($context, 'buttons', 6));
        $size = $this->num($context, 'button_size', 1.4);
        $width = $this->num($context, 'placket_width', 3.5);
        $topGap = $this->num($context, 'top_gap', 3);
        $bottomGap = $this->num($context, 'bottom_gap', 6);
        $extension = round($width, 2);

        $length = 0.0;
        $notes = [];

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $this->addFrontExtension($pieces[$index], $extension);
            $center = $this->centerX($piece);
            [$top, $bottom] = $this->centerSpan($piece);
            $length = max($length, $bottom - $top);

            // دکمه‌ها روی مرکز می‌مانند ولی جادکمه روی نوار جدا زده می‌شود
            $piece = $this->buttonRow(
                $piece,
                $center,
                $top + $topGap,
                max($top + $topGap + 1, $bottom - $bottomGap),
                $count,
                $size,
                'دکمه پشت نوار مخفی',
                holes: false,
            );

            $piece['meta']['closure'] = static::key();
            $piece['meta']['button_line'] = round($center, 2);
            $pieces[$index] = $piece;
        }

        $placket = $this->placketStrip($width * 3, $length + 3, 'placket-concealed', 'نوار دکمه مخفی', 2);
        $placket['markers'][] = $this->marker('fold', 'خط تای اول', $width, 0, $width, $length + 3);
        $placket['markers'][] = $this->marker('fold', 'خط تای دوم', $width * 2, 0, $width * 2, $length + 3);

        $step = $count > 1 ? ($length - $topGap - $bottomGap) / ($count - 1) : 0.0;

        for ($i = 0; $i < $count; $i++) {
            $y = $topGap + ($step * $i);
            $placket['drills'][] = $this->drill($width * 1.5, $y, 'buttonhole', 'جادکمه مخفی '.($i + 1));
            $placket['markers'][] = $this->marker('buttonhole', 'جادکمه '.($i + 1), ($width * 1.5) - ($size * 0.35), $y, ($width * 1.5) + ($size * 0.8), $y);
        }

        $notes[] = $this->note('tip', 'نوار مخفی '.Format::cm($width).'ی (سه لا، پس '
            .Format::cm($width * 3).' بریده می‌شود) پشت مرکز جلو نشست و '.$count.' جادکمه رویش خورد.');
        $notes[] = $this->note('info', 'دکمه‌ها روی خط مرکز جلوی طرف مقابل می‌نشینند؛ از بیرون هیچ دکمه‌ای دیده نمی‌شود.');
        $notes[] = $this->fabricNote($width * 3, 'نوار دکمه مخفی');

        return $this->result(array_merge($pieces, [$placket]), $notes, [
            'buttons' => $count,
            'placket_width' => round($width, 2),
        ]);
    }
}
