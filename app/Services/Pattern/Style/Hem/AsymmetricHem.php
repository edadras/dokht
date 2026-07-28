<?php

namespace App\Services\Pattern\Style\Hem;

use App\Support\Format;

/**
 * لبه نامتقارن: یک طرف لباس کوتاه و طرف دیگر بلند.
 *
 * برای نامتقارن‌بودن، قطعه‌ای که روی تای پارچه بریده می‌شد باز می‌شود و کامل بریده
 * می‌شود؛ وگرنه دو طرف همیشه قرینه درمی‌آید.
 */
class AsymmetricHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_asymmetric';
    }

    public function label(): string
    {
        return 'لبه نامتقارن';
    }

    public function description(): string
    {
        return 'یک سمت لباس کوتاه و سمت دیگر بلند؛ قطعه‌ها کامل (نه روی تا) بریده می‌شوند.';
    }

    public function paramsSchema(): array
    {
        return [
            'short_side' => [
                'label' => 'کوتاه شدن سمت راست', 'min' => 0, 'max' => 40, 'step' => 0.5, 'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'long_side' => [
                'label' => 'بلند شدن سمت چپ', 'min' => 0, 'max' => 45, 'step' => 0.5, 'default' => 14, 'unit' => 'سانتی‌متر',
            ],
            'allowance' => $this->allowanceParam(1.5),
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $short = $this->num($context, 'short_side', 10);
        $long = $this->num($context, 'long_side', 14);
        $allowance = $this->num($context, 'allowance', 1.5);
        $unfolded = [];

        foreach ($this->hemHostIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $wasFolded = ! empty($piece['on_fold']);

            if ($wasFolded) {
                $piece = $this->unfold($piece);
                $unfolded[] = $piece['name'];
            }

            $edges = $this->edgesWithTag($piece, 'hem');

            if ($edges === []) {
                continue;
            }

            $piece = $this->slantHem($piece, $edges, $long, -$short);
            $piece = $this->setHemAllowance($piece, ($allowance * 2) + 0.4);
            $piece['meta']['hem_style'] = static::key();

            $pieces[$index] = $this->reindexAnchors($piece);
        }

        $notes = [
            $this->note('tip', 'دم لباس نامتقارن شد: یک سمت '.Format::cm($short)
                .' کوتاه‌تر و سمت دیگر '.Format::cm($long).' بلندتر.'),
            $this->note('info', 'شیب لبه روی جلو و پشت یکسان اجرا شده است تا درزهای پهلو هم‌اندازه بمانند.'),
        ];

        if ($unfolded !== []) {
            $notes[] = $this->note('warning', implode('، ', $unfolded)
                .' دیگر روی تای پارچه بریده نمی‌شود؛ قطعه کامل باز شد چون دو طرف با هم فرق دارند. '
                .'پارچه پهن‌تری لازم است.');
        }

        return $this->result($pieces, $notes, [
            'short_side' => round($short, 2),
            'long_side' => round($long, 2),
            'unfolded' => $unfolded,
        ]);
    }
}
