<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن ساحلی.
 *
 * پیراهن سبک تابستانی که روی مایو هم پوشیده می‌شود: بالاتنه‌ای که روی خط کمر
 * تمام می‌شود و دامن چین‌دار پُری که به همان خط دوخته می‌گردد. یقه گشاد و گود
 * است و حلقه‌ها باز؛ همه لبه‌های باز با نوار اریب تمام می‌شوند، چون روی پارچه
 * نازک، سجاف روی تن دیده می‌شود و بد می‌افتد.
 *
 * دو عدد این پیراهن را می‌سازند: لبه کمر بالاتنه (که کاهش کمرش با ساسون گرفته
 * می‌شود) و لبه بالای دامن (که چند برابر آن است و رویش چین می‌خورد). این دو
 * لبه اندازه‌گیری‌شده به هم می‌رسند، نه حدسی؛ چین هم در meta.gathers ثبت می‌شود.
 *
 * بسته شدن از مرکز پشت است: پیراهن با کمر گرفته از باسن رد نمی‌شود، پس پشت در
 * دو تکه با زیپ بریده می‌گردد.
 */
class BeachDressGenerator extends BeachBaseGenerator
{
    public static function key(): string
    {
        return 'beach_dress';
    }

    public function label(): string
    {
        return 'پیراهن ساحلی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 2.5,
                'front_neck_depth_extra' => 8,
                'back_neck_depth' => 5,
                'armhole_depth_extra' => 1,
                'waist_dart_share' => 0.55,
            ]),
            $this->fitParam('regular'),
            $this->sleeveParam('none', 16, [
                'none' => 'بدون آستین (نوار اریب حلقه)',
                'set_in' => 'آستین کوتاه',
            ]),
            [
                'skirt_length' => [
                    'label' => 'بلندی دامن از خط کمر', 'min' => 30, 'max' => 100, 'step' => 1,
                    'default' => 58, 'unit' => 'سانتی‌متر',
                ],
                'gather_ratio' => [
                    'label' => 'نسبت پُری چین دامن', 'min' => 1.2, 'max' => 2.6, 'step' => 0.1,
                    'default' => 1.8,
                ],
                'skirt_flare' => [
                    'label' => 'باز شدن لبه پایین دامن در هر پهلو', 'min' => 0, 'max' => 24, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه روی درز پهلو', 'type' => 'toggle', 'default' => true,
                ],
                'waist_tie' => [
                    'label' => 'بند کمر', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);
        $skirtLength = max(20.0, (float) $this->param($params, 'skirt_length', 58));
        $ratio = max(1.1, (float) $this->param($params, 'gather_ratio', 1.8));

        $shared = [
            'shape' => 'waist',
            'length' => 0.0,
            'grow' => $grow,
            'waist_dart' => true,
            'bottom_tag' => 'waist',
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'bust_dart' => $this->flag($params, 'bust_dart', true),
            'code' => 'beach-dress-bodice-front',
            'name' => 'بالاتنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'code' => 'beach-dress-bodice-back',
            'name' => 'بالاتنه پشت (با درز و زیپ مرکز پشت)',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);
        $back = $this->markBackZip($back, $g, null, $skirtLength);

        $armhole = $this->armholeOf([$front, $back]);
        $halfNeck = $this->neckOf([$front, $back]);
        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet($measurements, $ease, $params, $armhole, $g, [
            'prefix' => 'beach-dress-',
            'sleeve_name' => 'آستین کوتاه',
        ]));

        $quarter = $g['quarter_waist'] + $grow;
        $gather = round($quarter * ($ratio - 1), 2);
        $flare = (float) $this->param($params, 'skirt_flare', 10);

        foreach ([['front', 'جلو', true], ['back', 'پشت', false]] as [$side, $name, $onFold]) {
            $panel = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'flare',
                'top_width' => $quarter,
                'top_y' => $g['side_waist_y'],
                'length' => $skirtLength,
                'gather' => $gather,
                'flare' => $flare,
                'top_tag' => 'waist',
                'on_fold' => $onFold,
                'cut' => $onFold ? 1 : 2,
                'code' => 'beach-dress-skirt-'.$side,
                'name' => 'دامن چین‌دار '.$name,
                'meta' => [
                    'gather_ratio' => round($ratio, 2),
                    'notes' => [
                        'لبه بالای این پنل '.$this->fa(round($ratio, 1))
                            .' برابر لبه کمر بالاتنه است و با چین ریز روی آن جمع می‌شود.',
                    ],
                ],
            ]);

            $panel = $this->recordGathers($panel, $gather, 'چین کمر دامن');

            if (! $onFold) {
                $panel['markers'][] = $this->marker(
                    'zip',
                    'ادامه زیپ مرکز پشت',
                    0,
                    0,
                    0,
                    min($skirtLength - 1, $g['hip_drop'] + 3),
                );
                $panel['meta']['notes'][] = 'زیپ مرکز پشت روی این درز ادامه پیدا می‌کند تا پایین‌تر از خط باسن.';
            }

            $pieces[] = $panel;
        }

        $style = (string) $this->param($params, 'sleeve_style', 'none');

        if ($style === 'none') {
            $pieces[] = $this->armholeBindingPiece($armhole, ['prefix' => 'beach-dress-', 'height' => 3.5]);
        }

        $pieces[] = $this->bandPiece('beach-dress-neck-binding', 'نوار اریب یقه', (2 * $halfNeck) + 4, 3.5, [
            'cut' => 1, 'part' => 'facing',
            'meta' => [
                'bias' => true,
                'girth_role' => 'trim',
                'target_neck' => round(2 * $halfNeck, 2),
                'notes' => ['روی اریب بریده می‌شود؛ روی پارچه نازک از سجاف بهتر می‌خوابد.'],
            ],
        ]);

        if ($this->flag($params, 'waist_tie', true)) {
            $pieces[] = $this->bandPiece(
                'beach-dress-waist-tie',
                'بند کمر',
                ($this->m($measurements, 'waist', 74) / 2) + 60,
                6,
                [
                    'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                    'meta' => [
                        'girth_role' => 'trim',
                        'notes' => ['دو بند روی درز پهلو دوخته می‌شود و پشت یا جلو گره می‌خورد.'],
                    ],
                ],
            );
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->beachNotes([
            'از سرشانه تا لبه پایین حدود '.$this->fa(round($this->hemFromShoulder($front) + $skirtLength, 1))
                .' سانتی‌متر می‌شود: بالاتنه تا خط کمر، به‌علاوه بلندی دامن.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
