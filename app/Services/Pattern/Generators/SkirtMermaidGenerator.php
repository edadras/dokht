<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن ماهی.
 *
 * از کمر تا زیر زانو قالب بدن است و از آنجا یک‌باره باز می‌شود. نقطه شروع کلوش و
 * تنگی همان نقطه پارامتر است، پس فاصله «تنگ» و «باز» را خیاط تعیین می‌کند.
 */
class SkirtMermaidGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_mermaid';
    }

    public function label(): string
    {
        return 'دامن ماهی';
    }

    /** پیش‌فرض‌ها: قد، نسبت شروع کلوش به قد، تنگی و گشادی. */
    protected function defaults(): array
    {
        return ['length' => 95, 'start' => 0.68, 'taper' => 3.0, 'flare' => 22.0];
    }

    public function paramsSchema(): array
    {
        $d = $this->defaults();

        return array_merge(
            $this->lengthParam($d['length'], 60, 145),
            [
                'flare_start' => [
                    'label' => 'شروع کلوش از کمر', 'min' => 25, 'max' => 110, 'step' => 1,
                    'default' => round($d['length'] * $d['start']), 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط کمر تا جایی که دامن باز می‌شود.',
                ],
                'thigh_taper' => [
                    'label' => 'تنگی ران و زانو', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => $d['taper'],
                    'unit' => 'سانتی‌متر', 'hint' => 'هر پهلو این‌قدر از خط باسن تنگ‌تر می‌شود.',
                ],
                'flare' => [
                    'label' => 'گشادی دم دامن', 'min' => 5, 'max' => 45, 'step' => 1, 'default' => $d['flare'],
                    'unit' => 'سانتی‌متر',
                ],
                'vent_length' => [
                    'label' => 'بلندی چاک پشت', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.6, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $d = $this->defaults();
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', $d['length']);
        $start = min((float) $this->param($params, 'flare_start', $length * $d['start']), $length - 8);
        $taper = -abs((float) $this->param($params, 'thigh_taper', $d['taper']));
        $flare = (float) $this->param($params, 'flare', $d['flare']);

        $shared = [
            'length' => $length,
            'flare_from' => $start,
            'flare_waist' => $taper,
            'hem_delta' => $flare,
        ];

        $pieces = [
            $this->blockPanel($mx, array_merge($shared, ['side' => 'front', 'dart_count' => 1])),
            $this->blockPanel($mx, array_merge($shared, [
                'side' => 'back',
                'dart_count' => 2,
                'vent' => (float) $this->param($params, 'vent_length', 0),
            ])),
        ];

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }
}
