<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/**
 * دکمه فشاری (دکمه قابلمه‌ای).
 *
 * دو نیمه فلزی که روی هم فشار داده می‌شوند؛ جادکمه نمی‌خواهد و همین آن را برای
 * لباس بچه، رکاب، لباس کار و سویشرت مناسب می‌کند. مرکز جلو مثل دکمه معمولی اضافه
 * می‌گیرد، چون دو لبه باید روی هم بیایند.
 */
class SnapClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_snap';
    }

    public function label(): string
    {
        return 'دکمه فشاری';
    }

    public function description(): string
    {
        return 'دو نیمه فلزی که روی هم فشار داده می‌شوند؛ جادکمه لازم ندارد و باز و بسته‌شدنش تند است.';
    }

    public function paramsSchema(): array
    {
        return [
            'snaps' => [
                'label' => 'تعداد دکمه فشاری', 'min' => 1, 'max' => 20, 'step' => 1, 'default' => 5,
            ],
            'snap_size' => [
                'label' => 'قطر دکمه', 'min' => 0.8, 'max' => 2.5, 'step' => 0.1, 'default' => 1.5,
                'unit' => 'سانتی‌متر',
            ],
            'top_gap' => [
                'label' => 'فاصله اولین دکمه از بالا', 'min' => 0, 'max' => 12, 'step' => 0.5,
                'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'bottom_gap' => [
                'label' => 'فاصله آخرین دکمه از پایین', 'min' => 0, 'max' => 40, 'step' => 0.5,
                'default' => 5, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(1, (int) $this->num($context, 'snaps', 5));
        $size = $this->num($context, 'snap_size', 1.5);
        $topGap = $this->num($context, 'top_gap', 2);
        $bottomGap = $this->num($context, 'bottom_gap', 5);
        $extension = round(($size * 0.75) + 0.8, 2);

        $notes = [];

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $had = (float) ($piece['meta']['button_stand'] ?? 0);

            if ($had < $extension) {
                $piece = $this->addFrontExtension($piece, $extension);
                $notes[] = $this->note('info', 'مرکز جلو '.Format::cm($extension - $had)
                    .' اضافه جلو گرفت تا دو نیمه دکمه فشاری روی هم بیفتند.');
            }

            $center = $this->centerX($piece);
            [$top, $bottom] = $this->centerSpan($piece);
            $first = $top + $topGap;
            $last = max($first + 1, $bottom - $bottomGap);
            $step = $count > 1 ? ($last - $first) / ($count - 1) : 0.0;

            for ($i = 0; $i < $count; $i++) {
                $y = $first + ($step * $i);
                // نیمه نر روی لبه رویی و نیمه ماده روی لبه زیری، هر دو روی خط مرکز
                $piece['drills'][] = $this->drill($center, $y, 'snap', 'دکمه فشاری '.($i + 1));
            }

            $piece['markers'][] = $this->marker('snap_line', 'خط دکمه فشاری', $center, $first, $center, $last);
            $piece['meta']['closure'] = static::key();
            $piece['meta']['notions'][] = [
                'type' => 'snap',
                'label' => 'دکمه فشاری',
                'count' => $count,
                'size' => round($size, 2),
            ];

            $pieces[$index] = $piece;
        }

        $notes[] = $this->note('tip', 'دکمه فشاری با پرس یا انبر مخصوص نصب می‌شود؛ جای آن‌ها روی الگو علامت خورده است.');
        $notes[] = $this->note('warning', 'زیر هر دکمه باید دو لا پارچه یا لایی باشد، وگرنه پایه فلزی پارچه را پاره می‌کند.');

        return $this->result($pieces, $notes, ['snaps' => $count]);
    }
}
