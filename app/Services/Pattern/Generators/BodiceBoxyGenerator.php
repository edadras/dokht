<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه گشاد (جعبه‌ای).
 *
 * درز پهلو از زیر بغل تا پایین صاف می‌آید و هیچ کمرگیری ندارد؛ سرشانه افتاده و
 * حلقه آستین گودتر است تا لباس روی تن آزاد بایستد. همان بلوکی که زیربنای بلوز
 * اورسایز، تیشرت جعبه‌ای و مانتوی راسته است.
 */
class BodiceBoxyGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_boxy';
    }

    public function label(): string
    {
        return 'بالاتنه گشاد (جعبه‌ای)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['neck_width_extra' => 1.5, 'front_neck_depth_extra' => 1.5, 'armhole_depth_extra' => 3, 'shoulder_slope' => 3.5],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            $this->fitParam('loose'),
            [
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => -6, 'max' => 45, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'shoulder_extra' => [
                    'label' => 'افتادگی سرشانه', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'سرشانه از استخوان شانه بیرون‌تر می‌رود و آستین پایین‌تر می‌نشیند.',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین', 'min' => -4, 'max' => 14, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 20, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    /** اضافه یک‌چهارم بر پایه فرم انتخابی؛ بلوک جعبه‌ای پیش‌فرض گشادتر است. */
    protected function boxyGrow(array $params): float
    {
        return $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 2.0, 'loose' => 4.5]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->boxyGrow($params);
        $length = (float) $this->param($params, 'body_length', 6);
        $vent = (float) $this->param($params, 'side_vent', 0);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'grow' => $grow,
            'waist_dart' => false,
            'bust_dart' => false,
            'shoulder_extra' => (float) $this->param($params, 'shoulder_extra', 3),
            'hem_flare' => (float) $this->param($params, 'hem_flare', 0),
            'bottom_tag' => 'hem',
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'boxy-front',
            'name' => 'بالاتنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'boxy-back',
            'name' => 'بالاتنه پشت',
        ]));

        $front = $this->markVent($front, $vent);
        $back = $this->markVent($back, $vent);

        return $this->finishBlock([$front, $back], $g, $grow);
    }

    /**
     * چاک پهلو روی لبه پایین.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markVent(array $piece, float $length): array
    {
        if ($length < 1) {
            return $piece;
        }

        $edges = $piece['meta']['side_edges'] ?? [];
        $edge = $edges === [] ? null : (int) end($edges);

        if ($edge === null) {
            return $piece;
        }

        $piece['meta']['vent'] = round($length, 2);
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'چاک پهلو به بلندی '.$this->fa(round($length, 1)).' سانتی‌متر از لبه پایین باز می‌ماند.',
        ]);

        return $piece;
    }
}
