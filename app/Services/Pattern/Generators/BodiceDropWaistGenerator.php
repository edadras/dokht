<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه کمر افتاده.
 *
 * خط دوخت از کمر پایین‌تر می‌آید و روی باسن کوچک می‌نشیند. چون بالاتنه از روی
 * کمر رد می‌شود، کاهش کمر همه روی درز پهلو گرفته می‌شود و بالاتنه راحت می‌ماند؛
 * پُری لباس از پنل پایین می‌آید که با چین یا کلوش به همان خط دوخته می‌شود.
 */
class BodiceDropWaistGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_drop_waist';
    }

    public function label(): string
    {
        return 'بالاتنه کمر افتاده';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['neck_width_extra' => 0.5, 'front_neck_depth_extra' => 1.5],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            [
                'drop' => [
                    'label' => 'افتادگی خط دوخت از کمر', 'min' => 3, 'max' => 25, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                    'hint' => 'ده سانتی‌متر یعنی خط دوخت روی باسن کوچک می‌نشیند.',
                ],
                'lower_length' => [
                    'label' => 'بلندی پنل پایین', 'min' => 8, 'max' => 90, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                ],
                'lower_style' => [
                    'label' => 'فرم پنل پایین', 'type' => 'select', 'default' => 'gather',
                    'options' => ['gather' => 'چین‌دار', 'flare' => 'کلوش', 'straight' => 'راسته'],
                ],
                'gather_amount' => [
                    'label' => 'پُری چین (هر نیم‌قطعه)', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'flare' => [
                    'label' => 'گشادی هر پهلو در لبه پایین', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $drop = (float) $this->param($params, 'drop', 10);
        $dropY = $g['side_waist_y'] + $drop;
        $style = (string) $this->param($params, 'lower_style', 'gather');
        $gather = $style === 'gather' ? (float) $this->param($params, 'gather_amount', 8) : 0.0;
        $flare = $style === 'flare' ? (float) $this->param($params, 'flare', 8) : 0.0;
        $lowerLength = (float) $this->param($params, 'lower_length', 26);

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $panel = $this->bodyPanel($g, [
                'side' => $side,
                'shape' => 'fitted',
                'length' => $drop,
                'waist_dart' => false,
                'bust_dart' => false,
                'bottom_tag' => 'waist',
                'code' => 'drop-waist-'.$side,
                'name' => $isFront ? 'بالاتنه جلو تا خط افتاده' : 'بالاتنه پشت تا خط افتاده',
                'meta' => ['style_line' => 'drop_waist', 'drop_y' => round($dropY, 2)],
            ]);

            $width = $this->panelWidthAt($panel, $dropY - 0.2);
            $pieces[] = $panel;

            $pieces[] = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => $style === 'gather' ? 'flare' : $style,
                'top_width' => max(4.0, $width),
                'top_y' => $dropY,
                'length' => $lowerLength,
                'gather' => $gather,
                'flare' => $flare,
                'top_tag' => 'waist',
                'code' => 'drop-waist-lower-'.$side,
                'name' => $isFront ? 'پنل پایین جلو' : 'پنل پایین پشت',
                'meta' => ['style_line' => 'drop_waist', 'drop_y' => round($dropY, 2)],
            ]);
        }

        return $this->finishBlock($pieces, $g);
    }
}
