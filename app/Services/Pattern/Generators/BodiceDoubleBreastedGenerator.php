<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه دوطرفه‌دکمه.
 *
 * جلوی لباس به اندازه هم‌پوشانی از خط مرکز جلو بیرون می‌زند و دو رج دکمه روی آن
 * می‌نشیند. این اضافه در دور تمام‌شده حساب نمی‌شود (چون روی هم می‌افتد)، پس دور
 * سینه و کمر همان «دور بدن + آزادی» می‌ماند. سجاف جلو و سجاف یقه پشت هم ساخته
 * می‌شود، چون بدون آن لبه دکمه‌خور فرم نمی‌گیرد.
 */
class BodiceDoubleBreastedGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_double_breasted';
    }

    public function label(): string
    {
        return 'بالاتنه دوطرفه‌دکمه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(['neck_width_extra' => 1, 'waist_dart_share' => 0.5]),
            [
                'overlap' => [
                    'label' => 'هم‌پوشانی جلو از خط مرکز', 'min' => 3, 'max' => 14, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                    'hint' => 'فاصله رج دکمه تا خط مرکز جلو؛ روی لباس دو برابر این عدد دیده می‌شود.',
                ],
                'button_rows' => [
                    'label' => 'تعداد دکمه در هر رج', 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 3,
                ],
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => 0, 'max' => 45, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'facing_width' => [
                    'label' => 'پهنای سجاف جلو', 'min' => 5, 'max' => 20, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $overlap = (float) $this->param($params, 'overlap', 7);
        $length = (float) $this->param($params, 'body_length', 14);
        $shape = $length > 0.5 ? 'fitted' : 'waist';
        $bottom = $g['front_waist_y'] + $length;

        $front = $this->bodyPanel($g, [
            'side' => 'front',
            'shape' => $shape,
            'length' => $length,
            'extension' => $overlap,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'bust_dart' => $this->flag($params, 'bust_dart', true),
            'bottom_tag' => $length > 0.5 ? 'hem' : 'waist',
            'code' => 'double-breasted-front',
            'name' => 'تنه جلو دوطرفه‌دکمه',
            'meta' => ['button_stand' => round($overlap, 2), 'double_breasted' => true],
        ]);

        $rows = (int) $this->param($params, 'button_rows', 3);
        $top = $g['bust_y'] + 2;
        $step = $rows > 1 ? ($g['front_waist_y'] - 2 - $top) / ($rows - 1) : 0.0;

        for ($i = 0; $i < $rows; $i++) {
            $y = $top + ($step * $i);

            foreach ([['right', $overlap * 2], ['left', 0.0]] as [$hand, $x]) {
                $front['drills'][] = [
                    'key' => 'button_'.$hand.'_'.($i + 1),
                    'label' => ($hand === 'right' ? 'دکمه رج راست ' : 'دکمه رج چپ ').$this->fa($i + 1),
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                ];
            }
        }

        $front['markers'][] = $this->marker('button_row', 'رج دکمه', $overlap * 2, $top, $overlap * 2, $top + ($step * max(0, $rows - 1)));
        $front['meta']['buttons'] = $rows * 2;

        $back = $this->bodyPanel($g, [
            'side' => 'back',
            'shape' => $shape,
            'length' => $length,
            'bottom_tag' => $length > 0.5 ? 'hem' : 'waist',
            'code' => 'double-breasted-back',
            'name' => 'تنه پشت',
        ]);

        $pieces = [
            $front,
            $back,
            $this->frontFacingPiece($g, $overlap, $bottom + $length, [
                'width' => (float) $this->param($params, 'facing_width', 10),
            ]),
            $this->backNeckFacingPiece($g),
        ];

        return $this->finishBlock($pieces, $g);
    }
}
