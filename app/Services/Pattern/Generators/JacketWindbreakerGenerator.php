<?php

namespace App\Services\Pattern\Generators;

/**
 * بادگیر.
 *
 * سبک‌ترین لباس رویی این خانواده: از پارچهٔ نازکِ بادگیر بریده می‌شود و کارش
 * فقط گرفتن باد است، نه گرم کردن. همین سبکی، مسئلهٔ خودش را دارد.
 *
 * پارچهٔ نازک و بی‌وزن روی تن نمی‌ایستد؛ باد از دم لباس و از مچ آستین داخل
 * می‌شود و بادگیر مثل بادکنک باد می‌کند. راه‌حل همان است که همیشه بوده: هر
 * دهانه‌ای کش می‌خورد — کمر (یا دم لباس)، مچ آستین و لبهٔ صورتِ کلاه. کشْ لباس
 * را کوچک‌تر نمی‌کند؛ الگو به اندازهٔ باز بریده می‌شود و کش پارچه را جمع می‌کند.
 * برای همین آزادی این مدل از پیراهن بیشتر است، هرچند روی تن جمع دیده می‌شود.
 */
class JacketWindbreakerGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_windbreaker';
    }

    public function label(): string
    {
        return 'بادگیر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 5,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 2,
                'shoulder_slope' => 3,
            ], [], 'loose', 'shirt'),
            $this->garmentLengthParam(20, 4, 50),
            $this->sleeveParam('set_in', 61),
            [
                'elastic_hem' => [
                    'label' => 'دم لباس', 'type' => 'select', 'default' => 'elastic',
                    'options' => [
                        'elastic' => 'کش داخل جای کش',
                        'rib' => 'نوار کشباف',
                        'plain' => 'ساده (فقط تودوزی)',
                    ],
                ],
                'elastic_cuff' => [
                    'label' => 'مچ آستین کشی', 'type' => 'toggle', 'default' => true,
                ],
                'hood' => [
                    'label' => 'کلاه جمع‌شونده در یقه', 'type' => 'toggle', 'default' => true,
                ],
                'rib_height' => [
                    'label' => 'بلندی نوار یا جای کش', 'min' => 3, 'max' => 9, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'mesh_lining' => [
                    'label' => 'آسترِ توری', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 15, 15),
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 14.0, ['bicep' => 10.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $ribHeight = (float) $this->param($params, 'rib_height', 5);
        $hemStyle = (string) $this->param($params, 'elastic_hem', 'elastic');
        $length = max(3.0, (float) $this->param($params, 'length', 20) - ($hemStyle === 'plain' ? 0.0 : $ribHeight));

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'windbreaker-',
            'grow' => 0.0,
            'shape' => 'straight',
            'opening' => 'zip',
            'collar' => 'stand',
            'collar_height' => max(6.0, $ribHeight + 2),
            'length' => $length,
            'front_name' => 'تنه جلوی بادگیر',
            'back_name' => 'تنه پشت بادگیر',
            'facing_width' => 6,
            'panel' => ['waist_dart' => false],
            'lining_options' => ['shape' => 'straight', 'length' => $length],
        ]);

        $halfNeck = $this->neckOf([$pieces[0], $pieces[1]]);
        $hemY = max(1.0, $g['side_waist_y'] + $length - 1.0);
        $hem = ($this->panelWidthAt($pieces[0], $hemY) * 2) + ($this->panelWidthAt($pieces[1], $hemY) * 2);

        $notes = [
            'بلندی تنه '.$this->fa(round($length, 1)).' سانتی‌متر درفت شده و نوار یا جای کشِ دم لباس '
                .$this->fa(round($ribHeight, 1)).' سانتی‌متر روی آن اضافه می‌شود؛ قد نهایی همان است که خواسته‌اید.',
        ];

        if ($hemStyle === 'rib') {
            $pieces[] = $this->ribBandPiece('windbreaker-hem-rib', 'نوار کشباف دم لباس', $hem, [
                'height' => $ribHeight, 'ratio' => 0.8, 'cut' => 1, 'on_fold' => true,
            ]);
        } elseif ($hemStyle === 'elastic') {
            $casing = $this->bandPiece('windbreaker-hem-casing', 'جای کش دم لباس', $hem / 2, $ribHeight, [
                'cut' => 2, 'part' => 'waistband',
                'meta' => [
                    'notions' => [[
                        'type' => 'elastic',
                        'label' => 'کش دم لباس',
                        'count' => 1,
                        'length' => round($this->m($measurements, 'hip', 98) * 0.85, 1),
                    ]],
                    'notes' => ['دو تکه برای دور کامل؛ کش حدود ۱۵ درصد کوتاه‌تر از خودِ لبه بریده می‌شود.'],
                ],
            ]);

            $pieces[] = $casing;
            $pieces[0] = $this->markDrawcord($pieces[0], 'hem', ($hem * 0.15) / 4, 'کش دم لباس', $hem * 0.85);
        } else {
            $notes[] = 'دم لباس ساده است و کش ندارد؛ در پارچهٔ نازک این یعنی باد از پایین داخل می‌شود.';
        }

        if ($this->flag($params, 'elastic_cuff', true)) {
            $wrist = $this->m($measurements, 'wrist', 16.5);
            $cuff = $this->ribBandPiece('windbreaker-cuff', 'مچ کشیِ آستین', $wrist + 12, [
                'height' => $ribHeight, 'ratio' => 0.75, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff',
            ]);
            $cuff['meta']['notions'][] = [
                'type' => 'elastic',
                'label' => 'کش مچ آستین',
                'count' => 2,
                'length' => round($wrist + 2, 1),
            ];
            $pieces[] = $cuff;
        }

        if ($this->flag($params, 'hood', true)) {
            $hood = $this->hoodSet($g, $halfNeck, [
                'prefix' => 'windbreaker-',
                'width_extra' => 6,
                'height_ratio' => 1.95,
                'name' => 'کلاه جمع‌شونده',
            ]);

            $hood[0]['meta']['notes'] = array_merge($hood[0]['meta']['notes'] ?? [], [
                'کلاه در یقهٔ ایستاده جمع می‌شود؛ پس یقه باید دولا و به اندازهٔ تای‌شدهٔ کلاه گشاد بریده شود.',
            ]);

            $hood[count($hood) - 1] = $this->markDrawcord(
                $hood[count($hood) - 1],
                '',
                4.0,
                'بند لبهٔ صورت کلاه',
                $this->m($measurements, 'neck', 36) + 55,
            );

            $pieces = array_merge($pieces, $hood);
        }

        if ($this->flag($params, 'mesh_lining', true)) {
            $notes[] = 'آسترِ توری روی تنه و آسترِ نازک روی آستین می‌خورد؛ توری روی آستین، دست را هنگام '
                .'پوشیدن گیر می‌اندازد. قطعه‌های آستر هم‌اندازهٔ رو بریده می‌شوند و در این الگو جدا کشیده نشده‌اند.';
        }

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 15),
                (float) $this->param($params, 'pocket_height', 15),
                ['prefix' => 'windbreaker-', 'welt' => 2.0, 'name' => 'مغزی جیب زیپ‌دار'],
            ));
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
