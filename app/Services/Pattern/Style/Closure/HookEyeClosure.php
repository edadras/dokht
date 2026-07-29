<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/**
 * قزن (قزن‌قفلی).
 *
 * بستِ نامرئی لباس مجلسی و کمر دامن و شلوار: یک طرف قلاب و طرف دیگر گیره، بدون
 * جادکمه و بدون دیده شدن روی رو. مرکز جلو فقط به اندازه جای دوخت اضافه می‌گیرد
 * (نه اضافه دکمه)، چون دو لبه لب‌به‌لب می‌ایستند و روی هم نمی‌آیند.
 */
class HookEyeClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_hook_eye';
    }

    public function label(): string
    {
        return 'قزن';
    }

    public function description(): string
    {
        return 'قلاب و گیره روی مرکز جلو یا کمر؛ از رو دیده نمی‌شود و جادکمه نمی‌خواهد.';
    }

    public function paramsSchema(): array
    {
        return [
            'hooks' => [
                'label' => 'تعداد قزن', 'min' => 1, 'max' => 30, 'step' => 1, 'default' => 5,
                'hint' => 'هرچه پارچه سنگین‌تر و لبه بلندتر باشد، قزن بیشتری لازم است.',
            ],
            'top_gap' => [
                'label' => 'فاصله اولین قزن از بالا', 'min' => 0, 'max' => 12, 'step' => 0.5,
                'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'bottom_gap' => [
                'label' => 'فاصله آخرین قزن از پایین', 'min' => 0, 'max' => 40, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'tape' => [
                'label' => 'نوار قزن آماده', 'type' => 'toggle', 'default' => false,
                'hint' => 'به‌جای قزن تکی، نوار قزن‌دار روی هر دو لبه دوخته می‌شود.',
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(1, (int) $this->num($context, 'hooks', 5));
        $topGap = $this->num($context, 'top_gap', 1.5);
        $bottomGap = $this->num($context, 'bottom_gap', 4);
        $tape = $this->flag($context, 'tape', false);

        $notes = [];
        $span = 0.0;

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $pieces[$index];

            // دو لبه لب‌به‌لب می‌ایستند، پس مرکز جلو باید باز شود ولی اضافه دکمه
            // نمی‌خواهد؛ همان جای دوخت معمولی کافی است.
            if (! empty($piece['on_fold'])) {
                $piece['on_fold'] = false;
                $piece['mirror'] = true;
                $piece['cut_quantity'] = 2;
                $piece['meta']['fold_edges'] = [];
                $piece['meta']['opened_center'] = true;

                $notes[] = $this->note('info', 'مرکز جلو باز شد؛ «'.$piece['name']
                    .'» دو تکه (چپ و راست) بریده می‌شود و دو لبه لب‌به‌لب با قزن بسته می‌شوند.');
            }

            [$top, $bottom] = $this->centerSpan($piece);
            $edge = $this->centerEdge($piece);
            $first = $top + $topGap;
            $last = max($first + 1, $bottom - $bottomGap);
            $span = max($span, round($last - $first, 2));
            $step = $count > 1 ? ($last - $first) / ($count - 1) : 0.0;

            for ($i = 0; $i < $count; $i++) {
                $y = $first + ($step * $i);
                $piece['drills'][] = $this->drill(0.0, $y, 'hook', 'قزن '.($i + 1));
                $piece['notches'][] = $this->notch(0.0, $y, $edge ?? 0, 'جای قزن '.($i + 1), 'hook');
            }

            $piece['markers'][] = $this->marker('hook_line', 'خط قزن', 0, $first, 0, $last);
            $piece['meta']['closure'] = static::key();

            $piece['meta']['notions'][] = $tape
                ? ['type' => 'hook', 'label' => 'نوار قزن', 'count' => 1, 'length' => round($last - $first, 1)]
                : ['type' => 'hook', 'label' => 'قزن و گیره', 'count' => $count];

            $pieces[$index] = $piece;
        }

        $notes[] = $this->note('tip', $tape
            ? 'نوار قزن به بلندی '.Format::cm($span).' روی هر دو لبه مرکز جلو دوخته می‌شود.'
            : 'قلاب روی لبه راست و گیره روی لبه چپ دوخته می‌شود تا از رو دیده نشود.');
        $notes[] = $this->note('warning', 'پشت هر قزن باید لایی بخورد؛ وگرنه پارچه در جای قزن کش می‌آید و چروک می‌ماند.');

        return $this->result($pieces, $notes, ['hooks' => $count]);
    }
}
