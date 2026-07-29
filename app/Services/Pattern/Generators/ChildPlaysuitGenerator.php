<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * سرهمی کودک.
 *
 * بالاتنه و شورت که روی خط کمر به هم می‌رسند، بدون هیچ بستی جز کش کمر: کودکِ
 * نوپا زیپ و دکمه را خودش باز نمی‌کند، پس این سرهمی از سر پوشیده می‌شود و
 * همه بار «رد شدن از سر» روی یقه است.
 *
 * دو قاعده این خانواده این‌جا با هم روبه‌رو می‌شوند و هر دو رعایت شده‌اند:
 *
 *   لبه کمر بالاتنه باید دقیقاً به اندازه لبه کمر شورت باشد، پس بالاتنه ساسون
 *   کمر نمی‌گیرد و همان آزادی به شورت هم می‌رسد. کش کمر پهنای الگو را عوض
 *   نمی‌کند؛ فقط کوتاه‌تر از لبه بریده می‌شود و لبه را جمع می‌کند.
 */
class ChildPlaysuitGenerator extends ChildBaseGenerator
{
    public static function key(): string
    {
        return 'child_playsuit';
    }

    public function label(): string
    {
        return 'سرهمی کودک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->childSchema(['neck_width_extra' => 2, 'armhole_depth_extra' => 2.5]),
            $this->childEaseSchema(1.5, 2),
            $this->sleeveParam('set_in', 14, [
                'set_in' => 'آستین کوتاه',
                'none' => 'بی‌آستین (نوار حلقه)',
            ]),
            [
                'rise_ease' => [
                    'label' => 'آزادی قد فاق', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'کودک بیشتر وقتش را نشسته و چهاردست‌وپا می‌گذراند؛ فاق تنگ لباس را بالا می‌کشد.',
                ],
                'short_length' => [
                    'label' => 'بلندی پاچه از فاق', 'min' => 4, 'max' => 30, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم پاچه روی ران', 'min' => 2, 'max' => 20, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'elastic_ratio' => [
                    'label' => 'کوتاهی کش کمر', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                    'default' => 0.82,
                ],
                'leg_elastic' => [
                    'label' => 'کش دم پاچه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // شکم کودک جلو آمده و کمرش فرورفتگی ندارد؛ قد بالاتنه را هم به همان
        // اندازه آزادی فاق بلندتر می‌گیریم تا سرهمی روی سرشانه بالا نکشد
        $rise = (float) $this->param($params, 'rise_ease', 3);
        $params = array_merge($params, [
            'bodice_length_extra' => (float) $this->param($params, 'bodice_length_extra', 0) + ($rise / 2),
        ]);

        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);
        $clearance = $this->headClearance($g, $measurements, ['margin' => 2.0, 'max_depth' => 4.0]);

        $shared = [
            'shape' => 'waist',
            'grow' => $grow,
            'waist_dart' => false,
            'bust_dart' => false,
            'neck_width_extra' => $clearance['width_extra'],
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'neck_depth_extra' => $clearance['front_depth_extra'],
            'code' => 'child-playsuit-bodice-front',
            'name' => 'بالاتنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'child-playsuit-bodice-back',
            'name' => 'بالاتنه پشت',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);

        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $measurements,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            ['prefix' => 'child-playsuit-'],
        ));

        $legEase = $this->legEase($ease, $grow);

