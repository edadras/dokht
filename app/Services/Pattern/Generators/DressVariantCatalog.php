<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ پیراهن زنانه.
 *
 * پیراهن با چهار انتخاب ساخته می‌شود و هر چهار، *قطعه* را عوض می‌کنند — پس هر
 * ترکیب یک الگوی جداست، نه یک برچسب:
 *
 *   سایه      دامن از کجا و چطور باز می‌شود (خط A، راسته، کلوش، پیلی، چین، …)
 *   قد        کوتاه، زانو، میدی، ماکسی
 *   آستین     بی‌آستین، حلقه‌ای، کوتاه، سه‌ربع، بلند
 *   یقه       گرد، هفت، خشتی، قایقی
 *
 * ترکیب‌ها کورکورانه ضرب نشده‌اند: هر سایه فقط قدهایی را می‌پذیرد که روی آن
 * معنا دارند (دامنِ پیلیِ کوتاه هست، ولی ماکسیِ مدادی نه) و هر قد فقط آستین‌هایی
 * را که با آن پوشیده می‌شود. نامِ فارسیِ هر ردیف هم از همان سه انتخاب ساخته
 * می‌شود، پس در فهرست دقیقاً همان چیزی خوانده می‌شود که هست.
 */
class DressVariantCatalog extends DressCatalogBaseGenerator implements VariantAware
{
    use HasVariants;

    /**
     * سایه‌ها: کلید ⇒ [نام، فرم، دامن، شکلِ تنه، بازشدنِ دم، قدهای پذیرفته].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float, 5: array<int, string>}>
     */
    protected const SILHOUETTES = [
        'aline' => ['خط A', 'waisted', 'skirt_a_line', 'fitted', 0.0, ['mini', 'knee', 'midi', 'maxi']],
        'straight' => ['راسته', 'waisted', 'skirt_straight', 'fitted', 0.0, ['mini', 'knee', 'midi']],
        'pencil' => ['مدادی', 'waisted', 'skirt_pencil', 'fitted', 0.0, ['knee', 'midi']],
        'circle' => ['کلوش', 'waisted', 'skirt_circle_half', 'fitted', 0.0, ['knee', 'midi', 'maxi']],
        'pleated' => ['پیلی‌دار', 'waisted', 'skirt_pleat_knife', 'fitted', 0.0, ['mini', 'knee', 'midi']],
        'boxpleat' => ['پیلی پهن', 'waisted', 'skirt_pleat_box', 'fitted', 0.0, ['mini', 'knee', 'midi']],
        'gathered' => ['چین‌دار', 'waisted', 'skirt_gathered', 'fitted', 0.0, ['mini', 'knee', 'midi', 'maxi']],
        'tiered' => ['طبقه‌ای', 'waisted', 'skirt_tiered', 'fitted', 0.0, ['knee', 'midi', 'maxi']],
        'gored' => ['ترک‌دار', 'waisted', 'skirt_gored', 'fitted', 0.0, ['knee', 'midi', 'maxi']],
        'trumpet' => ['شیپوری', 'waisted', 'skirt_trumpet', 'fitted', 0.0, ['midi', 'maxi']],
        'wrapskirt' => ['دامن پاکتی', 'waisted', 'skirt_wrap', 'fitted', 0.0, ['knee', 'midi']],
        'yoke' => ['یوک‌دار', 'waisted', 'skirt_yoke', 'fitted', 0.0, ['mini', 'knee', 'midi']],
        'shift' => ['شیفت', 'onepiece', '', 'straight', 3.0, ['mini', 'knee', 'midi']],
        'trapeze' => ['ذوزنقه‌ای', 'onepiece', '', 'trapeze', 6.0, ['mini', 'knee', 'midi']],
        'column' => ['ستونی', 'onepiece', '', 'fitted', 0.0, ['knee', 'midi', 'maxi']],
        'tent' => ['چادری', 'onepiece', '', 'trapeze', 8.0, ['knee', 'midi', 'maxi']],
    ];

