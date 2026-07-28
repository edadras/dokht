<?php

namespace App\Services\Pattern\Style\Closure;

use App\Support\Format;

/** زیپ سرتاسری: مرکز جلو کامل باز می‌شود و زیپ دوطرفه رویش می‌نشیند. */
class FullZipClosure extends BaseClosure
{
    public static function key(): string
    {
        return 'closure_zip_full';
    }

    public function label(): string
    {
        return 'زیپ سرتاسری';
    }

    public function description(): string
    {
        return 'مرکز جلو از یقه تا دم باز می‌شود و زیپ جداشونده رویش دوخته می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'guard' => ['label' => 'محافظ پشت زیپ', 'type' => 'toggle', 'default' => true],
            'seam' => [
                'label' => 'جای دوخت لبه زیپ', 'min' => 0.7, 'max' => 3, 'step' => 0.1, 'default' => 1.2, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $seam = $this->num($context, 'seam', 1.2);
        $length = 0.0;

        foreach ($this->frontIndexes($pieces) as $index) {
            $piece = $pieces[$index];

            if (! empty($piece['on_fold'])) {
                // مرکز جلو باید باز شود؛ اضافه‌ای لازم نیست، فقط دیگر روی تا نیست
                $piece['on_fold'] = false;
                $piece['mirror'] = true;
                $piece['cut_quantity'] = 2;
                $piece['meta']['fold_edges'] = [];
                $piece['meta']['opened_center'] = true;
            }

            [$top, $bottom] = $this->centerSpan($piece);
            $length = max($length, round($bottom - $top, 2));
            $edge = $this->centerEdge($piece);

            $piece['meta']['allowance_overrides']['default'] = round($seam, 2);
            $piece['meta']['closure'] = static::key();
            $piece['meta']['notions'][] = [
                'type' => 'zip',
                'label' => 'زیپ جداشونده سرتاسری',
                'length' => $length,
                'count' => 1,
            ];
            $piece['markers'][] = $this->marker('zip', 'خط زیپ مرکز جلو', 0, $top, 0, $bottom);
            $piece['notches'][] = $this->notch(0, $top, $edge ?? 0, 'سر بالای زیپ', 'zip');
            $piece['notches'][] = $this->notch(0, $bottom, $edge ?? 0, 'سر پایین زیپ', 'zip');

            $pieces[$index] = $piece;
        }

        $extra = [];
        $notes = [
            $this->note('tip', 'مرکز جلو باز شد و زیپ جداشونده '.Format::cm($length)
                .'ی رویش می‌نشیند؛ تنه جلو دیگر روی تای پارچه بریده نمی‌شود.'),
            $this->note('warning', 'لبه زیپ '.Format::cm($seam)
                .' جای دوخت می‌خواهد؛ کمتر از این زیپ روی لبه می‌افتد.'),
        ];

        if ($this->flag($context, 'guard', true)) {
            $extra[] = $this->placketStrip(6, $length, 'zip-guard', 'محافظ پشت زیپ', 1);
            $notes[] = $this->note('info', 'یک نوار محافظ ۶ سانتی‌متری پشت زیپ می‌خورد تا دندانه‌ها به تن نگیرد.');
        }

        return $this->result(array_merge($pieces, $extra), $notes, ['zip_length' => $length]);
    }
}
