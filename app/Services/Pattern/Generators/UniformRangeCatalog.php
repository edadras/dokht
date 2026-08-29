<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس کار و فرم.
 *
 * لباسِ فرم بازارِ خودش را دارد و همان‌طور که برندهای پوشاک یک تی‌شرت را در چند
 * قد و آستین می‌فروشند، تولیدکنندهٔ لباسِ کار هم یک روپوش را در سه قد، دو نوع
 * بست و با و بی جیب می‌دهد — چون سفارش‌دهنده (بیمارستان، رستوران، کارگاه) هر
 * بار یکی از این‌ها را می‌خواهد.
 *
 * محورها همه ساختِ قطعه را عوض می‌کنند: قد خطِ پایین را می‌برد، آستین قطعهٔ
 * آستین را می‌آورد یا برمی‌دارد، جیب یک قطعهٔ اضافه است، و بستِ جلو بینِ پاتلتِ
 * دکمه و لتِ زیرِ زیپ فرق می‌گذارد.
 *
 * شلوار و پیش‌بند اسکلتِ دیگری دارند، پس محورهای خودشان را می‌گیرند.
 */
class UniformRangeCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'uniform';
    }

    /**
     * بالاتنه‌ها: کلید ⇒ [نام، درفتِ پایه، سه قد، آستین‌ها، بستِ جلو دارد، یقهٔ انتخابی دارد].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, float>, 3: array<int, string>, 4: bool, 5: bool}>
     */
    protected const TOPS = [
        'lab_coat' => ['روپوش آزمایشگاه', 'uniform_lab_coat', [46, 58, 76], ['short', 'long'], true, true],
        'school_shirt' => ['پیراهن مدرسه', 'uniform_school_shirt', [16, 20, 32], ['short', 'long'], true, true],
        'nurse_dress' => ['روپوش پرستاری', 'uniform_nurse_dress', [52, 62, 80], ['none', 'short', 'long'], true, true],
        'office_dress' => ['پیراهن اداری', 'uniform_office_dress', [50, 58, 74], ['none', 'short', 'long'], true, true],
        'office_shirt' => ['پیراهن اداری کوتاه', 'uniform_office_shirt', [18, 22, 34], ['short', 'long'], true, true],
        'work_jacket' => ['کت کار', 'uniform_work_jacket', [14, 16, 28], ['short', 'long'], true, true],
        'chef_jacket' => ['کت آشپزی', 'uniform_chef_jacket', [18, 22, 34], ['short', 'long'], true, true],
        'work_shirt' => ['پیراهن کار', 'uniform_work_shirt', [18, 22, 34], ['short', 'long'], true, true],
        // این دو از سر پوشیده می‌شوند: نه بستِ جلو دارند نه یقهٔ انتخابی
        'scrub_top' => ['اسکراب', 'uniform_scrub_top', [14, 18, 30], ['none', 'short', 'long'], false, false],
        'smock' => ['روپوش کارگاه', 'uniform_smock', [26, 34, 48], ['short', 'long'], false, false],
        // جلیقه‌ها بی‌آستین‌اند و آستین محورشان نیست
        'hi_vis' => ['جلیقه شبرنگ', 'uniform_hi_vis_vest', [12, 14, 24], ['none'], true, false],
        'service_vest' => ['جلیقه سرویس', 'uniform_service_vest', [12, 16, 26], ['none'], true, false],
    ];

    /**
     * شلوارها: کلید ⇒ [نام، درفتِ پایه، کمربندِ انتخابی دارد].
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    protected const BOTTOMS = [
        'chef_pants' => ['شلوار آشپزی', 'uniform_chef_pants', false],
        'scrub_pants' => ['شلوار اسکراب', 'uniform_scrub_pants', false],
        'work_trousers' => ['شلوار کار', 'uniform_work_trousers', true],
    ];

    /**
     * پیش‌بندها: کلید ⇒ [نام، درفتِ پایه، سه قدِ دامن].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, float>}>
     */
    protected const APRONS = [
        'chef_apron' => ['پیش‌بند آشپزی', 'uniform_chef_apron', [58, 72, 92]],
        'waist_apron' => ['پیش‌بند کمری', 'uniform_waist_apron', [32, 42, 58]],
    ];

    /** نامِ سه قد، هم‌ترتیب با ستونِ قدها. */
    protected const LENGTH_NAMES = ['قد کوتاه', 'قد معمولی', 'قد بلند'];

    /** کلیدِ سه قد. */
    protected const LENGTH_KEYS = ['short', 'mid', 'long'];

    /**
     * آستین: کلید ⇒ [نام، سبک، بلندی].
     *
     * @var array<string, array{0: string, 1: string, 2: float}>
     */
    protected const SLEEVES = [
        'none' => ['بی‌آستین', 'none', 0.0],
        'short' => ['آستین کوتاه', 'set_in', 24.0],
        'long' => ['آستین بلند', 'set_in', 58.0],
    ];

    /**
     * بستِ جلو: کلید ⇒ [نام، مقدارِ پارامتر].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const OPENINGS = [
        'button' => ['بست دکمه', 'button'],
        'zip' => ['بست زیپ', 'zip'],
    ];

    /**
     * جیب: کلید ⇒ [نام، دارد یا نه].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const POCKETS = [
        'pocket' => ['جیب‌دار', true],
        'plain' => ['بی‌جیب', false],
    ];

    /**
     * فاق: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const RISES = [
        'mid' => 'فاق متوسط',
        'high' => 'فاق بلند',
    ];

    /**
     * قدِ پاچه: کلید ⇒ [نام، تغییر نسبت به قد داخل پا].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LEG_LENGTHS = [
        'crop' => ['کوتاه', -18.0],
        'ankle' => ['تا قوزک', -6.0],
        'full' => ['تمام‌قد', 0.0],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = array_merge(static::tops(), static::bottoms(), static::aprons());

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function tops(): array
    {
        $rows = [];

        foreach (static::TOPS as $top => [$name, $base, $lengths, $sleeves, $hasOpening, $hasCollar]) {
            foreach ($lengths as $index => $cm) {
                foreach ($sleeves as $sleeve) {
                    [$sleeveName, $style, $sleeveLength] = static::SLEEVES[$sleeve];

                    foreach (static::POCKETS as $pocket => [$pocketName, $hasPocket]) {
                        foreach ($hasOpening ? array_keys(static::OPENINGS) : [null] as $opening) {
                            $key = 'uni_'.$top.'_'.static::LENGTH_KEYS[$index].'_'.$sleeve.'_'.$pocket
                                .($opening === null ? '' : '_'.$opening);

                            $params = [
                                'length' => (float) $cm,
                                'sleeve_style' => $style,
                                'sleeve_length' => $sleeveLength,
                                'pocket' => $hasPocket,
                            ];

                            $title = $name.' '.static::LENGTH_NAMES[$index].'، '.$sleeveName.'، '.$pocketName;

                            if ($opening !== null) {
                                $params['front_opening'] = static::OPENINGS[$opening][1];
                                $title .= '، '.static::OPENINGS[$opening][0];
                            }

                            // یقه فقط جایی چرخانده می‌شود که درفت آن را بشناسد؛
                            // اسکراب و روپوش کارگاه یقهٔ گردِ ثابت دارند
                            if ($hasCollar) {
                                $rows[$key.'_stand'] = [
                                    'title' => $title.'، یقه ایستاده',
                                    'base' => $base,
                                    'params' => array_merge($params, ['collar' => 'stand']),
                                ];
                                $rows[$key.'_turn'] = [
                                    'title' => $title.'، یقه برگردان',
                                    'base' => $base,
                                    'params' => array_merge($params, ['collar' => 'turn']),
                                ];

                                continue;
                            }

                            $rows[$key] = ['title' => $title, 'base' => $base, 'params' => $params];
                        }
                    }
                }
            }
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function bottoms(): array
    {
        $rows = [];

        foreach (static::BOTTOMS as $bottom => [$name, $base, $hasBand]) {
            foreach (static::RISES as $rise => $riseName) {
                foreach (static::LEG_LENGTHS as $length => [$lengthName, $change]) {
                    $params = ['rise' => $rise, 'length_extra' => $change];

                    if (! $hasBand) {
                        $rows['uni_'.$bottom.'_'.$rise.'_'.$length] = [
                            'title' => $name.'، '.$riseName.'، '.$lengthName,
                            'base' => $base,
                            'params' => $params,
                        ];

                        continue;
                    }

                    foreach (['band' => 'کمربنددار', 'faced' => 'بی‌کمربند'] as $waist => $waistName) {
                        $rows['uni_'.$bottom.'_'.$rise.'_'.$length.'_'.$waist] = [
                            'title' => $name.'، '.$riseName.'، '.$lengthName.'، '.$waistName,
                            'base' => $base,
                            'params' => array_merge($params, ['waistband' => $waist === 'band']),
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function aprons(): array
    {
        $rows = [];

        foreach (static::APRONS as $apron => [$name, $base, $lengths]) {
            foreach ($lengths as $index => $cm) {
                for ($pockets = 0; $pockets <= 3; $pockets++) {
                    $rows['uni_'.$apron.'_'.static::LENGTH_KEYS[$index].'_p'.$pockets] = [
                        'title' => $name.' '.static::LENGTH_NAMES[$index].'، '
                            .($pockets === 0 ? 'بی‌جیب' : $pockets.' جیب'),
                        'base' => $base,
                        'params' => ['skirt_length' => (float) $cm, 'pocket_count' => $pockets],
                    ];
                }
            }
        }

        return $rows;
    }
}