        foreach (['front', 'back'] as $side) {
            $leg = $this->childShortPanel($measurements, $legEase, $params, [
                'side' => $side,
                'code' => 'child-playsuit-leg-'.$side,
                'name' => $side === 'front' ? 'شورت جلو' : 'شورت پشت',
            ]);

            $leg['meta']['girth_role'] = 'bottom';
            $leg['meta']['notes'] = array_merge($leg['meta']['notes'] ?? [], [
                'لبه کمر این قطعه به لبه کمر بالاتنه دوخته می‌شود؛ هر دو با یک آزادی درفت شده‌اند.',
            ]);

            $pieces[] = $leg;
        }

        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.82)));
        $pieces[] = $this->elasticWaistPiece('child-playsuit-waist-elastic', $measurements, $ease, $ratio, 2.5);

        if ($this->flag($params, 'leg_elastic', true)) {
            $hem = 0.0;

            foreach ($pieces as $piece) {
                if (($piece['meta']['girth_role'] ?? '') === 'bottom') {
                    $hem += (float) ($piece['meta']['hem_width'] ?? 0);
                }
            }

            $pieces[] = $this->bandPiece('child-playsuit-leg-elastic', 'نوار کش دم پاچه', max(12.0, $hem * 0.85), 2.5, [
                'cut' => 2, 'part' => 'waistband',
                'meta' => [
                    'stretch_ratio' => 0.85,
                    'target_length' => round($hem, 2),
                    'girth_role' => 'trim',
                    'notes' => ['برای هر پاچه یک کش؛ دم پاچه را دور ران جمع می‌کند.'],
                ],
            ]);
        }

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), [
                'prefix' => 'child-playsuit-', 'height' => 3,
            ]);
        }

        $pieces[] = $this->bandPiece('child-playsuit-neck-binding', 'نوار یقه', $this->neckOf([$front, $back]) + 3, 3, [
            'cut' => 2, 'part' => 'facing',
            'meta' => ['bias' => true, 'girth_role' => 'trim', 'notes' => ['نوار اریب دور یقه.']],
        ]);

        $pieces = $this->stampHeadClearance($pieces, $clearance, $g, ['notion' => 'snap']);

        return $this->finishBlock($this->childNoted($pieces, [
            'کش کمر پهنای الگو را عوض نمی‌کند؛ لبه کمر به اندازه بدن بریده و کش کوتاه‌تر روی آن دوخته می‌شود.',
        ]), $g, $grow);
    }

    /**
     * شورت کودک: همان لبه کمرِ پاچه، بدون خط زانو.
     *
     * پاچه بلندِ استاندارد قد داخل پا را دست‌کم سی سانتی‌متر می‌گیرد و برای پای
     * کودک اصلاً کوتاه نمی‌شود؛ پس شورت این‌جا مستقل درفت می‌شود.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function childShortPanel(array $m, array $ease, array $params, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $hip = $this->m($m, 'hip', 64) + $this->ease($ease, 'hip', 6);
        $waist = $this->m($m, 'waist', 56) + $this->ease($ease, 'waist', 4);

        $quarterHip = max(12.0, $hip / 4);
        $quarterWaist = max(9.0, $waist / 4);
        $crotchDepth = ($hip / 4) + 2.5 + (float) $this->param($params, 'rise_ease', 3);
        $hipY = min($crotchDepth - 4, max(8.0, $this->m($m, 'waist_to_hip', 14)));

        $panelWidth = $quarterHip + ($isFront ? -1 : 1);
        $crotchExtension = $isFront ? $hip / 16 : $hip / 10;
        $waistWidth = min($panelWidth, $quarterWaist + ($isFront ? -0.5 : 0.5));
        $backRise = $isFront ? 0.0 : 1.5;

        $legLength = max(4.0, (float) $this->param($params, 'short_length', 10));
        $hemTotal = $this->m($m, 'thigh', $hip * 0.58) + (float) $this->param($params, 'hem_ease', 6);
        $hemWidth = max(9.0, ($hemTotal / 2) + ($isFront ? -1 : 1));

        $centerX = ($crotchExtension + $panelWidth) / 2;
        $sideX = $crotchExtension + $panelWidth;
        $hemY = $crotchDepth + $legLength;
        $hemOuter = max($centerX + 3.5, $centerX + ($hemWidth / 2));
        $hemInner = max(0.5, $hemOuter - $hemWidth);

        $outline = [
            Geometry::point($crotchExtension, 0),
            Geometry::point($crotchExtension + $waistWidth, $backRise),
            Geometry::curve($sideX, $hipY, $crotchExtension + $waistWidth + (($sideX - $crotchExtension - $waistWidth) * 0.6), $hipY * 0.45),
            Geometry::point($hemOuter, $hemY),
            Geometry::point($hemInner, $hemY),
            Geometry::curve(0, $crotchDepth, max(0.2, $hemInner - 0.4), $crotchDepth + (($hemY - $crotchDepth) * 0.45)),
            Geometry::curve($crotchExtension, $hipY, $crotchExtension * ($isFront ? 0.2 : 0.32), $crotchDepth * 0.99),
        ];

        return $this->piece([
            'code' => $o['code'] ?? ($isFront ? 'child-short-front' : 'child-short-back'),
            'name' => $o['name'] ?? ($isFront ? 'شورت جلو' : 'شورت پشت'),
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($centerX, $hipY, $hemY - 1.5),
            'notches' => [
                $this->notch($sideX, $hipY, 2, 'نشانه باسن روی پهلو', 'side'),
                $this->notch(0, $crotchDepth, 5, 'نقطه فاق', 'crotch'),
            ],
            'markers' => [
                $this->marker('hip', 'خط باسن', $crotchExtension, $hipY, $sideX),
                $this->marker('crotch', 'خط فاق', 0, $crotchDepth, $sideX),
                $this->marker(
                    $isFront ? 'cf' : 'cb',
                    $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت',
                    $crotchExtension,
                    0,
                    $crotchExtension,
                    $hipY,
                ),
            ],
            'meta' => [
                'part' => $isFront ? 'front_leg' : 'back_leg',
                'edges' => ['waist', 'side', 'side', 'hem', 'side', 'side', 'default'],
                'fold_edges' => [],
                'side' => $isFront ? 'front' : 'back',
                'crotch_depth' => round($crotchDepth, 2),
                'hem_y' => round($hemY, 2),
                'panel_width' => round($panelWidth, 2),
                'hem_width' => round($hemWidth, 2),
                'leg_length' => round($legLength, 2),
            ],
        ]);
    }
}
