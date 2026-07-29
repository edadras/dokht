<?php

namespace App\Services\Pattern\Generators;

/**
 * تونیک پوشیده.
 *
 * تونیک معمولی کاتالوگ تا میان ران می‌آید، آستینش دلخواه است و یقه‌اش چاک دارد.
 * این یکی سه تصمیم دیگر گرفته و همان سه، پوشیدگی‌اش را می‌سازد:
 *
 *   ۱. بلندی تا زیر باسن یا میان ران، و مهم‌تر از آن: از خط کمر به پایین باز
 *      می‌شود تا روی باسن نچسبد. لباسِ کوتاهِ چسبان، «پوشیده» نیست حتی اگر
 *      همه‌جای بدن را بپوشاند.
 *   ۲. آستین همیشه تا مچ دست است، نه دلخواه.
 *   ۳. یقه بسته و بالا، بدون چاک جلو؛ برای رد شدن سر، چاک و دکمه روی مرکز پشت
 *      گذاشته می‌شود.
 *
 * ساسون کمر ملایم است تا لباس فرم داشته باشد ولی قالب تن نشود، و چاک پهلوی کوتاه
 * راه رفتن را راحت می‌کند.
 */
class TradModestTunicGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_modest_tunic';
    }

    public function label(): string
    {
        return 'تونیک پوشیده';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 0.5,
                'front_neck_depth_extra' => 1,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 2.5,
                'waist_dart_share' => 0.5,
            ]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(42, 22, 70),
            $this->collarParam('stand', [
                'none' => 'بدون یقه (نوار اریب دور یقه)',
                'stand' => 'یقه ایستاده کوتاه',
            ], 3.5),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین حلقه‌ای تا مچ',
            ]),
            [
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 2, 'max' => 24, 'step' => 1,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                    'hint' => 'لباس پوشیده باید از کمر به پایین باز شود، وگرنه روی باسن می‌چسبد.',
                ],
                'back_slit' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 6, 'max' => 24, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'cuff' => [
                    'label' => 'مچ‌بند دکمه‌دار', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_height' => [
                    'label' => 'بلندی مچ‌بند', 'min' => 3, 'max' => 9, 'step' => 0.5,
                    'default' => 5.5, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);
        $length = (float) $this->param($params, 'length', 42);
        $slit = (float) $this->param($params, 'back_slit', 12);
        $vent = (float) $this->param($params, 'side_vent', 10);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'modest-tunic-',
            'grow' => $grow,
            'shape' => 'flare',
            'length' => $length,
            'opening' => 'closed',
            'facing' => false,
            'front_name' => 'تنه جلو',
            'back_name' => 'تنه پشت',
            'panel' => ['waist_dart' => true],
            'sleeve' => ['sleeve_name' => 'آستین تا مچ'],
        ]);

        $pieces[0] = $this->markSideVent($pieces[0], $vent);
        $pieces[1] = $this->markSideVent($pieces[1], $vent);

        $pieces[1]['markers'][] = $this->marker(
            'slit',
            'چاک مرکز پشت',
            0,
            $g['back_neck_depth'],
            0,
            $g['back_neck_depth'] + $slit,
        );
        $pieces[1]['meta']['back_slit'] = round($slit, 2);
        $pieces[1] = $this->addNotion(
            $pieces[1],
            ['type' => 'button', 'label' => 'دکمه و بندِ سر چاک پشت', 'count' => 1],
            'چاک مرکز پشت به بلندی '.$this->fa(round($slit))
                .' سانتی‌متر؛ یقه بسته است و سر بدون این چاک رد نمی‌شود.',
        );

        $pieces[] = $this->bandPiece('modest-tunic-back-slit-facing', 'سجاف چاک پشت', $slit + 6, 6, [
            'cut' => 1, 'part' => 'facing',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notes' => ['روی چاک پشت دوخته و به داخل برگردانده می‌شود.'],
            ],
        ]);

        if ((string) $this->param($params, 'collar', 'stand') === 'none') {
            $pieces[] = $this->bandPiece(
                'modest-tunic-neck-binding',
                'نوار اریب دور یقه',
                (2 * $this->neckOf([$pieces[0], $pieces[1]])) + 4,
                3,
                [
                    'cut' => 1, 'part' => 'facing',
                    'meta' => ['bias' => true, 'girth_role' => 'trim', 'notes' => ['روی اریب بریده می‌شود.']],
                ],
            );
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'modest-tunic-', 'width' => 6]);

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($pieces[0]),
            'hem_at' => $length >= 38 ? 'میان ران' : 'زیر باسن',
            'sleeve' => 'مچ دست',
            'neck' => 'بسته و بالا، بدون چاک جلو',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'از خط کمر به پایین باز می‌شود؛ روی باسن هیچ‌جا به بدن نمی‌چسبد.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
