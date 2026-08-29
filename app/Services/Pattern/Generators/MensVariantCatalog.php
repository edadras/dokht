<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس مردانه.
 *
 * روی همان درفت‌های مردانه‌ای سوار است که بلوکِ مردانه را می‌شناسند (بی ساسونِ
 * سینه، کمرگیریِ کم، سرشانهٔ پهن‌تر، حلقهٔ گودتر) و فقط دو چیز را عوض می‌کند:
 * قد و آستین برای بالاتنه، و قد و فاق برای شلوار.
 */
class MensVariantCatalog extends CatalogVariantBase
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

    /**
     * شلوار: کلید ⇒ [نام، درفتِ پایه].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const BOTTOMS = [
        'trousers' => ['شلوار پارچه‌ای', 'mens_trousers_classic'],
        'suit' => ['شلوار کت‌وشلوار', 'mens_trousers_suit'],
        'pleated' => ['شلوار پیلی‌دار', 'mens_trousers_pleated'],
        'chino' => ['شلوار چینو', 'mens_chino'],
        'jeans' => ['شلوار جین', 'mens_jeans'],
        'slim' => ['شلوار جین جذب', 'mens_jeans_slim'],
        'cargo' => ['شلوار کارگو', 'mens_cargo'],
        'jogger' => ['شلوار جاگر', 'mens_jogger'],
    ];

    /**
     * قدِ شلوار: کلید ⇒ [نام، تغییر نسبت به قد داخل پا].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LEG_LENGTHS = [
        'crop' => ['کراپ', -20.0],
        'ankle' => ['تا قوزک', -8.0],
        'full' => ['تمام‌قد', 0.0],
    ];

    /**
     * پوشاک بیرونی: کلید ⇒ [نام، درفتِ پایه، قدها].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const OUTER = [
        'blazer' => ['کت تک', 'mens_blazer', ['hip', 'thigh']],
        'suitjacket' => ['کت رسمی', 'mens_suit_jacket', ['hip', 'thigh']],
        'overcoat' => ['پالتو', 'mens_overcoat', ['thigh', 'knee']],
        'trench' => ['بارانی', 'mens_trench', ['thigh', 'knee']],
        'bomber' => ['بمبر', 'mens_bomber', ['crop', 'hip']],
        'parka' => ['پارکا', 'mens_parka', ['thigh', 'knee']],
        'puffer' => ['پافر', 'mens_puffer', ['hip', 'thigh']],
        'denim' => ['کت جین', 'mens_denim_jacket', ['crop', 'hip']],
        'leather' => ['کت چرم', 'mens_leather_jacket', ['crop', 'hip']],
    ];

    /** قدِ پوشاک بیرونی. */
    protected const OUTER_LENGTHS = [
        'crop' => ['کوتاه', 10.0],
        'hip' => ['تا باسن', 24.0],
        'thigh' => ['تا ران', 48.0],
        'knee' => ['تا زانو', 74.0],
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

        foreach (static::BOTTOMS as $bottom => [$bottomName, $base]) {
            foreach (['low', 'mid', 'high'] as $rise) {
                foreach (static::LEG_LENGTHS as $length => [$lengthName, $change]) {
                    $rows['mens_'.$bottom.'_'.$rise.'_'.$length] = [
                        'title' => $bottomName.' مردانه، '
                            .match ($rise) { 'low' => 'فاق کوتاه', 'high' => 'فاق بلند', default => 'فاق متوسط' }
                            .'، '.$lengthName,
                        'base' => $base,
                        'params' => ['rise' => $rise, 'length_extra' => $change],
                    ];
                }
            }
        }

        foreach (static::OUTER as $outer => [$outerName, $base, $lengths]) {
            foreach ($lengths as $length) {
                [$lengthName, $cm] = static::OUTER_LENGTHS[$length];

                $rows['mens_outer_'.$outer.'_'.$length] = [
                    'title' => $outerName.' مردانه، '.$lengthName,
                    'base' => $base,
                    'params' => ['length' => $cm],
                ];
            }
        }

        return $rows;
    }
}
