<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * بالاتنه یوک‌دار (جلو و پشت).
 *
 * بالای بالاتنه با یک برش افقی جدا می‌شود؛ تکه بالا «یوک» است و تکه پایین با
 * پیلی یا چین به آن دوخته می‌شود. برای اینکه پیلی پارچه اضافه لازم دارد، تکه
 * پایین از یک درفتِ دوم با «اضافه مرکز» بریده می‌شود: لبه مرکزی آن به اندازه
 * عمق پیلی از خط مرکز بیرون می‌زند و پس از تا شدن پیلی، دور تمام‌شده لباس همان
 * چیزی می‌ماند که باید.
 */
class BodiceYokeGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_yoke';
    }

    public function label(): string
    {
        return 'بالاتنه یوک‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['neck_width_extra' => 0.5, 'armhole_depth_extra' => 1.5],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            [
                'back_yoke_depth' => [
                    'label' => 'عمق یوک پشت از سرشانه', 'min' => 6, 'max' => 24, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'front_yoke' => [
                    'label' => 'یوک جلو هم داشته باشد', 'type' => 'toggle', 'default' => true,
                ],
                'front_yoke_depth' => [
                    'label' => 'عمق یوک جلو از سرشانه', 'min' => 5, 'max' => 24, 'step' => 0.5,
                    'default' => 13, 'unit' => 'سانتی‌متر',
                ],
                'fullness_style' => [
                    'label' => 'زیر یوک', 'type' => 'select', 'default' => 'pleat',
                    'options' => ['plain' => 'صاف', 'pleat' => 'پیلی مرکزی', 'gather' => 'چین'],
                ],
                'fullness' => [
                    'label' => 'عمق پیلی یا پُری چین', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => -4, 'max' => 40, 'step' => 1,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'body_length', 4);
        $style = (string) $this->param($params, 'fullness_style', 'pleat');
        $fullness = $style === 'plain' ? 0.0 : (float) $this->param($params, 'fullness', 3);
        $frontYoke = $this->flag($params, 'front_yoke', true);

        $pieces = [];

        $plan = [
            ['side' => 'back', 'depth' => (float) $this->param($params, 'back_yoke_depth', 14)],
            ['side' => 'front', 'depth' => (float) $this->param($params, 'front_yoke_depth', 13)],
        ];

        foreach ($plan as $row) {
            $side = $row['side'];
            $isFront = $side === 'front';

            $shared = [
                'side' => $side,
                'shape' => 'straight',
                'length' => $length,
                'waist_dart' => false,
                'bust_dart' => false,
                'bottom_tag' => 'hem',
            ];

            if ($isFront && ! $frontYoke) {
                $pieces[] = $this->bodyPanel($g, array_merge($shared, [
                    'code' => 'yoke-body-front',
                    'name' => 'تنه جلو',
                ]));

                continue;
            }

            $neckDepth = $isFront ? $g['front_neck_depth'] : $g['back_neck_depth'];
            $yokeY = max($neckDepth + 2.5, min($g['bust_y'] - 2, $row['depth']));

            $plain = $this->bodyPanel($g, $shared);

            [$yoke] = $this->cutPanelAt($plain, $yokeY, [
                'code' => 'yoke-'.$side,
                'name' => $isFront ? 'یوک جلو' : 'یوک پشت',
                'cut' => 1,
                'on_fold' => true,
                'cut_tag' => 'default',
                'lines' => [],
                'center_x' => 0.0,
                'meta' => [
                    'part' => 'yoke',
                    'girth_role' => 'trim',
                    'style_line' => 'yoke',
                    'yoke_depth' => round($yokeY, 2),
                    'waist_y' => null,
                ],
            ]);

            $yoke['meta']['seam_length'] = $this->panelWidthAt($plain, $yokeY);
            $pieces[] = $yoke;

            $wide = $this->bodyPanel($g, array_merge($shared, ['extension' => $fullness, 'on_fold' => true]));
            $parts = $this->cutPanelAt($wide, $yokeY, [], [
                'code' => 'yoke-body-'.$side,
                'name' => $isFront ? 'تنه جلو زیر یوک' : 'تنه پشت زیر یوک',
                'cut' => 1,
                'on_fold' => true,
                'cut_tag' => 'default',
                'lines' => ['bust' => $g['bust_y']],
                'center_x' => $fullness,
                'meta' => [
                    'part' => $isFront ? 'front_bodice' : 'back_bodice',
                    'style_line' => 'yoke',
                    'yoke_depth' => round($yokeY, 2),
                    'waist_y' => round($g['side_waist_y'] - $yokeY, 2),
                ],
            ]);

            $body = $parts[1] ?? $parts[0];

            if ($fullness > 0.4) {
                $body['pleats'][] = [
                    'type' => $style === 'gather' ? 'gather' : 'pleat',
                    'label' => $style === 'gather' ? 'چین زیر یوک' : 'پیلی مرکزی زیر یوک',
                    'edge' => null,
                    'intake' => round($fullness, 2),
                    'from' => Geometry::point(0, 0),
                    'to' => Geometry::point($fullness, 0),
                ];

                $body['meta']['fullness'] = [$style === 'gather' ? 'gather' : 'pleat' => round($fullness * 2, 2)];
                $body['meta']['notes'] = array_merge($body['meta']['notes'] ?? [], [
                    'لبه بالای این قطعه '.$this->fa(round($fullness * 2, 1)).' سانتی‌متر پهن‌تر از لبه یوک است؛ '
                        .($style === 'gather' ? 'این اضافه با چین' : 'این اضافه با یک پیلی جعبه‌ای روی خط مرکز').' جمع می‌شود.',
                ]);
            }

            $pieces[] = $body;
        }

        return $this->finishBlock($pieces, $g);
    }
}
