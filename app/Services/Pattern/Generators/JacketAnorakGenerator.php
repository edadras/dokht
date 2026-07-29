<?php

namespace App\Services\Pattern\Generators;

/**
 * آنوراک.
 *
 * کاپشنی که سرخود پوشیده می‌شود؛ جلویش باز نمی‌شود و فقط یک نیم‌زیپ دارد. همین
 * یک تصمیم، دو نتیجهٔ فنی دارد که اگر دیده نشوند آنوراک دوخته نمی‌شود:
 *
 *   ۱. لباس باید از سر رد شود. وقتی جلو باز نمی‌شود، تنها راه ورود، دهانهٔ یقه
 *      به‌علاوهٔ همان نیم‌زیپ است. پس نیم‌زیپ باید دست‌کم تا خط سینه پایین بیاید،
 *      وگرنه سر از آن رد نمی‌شود.
 *   ۲. جلو روی تای پارچه بریده می‌شود و درز مرکزی ندارد؛ پس زیپ روی یک **شکاف**
 *      می‌نشیند، نه روی درز، و پاتلت زیپ همان چیزی است که لبهٔ شکاف را نگه
 *      می‌دارد.
 *
 * جیب کانگورو یک‌تکه از یک پهلو تا پهلوی دیگر می‌رود و کلاه سرخود است.
 */
class JacketAnorakGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_anorak';
    }

    public function label(): string
    {
        return 'آنوراک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 5,
                'neck_width_extra' => 3.5,
                'front_neck_depth_extra' => 3,
                'shoulder_slope' => 3,
            ], [], 'loose', 'shirt'),
            $this->garmentLengthParam(24, 8, 55),
            $this->sleeveParam('set_in', 61),
            [
                'half_zip' => [
                    'label' => 'بلندی نیم‌زیپ از گودی یقه', 'min' => 14, 'max' => 45, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                    'hint' => 'کوتاه‌تر از خط سینه یعنی سر از دهانه رد نمی‌شود.',
                ],
                'kangaroo' => [
                    'label' => 'جیب کانگورو', 'type' => 'toggle', 'default' => true,
                ],
                'hood' => [
                    'label' => 'کلاه', 'type' => 'toggle', 'default' => true,
                ],
                'hem_draw' => [
                    'label' => 'جمع‌شدن لبهٔ پایین با بند', 'min' => 0, 'max' => 26, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 16.0, ['bicep' => 10.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 24);
        $zip = (float) $this->param($params, 'half_zip', 26);
        $hemDraw = (float) $this->param($params, 'hem_draw', 12);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'anorak-',
            'grow' => 0.0,
            'shape' => 'straight',
            // جلو بسته: روی تای پارچه، بدون هیچ درز مرکزی
            'opening' => 'closed',
            'collar' => 'none',
            'front_name' => 'تنه جلوی آنوراک (روی تای پارچه)',
            'back_name' => 'تنه پشت آنوراک',
            'panel' => ['waist_dart' => false],
            'lining_options' => ['shape' => 'straight', 'length' => max(0.0, $length - 2)],
        ]);

        $halfNeck = $this->neckOf([$pieces[0], $pieces[1]]);
        $top = $g['front_neck_depth'];
        // زیپ دست‌کم تا سه سانتی‌متر زیر خط سینه می‌آید (وگرنه سر رد نمی‌شود) و
        // دست‌بالا تا کمی بالای خط کمر؛ پایین‌تر از آن دیگر نیم‌زیپ نیست.
        $zipEnd = min($g['front_waist_y'] - 2, max($g['bust_y'] + 3, $top + $zip));
        $zipLength = round(max(8.0, $zipEnd - $top), 1);
        $zipEnd = $top + $zipLength;

        // شکاف نیم‌زیپ روی خط مرکز جلو؛ همان خط تای پارچه است، پس شکاف است نه درز
        $pieces[0]['markers'][] = $this->marker('zip', 'شکاف نیم‌زیپ روی مرکز جلو', 0, round($top, 2), 0, round($zipEnd, 2));
        $pieces[0]['meta']['zip_length'] = $zipLength;
        $pieces[0]['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'نیم‌زیپ جلو (غیرجداشونده)',
            'count' => 1,
            'length' => $zipLength,
        ];

        $pieces[] = $this->bandPiece('anorak-zip-placket', 'پاتلت نیم‌زیپ', $zipLength + 4, 7, [
            'cut' => 2, 'part' => 'placket',
            'meta' => [
                'interfacing' => true,
                'notes' => [
                    'جلو روی تای پارچه بریده می‌شود و درز مرکزی ندارد؛ زیپ روی یک شکاف می‌نشیند و '
                        .'این پاتلت لبهٔ شکاف را نگه می‌دارد. سرِ پایینِ شکاف را با یک مثلث دوخت محکم کنید.',
                ],
            ],
        ]);

        $notes = [
            'نیم‌زیپ '.$this->fa($zipLength).' سانتی‌متر است و تا زیر خط سینه پایین می‌آید؛ '
                .'کوتاه‌تر از این، سر از دهانهٔ لباس رد نمی‌شود.',
        ];

        if ($this->flag($params, 'hood', true)) {
            $pieces = array_merge($pieces, $this->hoodSet($g, $halfNeck, [
                'prefix' => 'anorak-',
                'width_extra' => 7,
                'height_ratio' => 2.0,
                'name' => 'کلاه آنوراک',
            ]));

            $pieces[count($pieces) - 1] = $this->markDrawcord(
                $pieces[count($pieces) - 1],
                '',
                4.0,
                'بند لبهٔ صورت کلاه',
                $this->m($measurements, 'neck', 36) + 60,
            );
        } else {
            $pieces[] = $this->standCollarPiece($halfNeck, 7.0, ['prefix' => 'anorak-']);
        }

        if ($this->flag($params, 'kangaroo', true)) {
            // نیم‌پهنای جیب: روی تای پارچه بریده می‌شود، پس پهنای کاملش دو برابر
            // این عدد است و باید از پهنای تنه کمتر بماند
            $width = max(12.0, $g['quarter_bust'] * 0.8);
            $pieces[] = $this->kangarooPocketPiece($width, max(15.0, $width * 0.85), [
                'prefix' => 'anorak-',
                'opening' => max(9.0, $width * 0.45),
            ]);
            $notes[] = 'جیب کانگورو یک‌تکه و روی تای پارچه بریده می‌شود؛ دو دهانهٔ اریب دارد و دم لباس، لبهٔ پایینش را می‌گیرد.';
        }

        if ($hemDraw > 0.5) {
            $pieces[0] = $this->markDrawcord(
                $pieces[0],
                'hem',
                $hemDraw / 2,
                'بند لبهٔ پایین',
                $this->m($measurements, 'hip', 98) + 45,
            );
            $pieces[] = $this->bandPiece('anorak-hem-casing', 'جای بند لبهٔ پایین', $g['quarter_bust'] * 2, 4, [
                'cut' => 2, 'part' => 'waistband',
                'meta' => ['notes' => ['دو تکه برای دور کامل؛ بند از دو مغزی روی جلو بیرون می‌آید.']],
            ]);
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
