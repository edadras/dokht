<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه امپایر.
 *
 * خط کمر لباس از کمر واقعی بالا می‌آید و درست زیر سینه می‌نشیند؛ از آنجا به
 * پایین پارچه آزاد می‌شود. بالاتنه با یک برش افقی از بلوک جدا می‌شود و پنل پایین
 * دقیقاً هم‌اندازه لبه همان برش درفت می‌گردد، پس درز امپایر بدون کش آمدن بسته
 * می‌شود. زیر سینه یا ساسون می‌خورد، یا چین می‌خورد، یا صاف می‌ماند.
 */
class BodiceEmpireGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_empire';
    }

    public function label(): string
    {
        return 'بالاتنه امپایر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['front_neck_depth_extra' => 2],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            [
                'empire_rise' => [
                    'label' => 'بالا آمدن خط امپایر از کمر', 'min' => 4, 'max' => 22, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                    'hint' => 'خط امپایر همیشه دست‌کم سه سانتی‌متر زیر خط سینه می‌ماند.',
                ],
                'body_length' => [
                    'label' => 'بلندی از خط کمر به پایین', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'under_bust' => [
                    'label' => 'زیر سینه', 'type' => 'select', 'default' => 'gather',
                    'options' => ['gather' => 'چین‌دار', 'dart' => 'ساسون زیر سینه', 'plain' => 'صاف'],
                ],
                'gather_amount' => [
                    'label' => 'پُری چین زیر سینه (هر نیم‌قطعه)', 'min' => 0, 'max' => 14, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'lower_style' => [
                    'label' => 'فرم پنل پایین', 'type' => 'select', 'default' => 'flare',
                    'options' => ['flare' => 'کلوش', 'straight' => 'راسته', 'fitted' => 'کمرگیر'],
                ],
                'flare' => [
                    'label' => 'گشادی هر پهلو در لبه پایین', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);

        $empireY = max(
            $g['bust_y'] + 3,
            min($g['side_waist_y'] - 2, $g['side_waist_y'] - (float) $this->param($params, 'empire_rise', 12)),
        );

        $style = (string) $this->param($params, 'under_bust', 'gather');
        $gather = $style === 'gather' ? (float) $this->param($params, 'gather_amount', 4) : 0.0;
        $dart = $style === 'dart' ? round($g['bust_dart_intake'] * 0.8, 2) : 0.0;
        $lowerShape = (string) $this->param($params, 'lower_style', 'flare');
        $flare = $lowerShape === 'straight' ? 0.0 : (float) $this->param($params, 'flare', 6);
        $lowerLength = $g['side_waist_y'] - $empireY + (float) $this->param($params, 'body_length', 10);

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $panel = $this->bodyPanel($g, [
                'side' => $side,
                'shape' => 'waist',
                'waist_dart' => false,
                'bust_dart' => false,
            ]);

            [$upper] = $this->cutPanelAt($panel, $empireY, [
                'code' => 'empire-'.$side,
                'name' => $isFront ? 'بالاتنه جلو (بالای امپایر)' : 'بالاتنه پشت (بالای امپایر)',
                'cut' => 1,
                'on_fold' => true,
                'cut_tag' => 'waist',
                'lines' => ['bust' => $g['bust_y']],
                'center_x' => 0.0,
                'meta' => [
                    'part' => $isFront ? 'front_bodice' : 'back_bodice',
                    'style_line' => 'empire',
                    'empire_y' => round($empireY, 2),
                    'waist_y' => null,
                ],
            ]);

            $seam = (float) ($upper['meta']['lengths']['waist'] ?? $g['quarter_bust']);

            if ($isFront && $dart > 0.6) {
                $upper['darts'][] = $this->dart(
                    'bust',
                    'ساسون زیر سینه',
                    null,
                    $g['bust_apex_x'],
                    $empireY,
                    $dart,
                    $g['bust_apex_x'],
                    $g['bust_apex_y'],
                );
                $upper['meta']['under_bust_dart'] = $dart;
            }

            $upper['meta']['seam_length'] = round($seam, 2);
            $pieces[] = $upper;

            $pieces[] = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => $lowerShape,
                'top_width' => max(4.0, $seam - ($isFront ? $dart : 0.0)),
                'top_y' => $empireY,
                'length' => $lowerLength,
                'gather' => $isFront ? $gather : $gather * 0.6,
                'flare' => $flare,
                'code' => 'empire-lower-'.$side,
                'name' => $isFront ? 'پنل پایین جلو' : 'پنل پایین پشت',
                'top_tag' => 'waist',
                'meta' => ['style_line' => 'empire', 'empire_y' => round($empireY, 2)],
            ]);
        }

        return $this->finishBlock($pieces, $g);
    }
}
