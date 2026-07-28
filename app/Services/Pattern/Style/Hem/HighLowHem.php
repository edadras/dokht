<?php

namespace App\Services\Pattern\Style\Hem;

use App\Support\Format;

/**
 * لبه های‌لو: جلو کوتاه و پشت بلند.
 *
 * فقط مرکز جلو و مرکز پشت جابه‌جا می‌شوند و نقطه پهلو سر جایش می‌ماند، پس درز
 * پهلو دست‌نخورده و هم‌اندازه می‌ماند.
 */
class HighLowHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_high_low';
    }

    public function label(): string
    {
        return 'لبه های‌لو';
    }

    public function description(): string
    {
        return 'جلو کوتاه‌تر و پشت بلندتر؛ نقطه پهلو ثابت می‌ماند تا درز پهلو نخورد.';
    }

    public function paramsSchema(): array
    {
        return [
            'front_rise' => [
                'label' => 'کوتاه شدن جلو', 'min' => 0, 'max' => 30, 'step' => 0.5, 'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'back_drop' => [
                'label' => 'بلند شدن پشت', 'min' => 0, 'max' => 40, 'step' => 0.5, 'default' => 12, 'unit' => 'سانتی‌متر',
            ],
            'curve' => ['label' => 'گودی منحنی', 'min' => 0, 'max' => 10, 'step' => 0.5, 'default' => 3, 'unit' => 'سانتی‌متر'],
            'allowance' => $this->allowanceParam(1.5),
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        $hosts = $this->hemHostIndexes($pieces);

        if ($hosts === []) {
            return 'این لباس لبه‌ای برای شکل‌دادن ندارد.';
        }

        $sides = [];

        foreach ($hosts as $index) {
            $sides[] = $pieces[$index]['meta']['side'] ?? null;
        }

        if (! in_array('front', $sides, true) || ! in_array('back', $sides, true)) {
            return 'لبه های‌لو به قطعه جلو و پشت با هم نیاز دارد؛ این لباس یکی از آن‌ها را ندارد.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $rise = $this->num($context, 'front_rise', 8);
        $drop = $this->num($context, 'back_drop', 12);
        $curve = $this->num($context, 'curve', 3);
        $allowance = $this->num($context, 'allowance', 1.5);

        foreach ($this->hemHostIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');

            if ($edge === null) {
                continue;
            }

            $isBack = ($piece['meta']['side'] ?? 'front') === 'back';
            $delta = $isBack ? $drop : -$rise;

            $piece = $this->shapeHem($piece, $edge, $delta, 0.0, $isBack ? $curve : -$curve);
            $piece = $this->setHemAllowance($piece, ($allowance * 2) + 0.4);
            $piece['meta']['hem_style'] = static::key();
            $piece['meta']['hem_delta'] = round($delta, 2);

            $pieces[$index] = $this->reindexAnchors($piece);
        }

        return $this->result($pieces, [
            $this->note('tip', 'دم لباس های‌لو شد: مرکز جلو '.Format::cm($rise)
                .' بالا آمد و مرکز پشت '.Format::cm($drop).' پایین رفت.'),
            $this->note('info', 'نقطه پهلو تکان نخورده است، پس طول درز پهلوی جلو و پشت هنوز برابر است.'),
            $this->fabricNote($drop + 2, 'بلندتر شدن پشت لباس'),
        ], ['front_rise' => round($rise, 2), 'back_drop' => round($drop, 2)]);
    }
}
