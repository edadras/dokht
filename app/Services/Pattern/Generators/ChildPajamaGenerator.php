<?php

namespace App\Services\Pattern\Generators;

/**
 * پیژامه کودک.
 *
 * دو تکه در یک الگو: بالاتنه آستین‌بلندِ سرخود و شلوار کمر کشی. لباس خواب کودک
 * دو الزام دارد که هیچ لباس دیگری ندارد:
 *
 *   ۱. هیچ بستِ سختی نباید داشته باشد. زیپ، قزن و دکمه زیر بدنِ خوابیده جای
 *      خودش را روی پوست می‌گذارد؛ پس بالاتنه سرخود است (تنها راه ورودش یقه) و
 *      شلوار فقط کش کمر دارد. همین باعث می‌شود سنجش «رد شدن یقه از سر» این‌جا
 *      اجباری باشد، نه اختیاری.
 *   ۲. باید در خواب راحت باشد ولی گشاد نباشد. پارچه گشادِ زیاد دور تن می‌پیچد؛
 *      پس آزادی این مدل از تی‌شرت بیشتر و از هودی کمتر است، و لبه‌ها با نوار
 *      کشباف بسته می‌شوند تا آستین و پاچه بالا نروند.
 */
class ChildPajamaGenerator extends ChildBaseGenerator
{
    public static function key(): string
    {
        return 'child_pajama';
    }

    public function label(): string
    {
        return 'پیژامه کودک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->childSchema([
                'shoulder_slope' => 2.5,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 1.5,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 2.5,
            ]),
            $this->childEaseSchema(2, 2),
            $this->garmentLengthParam(14, 4, 34, 'بلندی بالاتنه از خط کمر'),
            $this->sleeveParam('set_in', 40, [
                'set_in' => 'آستین بلند',
            ]),
            [
                'length_extra' => [
                    'label' => 'تغییر قد شلوار', 'min' => -30, 'max' => 12, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => 2, 'max' => 24, 'step' => 1,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم پا', 'min' => 2, 'max' => 26, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'elastic_ratio' => [
                    'label' => 'کوتاهی کش کمر', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                    'default' => 0.82,
                ],
                'rib' => [
                    'label' => 'نوار کشباف مچ و دم پاچه', 'type' => 'toggle', 'default' => true,
                ],
                'rib_height' => [
                    'label' => 'بلندی نوار کشباف', 'min' => 2.5, 'max' => 7, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);
        $length = (float) $this->param($params, 'length', 14);
        $rib = $this->flag($params, 'rib', true);
        $ribHeight = (float) $this->param($params, 'rib_height', 4);

        // بالاتنه سرخود است، پس یقه تنها راه ورود است و سنجش سر اجباری
        $clearance = $this->headClearance($g, $measurements, ['margin' => 2.0, 'max_depth' => 4.0]);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'grow' => $grow,
            'waist_dart' => false,
            'bust_dart' => false,
            'bottom_tag' => 'hem',
            'neck_width_extra' => $clearance['width_extra'],
            'meta' => ['knit' => true],
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'neck_depth_extra' => $clearance['front_depth_extra'],
            'code' => 'child-pajama-top-front',
            'name' => 'بالاتنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'child-pajama-top-back',
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
            ['prefix' => 'child-pajama-'],
        ));

        $pieces[] = $this->neckBandPiece($this->neckOf([$front, $back]), [
            'prefix' => 'child-pajama-',
            'ratio' => 0.9,
            'height' => 2.5,
        ]);

        $legEase = $this->legEase($ease, $grow);
        // ساسون کمر روی شلوار کمر کشی معنا ندارد؛ legPanel این را از پارامترها
        // می‌خواند نه از گزینه‌های قطعه
        $legParams = array_merge($params, ['back_darts' => 0]);

        foreach (['front', 'back'] as $side) {
            $leg = $this->legPanel($measurements, $legEase, $legParams, [
                'side' => $side,
                'code' => 'child-pajama-leg-'.$side,
                'name' => $side === 'front' ? 'پاچه جلو' : 'پاچه پشت',
            ]);

            $leg['meta']['girth_role'] = 'bottom';
            $leg['meta']['notes'] = array_merge($leg['meta']['notes'] ?? [], [
                'لبه کمر به اندازه دور باسن بریده شده تا شلوار از باسن رد شود؛ کش آن را روی کمر جمع می‌کند.',
            ]);

            $pieces[] = $leg;
        }

        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.82)));
        $pieces[] = $this->elasticWaistPiece('child-pajama-waist-elastic', $measurements, $ease, $ratio, 3);

        if ($rib) {
            $pieces[] = $this->ribBandPiece(
                'child-pajama-cuff-rib',
                'نوار کشباف مچ آستین',
                $this->m($measurements, 'wrist', 12) + 4,
                ['height' => $ribHeight, 'ratio' => 0.88, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff'],
            );

            $hem = 0.0;

            foreach ($pieces as $piece) {
                if (($piece['meta']['girth_role'] ?? '') === 'bottom') {
                    $hem += (float) ($piece['meta']['hem_width'] ?? 0);
                }
            }

            $pieces[] = $this->ribBandPiece(
                'child-pajama-leg-rib',
                'نوار کشباف دم پاچه',
                max(12.0, $hem),
                ['height' => $ribHeight, 'ratio' => 0.88, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff'],
            );
        }

        $pieces = $this->stampHeadClearance($pieces, $clearance, $g, ['notion' => 'snap']);

        return $this->finishBlock($this->childNoted($pieces, [
            'پیژامه هیچ بستِ سختی ندارد؛ زیپ و دکمه زیر بدنِ خوابیده جای خودش را روی پوست می‌گذارد.',
        ]), $g, $grow);
    }
}