    /**
     * قدها: کلید ⇒ [نام، قدِ دامن از خط کمر، قدِ یک‌تکه از خط کمر].
     *
     * @var array<string, array{0: string, 1: float, 2: float}>
     */
    protected const LENGTHS = [
        'mini' => ['کوتاه', 34.0, 32.0],
        'knee' => ['تا زانو', 52.0, 48.0],
        'midi' => ['میدی', 72.0, 68.0],
        'maxi' => ['ماکسی', 98.0, 94.0],
    ];

    /**
     * آستین‌ها: کلید ⇒ [نام، سبک، بلندی].
     *
     * @var array<string, array{0: string, 1: string, 2: float}>
     */
    protected const SLEEVES = [
        'none' => ['بی‌آستین', 'none', 0.0],
        'cap' => ['آستین حلقه‌ای', 'set_in', 10.0],
        'short' => ['آستین کوتاه', 'set_in', 20.0],
        'elbow' => ['آستین تا آرنج', 'set_in', 34.0],
        'long' => ['آستین بلند', 'set_in', 58.0],
    ];

    /**
     * فرمِ تنه: کلید ⇒ نام.
     *
     * فرم فقط برچسب نیست؛ آزادیِ سینه و کمر و باسن را با هم عوض می‌کند و شکلِ
     * درزِ پهلو را. برندها همین را «fit» می‌نامند و یک مدل را در دو فرم
     * می‌فروشند: همان سایه، یک بار اندام‌نما و یک بار راحت.
     *
     * @var array<string, string>
     */
    protected const FITS = [
        'regular' => 'فرم معمولی',
        'fitted' => 'فرم جذب',
    ];

    /**
     * یقه‌ها: کلید ⇒ [نام، پهنای اضافه، گودیِ اضافهٔ جلو].
     *
     * @var array<string, array{0: string, 1: float, 2: float}>
     */
    protected const NECKS = [
        'round' => ['یقه گرد', 0.0, 0.0],
        'v' => ['یقه هفت', 1.0, 7.0],
        'square' => ['یقه خشتی', 3.0, 5.0],
        'boat' => ['یقه قایقی', 6.0, -1.0],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SILHOUETTES as $shape => [$shapeName, $form, $skirt, $panel, $flare, $lengths]) {
            foreach ($lengths as $length) {
                [$lengthName, $skirtLength, $bodyLength] = static::LENGTHS[$length];

                foreach (static::SLEEVES as $sleeve => [$sleeveName, $style, $sleeveLength]) {
                    // آستینِ بلند روی پیراهنِ ماکسیِ بی‌آستین‌طور معنا دارد، ولی
                    // ترکیبِ «کوتاهِ آستین‌بلند» را عمداً نمی‌سازیم: بازار ندارد و
                    // فقط فهرست را شلوغ می‌کند
                    if ($length === 'mini' && $sleeve === 'long') {
                        continue;
                    }

                    foreach (static::NECKS as $neck => [$neckName, $neckWidth, $neckDepth]) {
                        // یقهٔ قایقی روی لباسِ بی‌آستین از شانه می‌افتد
                        if ($neck === 'boat' && $sleeve === 'none') {
                            continue;
                        }

                        foreach (static::FITS as $fit => $fitName) {
                            $key = 'dress_'.$shape.'_'.$length.'_'.$sleeve.'_'.$neck.'_'.$fit;

                            $rows[$key] = [
                                'title' => 'پیراهن '.$shapeName.' '.$lengthName.'، '.$sleeveName.'، '.$neckName.'، '.$fitName,
                                'form' => $form,
                                'shape' => $panel,
                                'fit' => $fit,
                                'hem_flare' => $flare,
                                'length' => $bodyLength,
                                'skirt' => $skirt !== '' ? $skirt : null,
                                'skirt_length' => $skirtLength,
                                'sleeve' => $style,
                                'sleeve_length' => $sleeveLength,
                                'bust_dart' => true,
                                'waist_dart' => $form === 'waisted',
                                'block' => [
                                    'neck_width_extra' => 0.5 + $neckWidth,
                                    'front_neck_depth_extra' => 2.0 + $neckDepth,
                                ],
                            ];

                            if ($skirt === '') {
                                unset($rows[$key]['skirt'], $rows[$key]['skirt_length']);
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }

    protected function dress(): array
    {
        return $this->spec();
    }
}
