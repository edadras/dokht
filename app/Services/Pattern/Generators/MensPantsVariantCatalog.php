<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ شلوار مردانه.
 *
 * روی همان درفت‌های مردانه‌ای سوار است که بلوکِ مردانه را می‌شناسند: بی ساسونِ
 * سینه، کمرگیریِ کم، سرشانهٔ پهن‌تر و حلقهٔ گودتر.
 *
 * چرا سه کلاسِ جدا و نه یکی: گروهِ فهرست یک ویژگیِ *ایستای* کلاس است. اگر هر سه
 * دسته در یک کلاس بنشینند، پالتوی مردانه هم زیر «پیراهن» فهرست می‌شود — و
 * آزمونِ پیراهن هم آن را مثل پیراهن می‌سنجد و به‌درستی می‌افتد.
 */
class MensPantsVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'pants';
    }

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

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

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

        return $rows;
    }
}
