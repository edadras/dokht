<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/** بست بندی: دو ردیف سوراخ (اویه) روی مرکز جلو که با بند بسته می‌شود. */
class LacingClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_lacing';
    }

    public function label(): string
    {
        return 'بست بندی';
    }

    public function description(): string
    {
        return 'دو ردیف سوراخ روی مرکز جلو که با بند بسته می‌شود؛ اندازه‌اش قابل تنظیم می‌ماند.';
    }

    public function paramsSchema(): array
    {
        return [
            'eyelets' => ['label' => 'تعداد سوراخ هر طرف', 'min' => 3, 'max' => 16, 'step' => 1, 'default' => 7],
            'gap' => [
                'label' => 'فاصله دو لبه از هم', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'بند این فاصله را می‌بندد؛ همین مقدار از دور لباس کم می‌شود.',
            ],
            'inset' => [
                'label' => 'فاصله سوراخ از لبه', 'min' => 1, 'max' => 4, 'step' => 0.25, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'top_gap' => ['label' => 'فاصله از یقه', 'min' => 0.5, 'max' => 15, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر'],
            'bottom_gap' => ['label' => 'فاصله از دم', 'min' => 0.5, 'max' => 25, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر'],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $eyelets = max(3, (int) $this->num($context, 'eyelets', 7));
        $gap = $this->num($context, 'gap', 3);
        $inset = $this->num($context, 'inset', 1.5);
        $topGap = $this->num($context, 'top_gap', 2);
        $bottomGap = $this->num($context, 'bottom_gap', 4);

        $spacing = 0.0;

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $extension = round($inset + 1.5, 2);
            $piece = $this->addFrontExtension($piece, $extension);

            [$top, $bottom] = $this->centerSpan($piece);
            $first = $top + $topGap;
            $last = max($first + 1, $bottom - $bottomGap);

            $piece = $this->buttonRow($piece, $inset, $first, $last, $eyelets, 0.6, 'سوراخ بند', holes: false);
            $spacing = (float) ($piece['meta']['buttons'][0]['spacing'] ?? 0);

            $piece['meta']['closure'] = static::key();
            $piece['meta']['lacing_gap'] = round($gap, 2);
            $piece['meta']['notions'][] = [
                'type' => 'cord',
                'label' => 'بند',
                'length' => round((($last - $first) * 2.4) + 40, 2),
                'count' => 1,
            ];

            $pieces[$index] = $piece;
        }

        return $this->result($pieces, [
            $this->note('tip', $eyelets.' سوراخ در هر طرف با فاصله '.Format::cm($spacing)
                .' و '.Format::cm($inset).' از لبه علامت خورد.'),
            $this->note('warning', 'دو لبه '.Format::cm($gap).' از هم فاصله دارند، پس دور لباس '
                .Format::cm($gap).' کمتر از دور بسته‌شده است؛ اگر تنگ می‌شود آزادی سینه را بیشتر کنید.'),
            $this->note('info', 'پشت ردیف سوراخ‌ها حتماً لایی بچسبانید تا پارچه پاره نشود.'),
        ], ['eyelets' => $eyelets, 'gap' => round($gap, 2)]);
    }
}
