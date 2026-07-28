<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/** دکمه‌خور تک‌ردیفه: یک ردیف دکمه روی خط مرکز جلو. */
class SingleBreastedClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_single_breasted';
    }

    public function label(): string
    {
        return 'دکمه‌خور تک‌ردیفه';
    }

    public function description(): string
    {
        return 'یک ردیف دکمه روی مرکز جلو؛ اضافه جلو و جای دکمه‌ها خودکار حساب می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'buttons' => ['label' => 'تعداد دکمه', 'min' => 1, 'max' => 14, 'step' => 1, 'default' => 6],
            'button_size' => [
                'label' => 'قطر دکمه', 'min' => 0.8, 'max' => 4, 'step' => 0.1, 'default' => 1.4, 'unit' => 'سانتی‌متر',
            ],
            'top_gap' => [
                'label' => 'فاصله دکمه اول از یقه', 'min' => 0.5, 'max' => 12, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'bottom_gap' => [
                'label' => 'فاصله دکمه آخر از دم', 'min' => 0.5, 'max' => 25, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(1, (int) $this->num($context, 'buttons', 6));
        $size = $this->num($context, 'button_size', 1.4);
        $topGap = $this->num($context, 'top_gap', 2);
        $bottomGap = $this->num($context, 'bottom_gap', 6);
        $extension = round(($size * 0.75) + 0.8, 2);

        $notes = [];
        $spacing = 0.0;

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $had = (float) ($piece['meta']['button_stand'] ?? 0);

            if ($had < $extension) {
                $piece = $this->addFrontExtension($piece, $extension);
                $notes[] = $this->note('info', 'مرکز جلو '.Format::cm($extension - $had)
                    .' اضافه جلو گرفت تا دکمه رویش بنشیند؛ «'.$piece['name']
                    .'» دیگر روی تای پارچه بریده نمی‌شود و دو تکه (چپ و راست) بریده می‌شود.');
            }

            $center = $this->centerX($piece);
            [$top, $bottom] = $this->centerSpan($piece);

            $piece = $this->buttonRow(
                $piece,
                $center,
                $top + $topGap,
                max($top + $topGap + 1, $bottom - $bottomGap),
                $count,
                $size,
                'دکمه مرکز جلو',
            );

            $spacing = (float) ($piece['meta']['buttons'][0]['spacing'] ?? 0);
            $piece['meta']['closure'] = static::key();
            $piece['meta']['button_line'] = round($center, 2);
            $pieces[$index] = $piece;
        }

        $notes[] = $this->note('tip', $count.' دکمه '.Format::cm($size).'ی روی خط مرکز جلو نشست، '
            .'با فاصله '.Format::cm($spacing).' از هم؛ جادکمه‌ها افقی و کمی جلوتر از خط مرکز علامت خورده‌اند.');
        $notes[] = $this->note('info', 'خط یقه به اندازه اضافه جلو بلندتر شد؛ اگر یقه دارید، دوباره اندازه گرفته می‌شود.');

        return $this->result($pieces, $notes, [
            'buttons' => $count,
            'extension' => $extension,
            'spacing' => round($spacing, 2),
        ]);
    }
}
