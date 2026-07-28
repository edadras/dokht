<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/**
 * دکمه‌خور دوردیفه: جلوی لباس روی هم می‌افتد و دو ردیف دکمه دارد.
 *
 * اضافه جلو به اندازه «روی هم افتادن» بزرگ می‌شود و دو ردیف دکمه دقیقاً قرینه خط
 * مرکز جلو، هرکدام به اندازه همان روی هم افتادن از مرکز فاصله می‌گیرند.
 */
class DoubleBreastedClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_double_breasted';
    }

    public function label(): string
    {
        return 'دکمه‌خور دوردیفه';
    }

    public function description(): string
    {
        return 'جلو روی هم می‌افتد و دو ردیف دکمه قرینه مرکز جلو می‌نشیند؛ کت دوردیفه.';
    }

    public function paramsSchema(): array
    {
        return [
            'overlap' => [
                'label' => 'روی هم افتادن', 'min' => 3, 'max' => 14, 'step' => 0.5, 'default' => 6.5,
                'unit' => 'سانتی‌متر', 'hint' => 'هر ردیف دکمه همین اندازه از مرکز جلو فاصله می‌گیرد.',
            ],
            'pairs' => ['label' => 'تعداد جفت دکمه', 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 3],
            'button_size' => ['label' => 'قطر دکمه', 'min' => 1, 'max' => 4, 'step' => 0.1, 'default' => 2, 'unit' => 'سانتی‌متر'],
            'top_gap' => ['label' => 'فاصله ردیف اول از یقه', 'min' => 2, 'max' => 30, 'step' => 0.5, 'default' => 8, 'unit' => 'سانتی‌متر'],
            'bottom_gap' => ['label' => 'فاصله ردیف آخر از دم', 'min' => 1, 'max' => 30, 'step' => 0.5, 'default' => 8, 'unit' => 'سانتی‌متر'],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $overlap = $this->num($context, 'overlap', 6.5);
        $pairs = max(1, (int) $this->num($context, 'pairs', 3));
        $size = $this->num($context, 'button_size', 2);
        $topGap = $this->num($context, 'top_gap', 8);
        $bottomGap = $this->num($context, 'bottom_gap', 8);
        $extension = round($overlap + ($size * 0.6), 2);

        $notes = [];
        $moved = 0.0;

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $had = (float) ($piece['meta']['button_stand'] ?? 0);
            $piece = $this->addFrontExtension($piece, $extension);
            $moved = round($extension - $had, 2);

            $center = $this->centerX($piece);
            [$top, $bottom] = $this->centerSpan($piece);
            $first = $top + $topGap;
            $last = max($first + 1, $bottom - $bottomGap);

            // ردیف بیرونی (سمت لبه) و ردیف داخلی، هر دو به اندازه overlap از مرکز
            $piece = $this->buttonRow($piece, $center - $overlap, $first, $last, $pairs, $size, 'ردیف دکمه بیرونی');
            $piece = $this->buttonRow($piece, $center + $overlap, $first, $last, $pairs, $size, 'ردیف دکمه داخلی', holes: false);

            $piece['meta']['closure'] = static::key();
            $piece['meta']['button_line'] = round($center, 2);
            $piece['meta']['button_overlap'] = round($overlap, 2);
            $piece['markers'][] = $this->marker('cf', 'خط مرکز جلو', $center, $top, $center, $bottom);

            $pieces[$index] = $piece;
        }

        $notes[] = $this->note('tip', 'اضافه جلو '.Format::cm($moved).' بزرگ‌تر شد تا جلو '
            .Format::cm($overlap).' روی هم بیفتد.');
        $notes[] = $this->note('tip', $pairs.' جفت دکمه گذاشته شد: دو ردیف که هرکدام '
            .Format::cm($overlap).' از خط مرکز جلو فاصله دارند، پس کاملاً قرینه‌اند.');
        $notes[] = $this->fabricNote($extension * 2, 'اضافه جلوی دوردیفه');
        $notes[] = $this->note('info', 'خط یقه به اندازه اضافه جلو بلندتر شد؛ یقه دوباره اندازه گرفته می‌شود.');

        return $this->result($pieces, $notes, [
            'overlap' => round($overlap, 2),
            'extension' => $extension,
            'pairs' => $pairs,
        ]);
    }
}
