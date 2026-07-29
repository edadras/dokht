<?php

namespace App\Services\Pattern\Generators;

/**
 * ژاکت کوتاه.
 *
 * ژاکتی که روی خط کمر یا کمی پایین‌تر تمام می‌شود. کوتاه بودن یک تصمیم ساده به
 * نظر می‌رسد ولی دو چیز را واقعاً عوض می‌کند و اگر رعایت نشوند ژاکت روی تن بالا
 * می‌زند:
 *
 *   ۱. لبهٔ پایین درست همان‌جایی می‌افتد که بدن پهن‌تر می‌شود (خط کمر تا باسن).
 *      پس درز پهلو نباید کمرگیری تند داشته باشد، وگرنه دم ژاکت روی باسن گیر
 *      می‌کند و بالا می‌رود. برای همین فرم پیش‌فرض راسته است، نه جذب.
 *   ۲. آستین باید کوتاه‌تر بریده شود. آستین بلندِ معمولی زیر یک ژاکت کوتاه، تن
 *      را کوتاه‌تر نشان می‌دهد؛ آستین سه‌ربع همان چیزی است که این مدل می‌خواهد.
 *
 * جلو لبه‌به‌لبه بسته می‌شود (بدون هم‌پوشانی) و لبه‌هایش با سجاف تمام می‌شود.
 */
class JacketCroppedGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_cropped';
    }

    public function label(): string
    {
        return 'ژاکت کوتاه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 3.5,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 4,
                'shoulder_slope' => 4,
            ]),
            $this->garmentLengthParam(4, -4, 22, 'بلندی از خط کمر'),
            $this->collarParam('none', [
                'none' => 'بدون یقه (لبه با سجاف تمام می‌شود)',
                'stand' => 'یقه ایستادهٔ کوتاه',
                'turn' => 'یقه برگردان کوتاه',
            ], 5),
            $this->sleeveParam('set_in', 46),
            [
                'front_close' => [
                    'label' => 'بست جلو', 'type' => 'select', 'default' => 'hook',
                    'options' => [
                        'none' => 'باز می‌ماند',
                        'hook' => 'قزن مخفی روی خط مرکز جلو',
                        'button' => 'یک دکمهٔ بالای جلو',
                    ],
                    'hint' => 'جلو لبه‌به‌لبه است و هم‌پوشانی ندارد؛ دکمهٔ ردیفی روی آن جا نمی‌شود.',
                ],
            ],
            $this->pocketParam(false, 12, 13),
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 12.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 4);
        $close = (string) $this->param($params, 'front_close', 'hook');

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'cropped-',
            'grow' => 0.0,
            // راسته، چون لبهٔ پایین روی پهن‌ترین جای بین کمر و باسن می‌ایستد
            'shape' => $this->fitShape($params, ['fitted' => 'fitted', 'regular' => 'straight', 'loose' => 'straight']),
            'opening' => 'open',
            'length' => $length,
            'front_name' => 'تنه جلوی ژاکت کوتاه',
            'back_name' => 'تنه پشت ژاکت کوتاه',
            'facing_width' => 7,
            'lining_options' => ['length' => max(0.0, $length - 1.5), 'shape' => 'straight'],
        ]);

        $notes = [
            'جلو لبه‌به‌لبه است: دو نیمه روی هم نمی‌افتند و هر لبه با سجاف تمام می‌شود.',
        ];

        if ($close === 'hook') {
            $pieces[0]['meta']['notions'][] = [
                'type' => 'hook',
                'label' => 'قزن مخفی مرکز جلو',
                'count' => 2,
            ];
            $notes[] = 'دو قزن مخفی روی خط مرکز جلو بسته می‌شود؛ از رو دیده نمی‌شود.';
        }

        if ($close === 'button') {
            $pieces[0]['drills'][] = [
                'key' => 'button_1',
                'label' => 'دکمهٔ بالای جلو',
                'x' => 1.2,
                'y' => round($g['bust_y'] + 1, 2),
            ];
            $pieces[0]['meta']['notions'][] = ['type' => 'button', 'label' => 'دکمهٔ بالای جلو', 'count' => 1];
            $notes[] = 'یک دکمه بالای جلو بسته می‌شود و بقیهٔ لبه باز می‌ماند؛ چون هم‌پوشانی ندارد، جادکمه روی خودِ لبه می‌افتد.';
        }

        $sleeveLength = (float) $this->param($params, 'sleeve_length', 46);
        $armLength = $this->m($measurements, 'arm_length', 58);

        if ($sleeveLength < $armLength - 4) {
            $notes[] = 'آستین '.$this->fa(round($armLength - $sleeveLength, 1))
                .' سانتی‌متر کوتاه‌تر از قد بازوی این بدن بریده شده است؛ دمِ آستین روی ساعد می‌ایستد.';
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'cropped-']));

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
