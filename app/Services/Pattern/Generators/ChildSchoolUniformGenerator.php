<?php

namespace App\Services\Pattern\Generators;

/**
 * فرم مدرسه.
 *
 * فرم مدرسه یک لباس نیست، یک دست لباس است: روپوش (تونیک) به‌علاوه شلوار یا
 * دامن. این تولیدکننده هر دو تکه را با هم درفت می‌کند، چون تنها این‌طور می‌شود
 * مطمئن شد که هر دو با یک آزادی و یک قد بریده شده‌اند.
 *
 * سه چیز که فرم مدرسه را از لباس بیرون جدا می‌کند:
 *
 *   ۱. کودک آن را خودش می‌پوشد و درمی‌آورد، روزی چند بار. پس روپوش جلوباز با
 *      دکمه است و پایین‌تنه کمر کشی دارد، نه زیپ و قزن.
 *   ۲. هر روز پوشیده و شسته می‌شود. جیب‌ها رودوزی و بادوام‌اند و لبه پایین
 *      برگردان دارد تا بشود یک فصل بلندترش کرد.
 *   ۳. کمر کشی باید هم از باسن رد شود و هم روی کمر بایستد. پس لبه کمر به اندازه
 *      دور باسن بریده می‌شود و کش کوتاه‌تر آن را روی کمر جمع می‌کند.
 */
class ChildSchoolUniformGenerator extends ChildBaseGenerator
{
    public static function key(): string
    {
        return 'child_school_uniform';
    }

