<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن پیلی آکاردئونی.
 *
 * پیلی‌های ریز و پشت‌سرهم که با «نسبت پُری» تعریف می‌شوند: پارچه به اندازه
 * نسبت × اندازه تمام‌شده بریده می‌شود و عمق هر پیلی از همان‌جا درمی‌آید:
 *   عمق = (پارچه − تمام‌شده) ÷ (۲ × تعداد پیلی)
 */
class SkirtAccordionGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_pleat_accordion';
    }

    public function label(): string
    {
        return 'دامن پیلی آکاردئونی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(62, 30, 115),
            [
                'fullness' => [
                    'label' => 'نسبت پُری پارچه', 'min' => 1.6, 'max' => 3.5, 'step' => 0.1, 'default' => 2.6,
                    'hint' => 'پارچه چند برابر اندازه تمام‌شده بریده شود.',
                ],
                'pleat_count' => [
                    'label' => 'تعداد پیلی دور دامن', 'min' => 12, 'max' => 80, 'step' => 4, 'default' => 40,
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم دامن روی باسن', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 6,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 62);
        $ratio = max(1.2, (float) $this->param($params, 'fullness', 2.6));
        $count = max(4, ((int) round(((float) $this->param($params, 'pleat_count', 40)) / 4)) * 4);

        $finishedHem = $mx['hip_target'] + max(0.0, (float) $this->param($params, 'hem_ease', 6));
        $fabric = $finishedHem * $ratio;
        $depth = ($fabric - $finishedHem) / (2 * $count);

        $note = 'پهنای دم دامن روی پارچه '.$this->fa(round($fabric, 1)).' سانتی‌متر است: '
            .$this->fa(round($finishedHem, 1)).' سانتی‌متر پهنای تمام‌شده + '
            .$this->fa(round($fabric - $finishedHem, 1)).' سانتی‌متر جای پیلی ('
            .$this->fa($count).' پیلی × '.$this->fa(round(2 * $depth, 2)).' سانتی‌متر).';

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->pleatedRectPanel([
                'side' => $side,
                'part' => $side === 'front' ? 'skirt_front' : 'skirt_back',
                'code' => 'accordion-'.$side,
                'name' => $side === 'front' ? 'دامن آکاردئونی جلو' : 'دامن آکاردئونی پشت',
                'width' => $fabric / 4,
                'length' => $length,
                'pleats' => max(1, (int) ($count / 4)),
                'depth' => $depth,
                'style' => 'accordion',
                'finished_waist' => $mx['waist_target'] / 4,
                'waist_target' => $mx['waist_target'],
                'hip_y' => round($mx['hip_y'], 2),
                'notes' => [$note, 'پیلی آکاردئونی روی پارچه دوخته‌نشده پرس می‌شود؛ درز پهلو پیش از پرس بسته شود.'],
            ]);
        }

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }
}
