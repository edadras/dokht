<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ پیراهن و بالاتنهٔ مردانه.
 *
 * روی همان درفت‌های مردانه‌ای سوار است که بلوکِ مردانه را می‌شناسند: بی ساسونِ
 * سینه، کمرگیریِ کم، سرشانهٔ پهن‌تر و حلقهٔ گودتر.
 *
 * چرا سه کلاسِ جدا و نه یکی: گروهِ فهرست یک ویژگیِ *ایستای* کلاس است. اگر هر سه
 * دسته در یک کلاس بنشینند، پالتوی مردانه هم زیر «پیراهن» فهرست می‌شود — و
 * آزمونِ پیراهن هم آن را مثل پیراهن می‌سنجد و به‌درستی می‌افتد.
 */
class MensTopVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'shirt';
    }

    /**
     * بالاتنه: کلید ⇒ [نام، درفتِ پایه، بلندی‌های آستین].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const TOPS = [
        'dress' => ['پیراهن رسمی', 'mens_shirt_dress', ['short', 'long']],
        'casual' => ['پیراهن راحتی', 'mens_shirt_casual', ['short', 'long']],
        'camp' => ['پیراهن یقه‌باز', 'mens_shirt_camp', ['short', 'long']],
        'mandarin' => ['پیراهن یقه‌دیپلمات', 'mens_shirt_mandarin', ['short', 'long']],
        'flannel' => ['پیراهن پشمی', 'mens_shirt_flannel', ['long']],
        'grandad' => ['پیراهن بی‌یقه', 'mens_shirt_grandad', ['short', 'long']],
        'tunic' => ['تونیک مردانه', 'mens_tunic', ['short', 'long']],
    ];

    /**
     * آستین: کلید ⇒ [نام، بلندی].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const SLEEVES = [
        'short' => ['آستین کوتاه', 22.0],
        'long' => ['آستین بلند', 60.0],
    ];

    /**
     * قدِ بالاتنه: کلید ⇒ [نام، سانتی‌متر از خط کمر].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const TOP_LENGTHS = [
        'short' => ['کوتاه', 14.0],
        'regular' => ['معمولی', 22.0],
        'long' => ['بلند', 32.0],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::TOPS as $top => [$topName, $base, $sleeves]) {
            foreach ($sleeves as $sleeve) {
                [$sleeveName, $sleeveLength] = static::SLEEVES[$sleeve];

                foreach (static::TOP_LENGTHS as $length => [$lengthName, $cm]) {
                    $rows['mens_'.$top.'_'.$sleeve.'_'.$length] = [
                        'title' => $topName.' مردانه، '.$sleeveName.'، '.$lengthName,
                        'base' => $base,
                        'params' => ['sleeve_length' => $sleeveLength, 'body_length' => $cm],
                    ];
                }
            }
        }

        return $rows;
    }
}
