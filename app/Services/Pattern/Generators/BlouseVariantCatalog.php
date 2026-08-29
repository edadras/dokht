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

                    // خطِ باز، یقهٔ دوخته را برنمی‌دارد
                    $collar = $takesCollar && in_array($body, ['classic', 'fitted', 'longline'], true)
                        ? 'shirt'
                        : 'none';

                    $key = 'blouse_'.$body.'_'.$line.'_'.$arm;

                    $rows[$key] = [
                        'title' => 'شومیز '.$bodyName.'، '.$lineName.'، '.$armName,
                        'fit' => $fit,
                        'neckline' => $line,
                        'collar' => $collar,
                        'sleeve' => $arm,
                        'body_length' => $length,
                        'gathers' => $gathers,
                        'bust_dart' => $dart,
                        'use' => 'daily',
                        'opening' => $collar === 'none' ? 'closed' : 'button',
                    ];
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
