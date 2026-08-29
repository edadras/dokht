<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ پوشاک بیرونیِ مردانه.
 *
 * روی همان درفت‌های مردانه‌ای سوار است که بلوکِ مردانه را می‌شناسند: بی ساسونِ
 * سینه، کمرگیریِ کم، سرشانهٔ پهن‌تر و حلقهٔ گودتر.
 *
 * چرا سه کلاسِ جدا و نه یکی: گروهِ فهرست یک ویژگیِ *ایستای* کلاس است. اگر هر سه
 * دسته در یک کلاس بنشینند، پالتوی مردانه هم زیر «پیراهن» فهرست می‌شود — و
 * آزمونِ پیراهن هم آن را مثل پیراهن می‌سنجد و به‌درستی می‌افتد.
 */
class MensOuterVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'outerwear';
    }

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