    public function label(): string
    {
        return 'فرم مدرسه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->childSchema([
                'shoulder_slope' => 3,
                'neck_width_extra' => 1.5,
                'front_neck_depth_extra' => 1.5,
                'back_neck_depth' => 2.5,
                'armhole_depth_extra' => 3,
            ]),
            $this->childEaseSchema(2.5, 2),
            $this->garmentLengthParam(30, 12, 60, 'بلندی تونیک از خط کمر'),
            $this->sleeveParam('set_in', 42, [
                'set_in' => 'آستین بلند',
                'none' => 'بی‌آستین (نوار حلقه)',
            ]),
            $this->openingParam('button', 2, [
                'button' => 'جلوباز با دکمه',
                'closed' => 'سرخود (روی تای پارچه)',
            ]),
            $this->collarParam('turn', [
                'turn' => 'یقه برگردان',
                'stand' => 'یقه ایستاده',
                'none' => 'بدون یقه (سجاف تمیزدوزی)',
            ], 5),
            [
                'bottom' => [
                    'label' => 'پایین‌تنه', 'type' => 'select', 'default' => 'pants',
                    'options' => [
                        'pants' => 'شلوار کمر کشی',
                        'skirt' => 'دامن کمر کشی',
                        'none' => 'فقط تونیک',
                    ],
                ],
                'bottom_length' => [
                    'label' => 'بلندی دامن از کمر', 'min' => 20, 'max' => 60, 'step' => 1,
                    'default' => 34, 'unit' => 'سانتی‌متر',
                ],
                'length_extra' => [
                    'label' => 'تغییر قد شلوار', 'min' => -30, 'max' => 12, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => 2, 'max' => 24, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم پا', 'min' => 2, 'max' => 30, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'elastic_ratio' => [
                    'label' => 'کوتاهی کش کمر', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                    'default' => 0.82,
                ],
            ],
            $this->pocketParam(true, 11, 12),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);
        $length = (float) $this->param($params, 'length', 30);
        $closed = (string) $this->param($params, 'front_opening', 'button') === 'closed';

        // تونیکِ سرخود از سر پوشیده می‌شود، پس یقه‌اش باید دور سر را رد کند؛
        // تونیک جلوباز از جلو باز می‌شود و این محدودیت را ندارد
        $clearance = $this->headClearance($g, $measurements, [
            'required' => $closed,
            'margin' => 2.0,
            'max_depth' => 3.0,
        ]);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'child-uniform-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'front_name' => 'تونیک جلو',
            'back_name' => 'تونیک پشت',
            'panel' => [
                'waist_dart' => false,
                'bust_dart' => false,
                'neck_width_extra' => $clearance['width_extra'],
            ],
            'front' => ['neck_depth_extra' => $clearance['front_depth_extra']],
            'facing' => ! $closed,
            'facing_width' => 7,
        ]);

        if ($closed) {
            $pieces = $this->stampHeadClearance($pieces, $clearance, $g, ['notion' => 'button']);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'child-uniform-']));

        $bottom = (string) $this->param($params, 'bottom', 'pants');
        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.82)));

        if ($bottom === 'pants') {
            $pieces = array_merge($pieces, $this->uniformPants($measurements, $ease, $params, $grow));
        }

        if ($bottom === 'skirt') {
            $pieces = array_merge($pieces, $this->uniformSkirt($g, $params, $grow));
        }

        if ($bottom !== 'none') {
            $pieces[] = $this->elasticWaistPiece('child-uniform-waist-elastic', $measurements, $ease, $ratio, 3);
        }

        return $this->finishBlock($this->childNoted($pieces, [
            'لبه پایین تونیک و پاچه با برگردان پهن دوخته می‌شود تا سال بعد بشود بلندترش کرد.',
        ]), $g, $grow);
    }

    /**
     * شلوار فرم: دو پاچه با کمر کشی.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function uniformPants(array $m, array $ease, array $params, float $grow): array
    {
        $legEase = $this->legEase($ease, $grow);
        // کمر کشی ساسون نمی‌خواهد؛ پارچه اضافه را کش جمع می‌کند. ساسون از روی
        // پارامترها خوانده می‌شود، نه از گزینه‌های قطعه، پس همین‌جا صفر می‌شود
        $legParams = array_merge($params, ['back_darts' => 0]);
        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $leg = $this->legPanel($m, $legEase, $legParams, [
                'side' => $side,
                'code' => 'child-uniform-leg-'.$side,
                'name' => $side === 'front' ? 'پاچه جلو' : 'پاچه پشت',
            ]);

            $leg['meta']['girth_role'] = 'bottom';
            $leg['meta']['notes'] = array_merge($leg['meta']['notes'] ?? [], [
                'لبه کمر به اندازه دور باسن بریده شده تا شلوار از باسن رد شود؛ کش آن را روی کمر جمع می‌کند.',
            ]);

            $pieces[] = $leg;
        }

        return $pieces;
    }

    /**
     * دامن فرم: دو پنل با چین کمر.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function uniformSkirt(array $g, array $params, float $grow): array
    {
        $waistQuarter = $g['quarter_waist'] + $grow;
        // لبه کمر باید از باسن رد شود، وگرنه دامن بالا نمی‌آید؛ پس پارچه اضافه
        // دست‌کم به اندازه اختلاف کمر و باسن است و بعد چین بیشتری هم رویش می‌آید
        $gather = round(max($g['quarter_hip'] + $grow - $waistQuarter + 1.5, $waistQuarter * 0.35), 2);
        $length = (float) $this->param($params, 'bottom_length', 34);
        $pieces = [];

        foreach (['front', 'back'] as $side) {
            // لبه بالا پهن‌تر بریده می‌شود و چین در meta.gathers ثبت می‌گردد، نه
            // در قالب پیلی؛ یک چین نباید دو جا شمرده شود
            $panel = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'straight',
                'grow' => $grow,
                'top_width' => $waistQuarter + $gather,
                'top_y' => $g['side_waist_y'],
                'length' => $length,
                'gather' => 0.0,
                'top_tag' => 'waist',
                'code' => 'child-uniform-skirt-'.$side,
                'name' => $side === 'front' ? 'دامن جلو' : 'دامن پشت',
                'meta' => [
                    'notes' => ['اندازه بریده‌شده لبه کمر از دور باسن بزرگ‌تر است تا دامن از باسن رد شود.'],
                ],
            ]);

            $pieces[] = $this->gatheredWaist($panel, $gather, 'چین کمر دامن روی کش');
        }

        return $pieces;
    }
}
