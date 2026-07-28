<?php

namespace App\Services\Pattern\Generators;

/**
 * پالتو کلاسیک.
 *
 * لباس رویی سنگین که روی لباس دیگر پوشیده می‌شود، پس آزادی حلقه و بازو بیشتر
 * است. تنه با درز مرکز پشت فرم می‌گیرد، آستین دوتکه خیاطی (آستین رو و آستین
 * زیر) از آرنج می‌شکند، برگردان یقه با سجاف یک‌سره می‌شود و همه تنه آستر می‌خورد.
 */
class CoatClassicGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'coat_classic';
    }

    public function label(): string
    {
        return 'پالتو کلاسیک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 5, 'neck_width_extra' => 2.5, 'shoulder_slope' => 4, 'front_neck_depth_extra' => 3]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(60, 25, 120),
            $this->openingParam('button', 3),
            $this->collarParam('shawl', [
                'shawl' => 'یقه شالی با سجاف یک‌سره',
                'turn' => 'یقه برگردان',
                'stand' => 'یقه ایستاده',
            ], 9),
            $this->sleeveParam('two_piece', 60),
            [
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 22, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'back_vent' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 45, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'collar_break' => [
                    'label' => 'محل شکست یقه از خط سینه', 'min' => 0, 'max' => 25, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 16, 18),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.5, 'regular' => 3.0, 'loose' => 5.0]);
        $length = (float) $this->param($params, 'length', 60);
        $vent = (float) $this->param($params, 'back_vent', 24);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'coat-',
            'grow' => $grow,
            'shape' => 'fitted',
            'back_seam' => true,
            'bust_dart' => true,
            'front_name' => 'تنه جلوی پالتو',
            'back_name' => 'تنه پشت پالتو (درز مرکزی)',
            'facing_width' => 12,
            'collar_break' => (float) $this->param($params, 'collar_break', 10),
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 3), 'back_pleat' => 2.5],
        ]);

        if ($vent > 1) {
            $pieces[1]['meta']['back_vent'] = round($vent, 2);
            $pieces[1]['meta']['notes'] = array_merge($pieces[1]['meta']['notes'] ?? [], [
                'چاک مرکز پشت به بلندی '.$this->fa(round($vent, 1)).' سانتی‌متر باز می‌ماند؛ پهنای زیرِ چاک چهار سانتی‌متر است.',
            ]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'coat-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
