<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن دخترانه.
 *
 * بالاتنه صاف و بدون ساسون (تن کودک برجستگی سینه ندارد) که روی خط کمر تمام
 * می‌شود، و دامن چین‌دار پُر که به همان خط دوخته می‌گردد. بسته شدن از مرکز پشت
 * است تا از سر رد شود؛ برای همین پشت در دو تکه با اضافه جای دکمه بریده می‌شود.
 * حلقه و یقه با نوار اریب تمیز می‌شوند که برای پارچه‌های نازک کودک بهترین است.
 */
class ChildDressGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'child_dress';
    }

    public function label(): string
    {
        return 'پیراهن دخترانه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['shoulder_slope' => 3, 'neck_width_extra' => 0.5, 'front_neck_depth_extra' => 1, 'back_neck_depth' => 1.5, 'armhole_depth_extra' => 1],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            $this->sleeveParam('none', 18, [
                'none' => 'بدون آستین (نوار حلقه)',
                'set_in' => 'آستین کوتاه',
            ]),
            [
                'skirt_length' => [
                    'label' => 'بلندی دامن از کمر', 'min' => 12, 'max' => 60, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                ],
                'gather_ratio' => [
                    'label' => 'نسبت پُری چین دامن', 'min' => 1.2, 'max' => 2.6, 'step' => 0.1,
                    'default' => 1.8,
                ],
                'back_stand' => [
                    'label' => 'اضافه جای دکمه مرکز پشت', 'min' => 1.5, 'max' => 5, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه پشت', 'min' => 2, 'max' => 8, 'step' => 1, 'default' => 4,
                ],
                'sash' => [
                    'label' => 'بند کمر (پاپیون پشت)', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $stand = (float) $this->param($params, 'back_stand', 2.5);
        $length = (float) $this->param($params, 'skirt_length', 26);
        $ratio = max(1.0, (float) $this->param($params, 'gather_ratio', 1.8));

        $front = $this->bodyPanel($g, [
            'side' => 'front',
            'shape' => 'waist',
            'waist_dart' => false,
            'bust_dart' => false,
            'code' => 'child-dress-bodice-front',
            'name' => 'بالاتنه جلو',
        ]);

        $back = $this->bodyPanel($g, [
            'side' => 'back',
            'shape' => 'waist',
            'waist_dart' => false,
            'bust_dart' => false,
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'code' => 'child-dress-bodice-back',
            'name' => 'بالاتنه پشت (جای دکمه)',
            'meta' => ['button_stand' => round($stand, 2)],
        ]);

        [$front, $back] = $this->walkSideSeams($front, $back);
        $back = $this->markButtons($back, $stand, $g['back_neck_depth'] + 1.5, $g['back_waist_y'] - 1.5, (int) $this->param($params, 'buttons', 4));

        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $measurements,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            ['prefix' => 'child-dress-'],
        ));

        $waistQuarter = $g['quarter_waist'];
        $gather = round($waistQuarter * ($ratio - 1), 2);

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $pieces[] = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'flare',
                'top_width' => $waistQuarter,
                'top_y' => $g['side_waist_y'],
                'length' => $length,
                'gather' => $gather,
                'flare' => round($length * 0.25, 2),
                'top_tag' => 'waist',
                'extension' => $isFront ? 0.0 : $stand,
                'on_fold' => $isFront,
                'cut' => $isFront ? 1 : 2,
                'code' => 'child-dress-skirt-'.$side,
                'name' => $isFront ? 'دامن چین‌دار جلو' : 'دامن چین‌دار پشت',
                'meta' => [
                    'gather_ratio' => $ratio,
                    'notes' => ['لبه کمر این پنل '.$this->fa($ratio).' برابر لبه کمر بالاتنه است و با چین روی آن جمع می‌شود.'],
                ],
            ]);
        }

        $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), ['prefix' => 'child-dress-', 'height' => 3]);
        $pieces[] = $this->bandPiece('child-dress-neck-binding', 'نوار یقه', $this->neckOf([$front, $back]) + 4, 3, [
            'cut' => 1, 'part' => 'facing',
            'meta' => ['bias' => true, 'girth_role' => 'trim', 'notes' => ['نوار اریب دور یقه.']],
        ]);

        if ($this->flag($params, 'sash', true)) {
            $pieces[] = $this->bandPiece('child-dress-sash', 'بند کمر', ($this->m($measurements, 'waist', 55) / 2) + 45, 8, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => ['notes' => ['دو بند روی درز پهلو دوخته و پشت کمر پاپیون می‌شود.']],
            ]);
        }

        return $this->finishBlock($pieces, $g);
    }
}
