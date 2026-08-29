<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ شومیز و بلوز.
 *
 * پایهٔ شومیز از پیش سه محورِ «قطعه‌عوض‌کن» را می‌شناسد — فرمِ تنه، خطِ یقه و
 * آستین — و هر سه در الگو دیده می‌شوند، نه در برچسب. این جدول همان سه را در هم
 * ضرب می‌کند، با دو قید:
 *
 *   • یقهٔ دوخته‌شده فقط روی خطِ یقهٔ گرد و هفت می‌نشیند. یقهٔ مردانه روی خطِ
 *     قایقی یا آف‌شولدر جایی برای پایه ندارد و روی سرشانه می‌ایستد.
 *   • آستینِ بلند روی تنهٔ کراپ ساخته نمی‌شود؛ در بازار هم نیست.
 */
class BlouseVariantCatalog extends BlouseBaseGenerator implements VariantAware
{
    use HasVariants;

    /**
     * تنه: کلید ⇒ [نام، فرم، قد از خط کمر، چین، ساسون سینه].
     *
     * @var array<string, array{0: string, 1: string, 2: float, 3: float, 4: bool}>
     */
    protected const BODIES = [
        'classic' => ['کلاسیک', 'regular', 16.0, 0.0, true],
        'fitted' => ['جذب', 'fitted', 16.0, 0.0, true],
        'relaxed' => ['راحت', 'loose', 18.0, 0.0, false],
        'oversized' => ['اورسایز', 'loose', 22.0, 0.0, false],
        'crop' => ['کراپ', 'regular', 0.0, 0.0, true],
        'longline' => ['بلند', 'regular', 32.0, 0.0, true],
        'tunic' => ['تونیکی', 'loose', 44.0, 0.0, false],
        'gathered' => ['چین‌دار', 'loose', 18.0, 10.0, false],
    ];

    /**
     * خطِ یقه: کلید ⇒ [نام، آیا یقهٔ دوخته می‌پذیرد].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const LINES = [
        'round' => ['یقه گرد', true],
        'v' => ['یقه هفت', true],
        'scoop' => ['یقه گرد باز', false],
        'square' => ['یقه خشتی', false],
        'boat' => ['یقه قایقی', false],
        'sweetheart' => ['یقه دلبری', false],
        'u' => ['یقه U', false],
        'off_shoulder' => ['یقه آف‌شولدر', false],
    ];

    /**
     * یقهٔ دوخته: کلید ⇒ [نام، بستِ جلو].
     *
     * یقه و بستِ جلو با هم می‌آیند چون با هم قطعه می‌سازند: یقهٔ مردانه پایه و
     * سرِ یقه می‌خواهد و روی جلوی بسته جایی برای نشستن ندارد؛ یقهٔ کرواتی و
     * پاپیونی هم نوارِ خودشان را می‌آورند.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const COLLARS = [
        'none' => ['بی‌یقه', 'closed'],
        'shirt' => ['یقه مردانه', 'button'],
        'stand' => ['یقه ایستاده', 'button'],
        'tie' => ['یقه کرواتی', 'closed'],
        'bow' => ['یقه پاپیونی', 'closed'],
    ];

    /**
     * آستین: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const ARMS = [
        'none' => 'بی‌آستین',
        'cap' => 'آستین حلقه‌ای',
        'short' => 'آستین کوتاه',
        'three_quarter' => 'آستین سه‌ربع',
        'long' => 'آستین بلند',
        'puff' => 'آستین پفی',
        'bell' => 'آستین زنگوله‌ای',
        'flutter' => 'آستین کلوش کوتاه',
    ];

    /**
     * پرداختِ لبه‌ها: کلید ⇒ [نام، فرفری دارد یا نه].
     *
     * فرفر یک قطعهٔ اضافه است که بریده و چین داده و روی لبه دوخته می‌شود، نه یک
     * تزیینِ چاپی. برندها همان شومیز را در دو ساخت می‌فروشند.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const TRIMS = [
        'plain' => ['لبه ساده', false],
        'ruffled' => ['لبه فرفری', true],
    ];

    /**
     * کمربندِ پارچه‌ای: کلید ⇒ [نام، دارد یا نه].
     *
     * فقط روی تنه‌هایی که به خط کمر می‌رسند؛ کمربند روی شومیزِ کراپ جایی برای
     * بستن ندارد.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const BELTS = [
        'loose' => ['بی‌کمربند', false],
        'belted' => ['کمربنددار', true],
    ];

    /** تنه‌هایی که تا خط کمر یا پایین‌تر می‌آیند و کمربند می‌پذیرند. */
    protected const BELTABLE = ['classic', 'fitted', 'relaxed', 'longline', 'tunic', 'gathered'];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::BODIES as $body => [$bodyName, $fit, $length, $gathers, $dart]) {
            foreach (static::LINES as $line => [$lineName, $takesCollar]) {
                foreach (static::ARMS as $arm => $armName) {
                    if ($body === 'crop' && in_array($arm, ['long', 'bell'], true)) {
                        continue;
                    }

                    foreach (static::COLLARS as $collar => [$collarName, $opening]) {
                        // خطِ یقهٔ باز پایه‌ای برای یقهٔ دوخته ندارد؛ یقه روی
                        // سرشانه می‌ایستد به‌جای اینکه دورِ گردن بنشیند
                        if ($collar !== 'none' && ! $takesCollar) {
                            continue;
                        }

                        // شومیزِ کراپ و اورسایز یقهٔ کرواتی و پاپیونی نمی‌گیرند
                        if (in_array($collar, ['tie', 'bow'], true)
                            && in_array($body, ['crop', 'oversized', 'tunic'], true)) {
                            continue;
                        }

                        $belts = in_array($body, static::BELTABLE, true)
                            ? static::BELTS
                            : ['loose' => static::BELTS['loose']];

                        foreach (static::TRIMS as $trim => [$trimName, $ruffle]) {
                            foreach ($belts as $belt => [$beltName, $hasBelt]) {
                                $key = 'blouse_'.$body.'_'.$line.'_'.$arm.'_'.$collar.'_'.$trim
                                    .($hasBelt ? '_belted' : '');

                                $rows[$key] = [
                                    'title' => 'شومیز '.$bodyName.'، '.$lineName.'، '.$armName.'، '.$collarName
                                        .'، '.$trimName.($hasBelt ? '، '.$beltName : ''),
                                    'fit' => $fit,
                                    'neckline' => $line,
                                    'collar' => $collar,
                                    'sleeve' => $arm,
                                    'body_length' => $length,
                                    'gathers' => $gathers,
                                    'bust_dart' => $dart,
                                    'use' => 'daily',
                                    'opening' => $opening,
                                    'defaults' => ['ruffle' => $ruffle, 'tie_belt' => $hasBelt],
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }

    protected function blouse(): array
    {
        return $this->spec();
    }
}
