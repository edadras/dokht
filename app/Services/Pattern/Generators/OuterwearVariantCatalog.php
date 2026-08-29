<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ کت، پالتو و کاپشن.
 *
 * لایهٔ رویی دو انتخابِ روشن دارد: چه فرمی، و تا کجا. قد را با پارامترِ خودِ
 * درفت عوض می‌کنیم و باقی — یقه، بست، آستر، جیب — همان‌جا می‌ماند.
 *
 * هر فرم فقط قدهایی را می‌گیرد که رویش پوشیده می‌شود: بمبر بلند نمی‌شود، و
 * پالتوی کلاسیک کوتاه‌تر از باسن دیگر پالتو نیست.
 */
class OuterwearVariantCatalog extends CatalogVariantBase
{
    public static function group(): string
    {
        return 'outerwear';
    }

    /**
     * فرم‌ها: کلید ⇒ [نام، درفتِ پایه، قدهای پذیرفته، فرم‌های پذیرفته، نامِ پارامترِ قد].
     *
     * چند مدل فقط فرمِ «معمولی» را می‌گیرند: بارانی و دافل خودشان از پیش گشادند
     * (روی لباسِ دیگر پوشیده می‌شوند) و فرمِ گشادتر آزادیِ باسنشان را از بازهٔ
     * کاتالوگ بیرون می‌برد.
     *
     * ستونِ آخر برای درفت‌هایی است که قدشان نامِ دیگری دارد. کتِ تک از روزِ اول
     * «بلندی تنه از کمر» داشته، نه «قد»؛ تا وقتی جدول کورکورانه «قد» می‌فرستاد،
     * هر سه ردیفِ کوتاه و تا باسن و تا ران یک الگوی یکسان بودند.
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>, 3?: array<int, string>, 4?: string}>
     */
    protected const SHAPES = [
        'blazer' => ['کت تک', 'blazer', ['crop', 'hip', 'thigh'], ['regular', 'loose'], 'body_length'],
        'suit' => ['کت رسمی', 'suit_jacket', ['hip', 'thigh']],
        'double' => ['کت دوردیف', 'jacket_double_breasted', ['hip', 'thigh']],
        'cropped' => ['کت کوتاه', 'jacket_cropped', ['crop', 'hip']],
        'biker' => ['کت چرم', 'jacket_biker', ['crop', 'hip']],
        'work' => ['کت کار', 'jacket_work', ['crop', 'hip', 'thigh']],
        'bomber' => ['بمبر', 'bomber', ['crop', 'hip']],
        'windbreaker' => ['بادگیر', 'jacket_windbreaker', ['hip', 'thigh']],
        'anorak' => ['آنوراک', 'jacket_anorak', ['hip', 'thigh']],
        'parka' => ['پارکا', 'jacket_parka', ['thigh', 'knee']],
        'puffer' => ['پافر', 'jacket_puffer', ['crop', 'hip', 'thigh', 'knee']],
        'overcoat' => ['پالتو', 'coat_overcoat', ['thigh', 'knee', 'calf']],
        'peacoat' => ['پالتو دوردیف کوتاه', 'coat_peacoat', ['hip', 'thigh']],
        'duffle' => ['دافل', 'coat_duffle', ['thigh', 'knee'], ['regular']],
        'trench' => ['بارانی', 'coat_trench', ['thigh', 'knee', 'calf']],
        'wrapcoat' => ['پالتو کمربندی', 'coat_wrap', ['thigh', 'knee', 'calf']],
        'raincoat' => ['بارانی سبک', 'raincoat', ['thigh', 'knee'], ['regular']],
        'cape' => ['شنل', 'coat_cape', ['hip', 'thigh', 'knee']],
        'vest' => ['جلیقه', 'vest_utility', ['crop', 'hip']],
    ];

    /**
     * قدها: کلید ⇒ [نام، سانتی‌متر از خط کمر].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LENGTHS = [
        'crop' => ['کوتاه', 8.0],
        'hip' => ['تا باسن', 24.0],
        'thigh' => ['تا ران', 48.0],
        'knee' => ['تا زانو', 72.0],
        'calf' => ['بلند', 96.0],
    ];

    /**
     * آستر: کلید ⇒ [نام، دارد یا نه].
     *
     * آستر برچسب نیست؛ یک نسخهٔ کاملِ دیگر از تنه و آستین است که باید بریده و
     * دوخته شود. برندها همان کت را با آستر و بی‌آستر می‌فروشند (یکی برای زمستان
     * و یکی برای بهار) و الگویشان واقعاً دو چیز است.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    protected const LININGS = [
        'lined' => ['آستردار', true],
        'unlined' => ['بی‌آستر', false],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SHAPES as $shape => $row) {
            [$shapeName, $base, $lengths] = $row;
            $fits = $row[3] ?? ['regular', 'loose'];
            $lengthParam = $row[4] ?? 'length';

            foreach ($lengths as $length) {
                [$lengthName, $cm] = static::LENGTHS[$length];

                foreach (['regular' => 'معمولی', 'loose' => 'گشاد'] as $fit => $fitName) {
                    if (! in_array($fit, $fits, true)) {
                        continue;
                    }

                    foreach (static::LININGS as $lining => [$liningName, $hasLining]) {
                        $rows['outer_'.$shape.'_'.$length.'_'.$fit.'_'.$lining] = [
                            'title' => $shapeName.' '.$lengthName.'، فرم '.$fitName.'، '.$liningName,
                            'base' => $base,
                            'params' => [$lengthParam => $cm, 'fit' => $fit, 'lining' => $hasLining],
                        ];
                    }
                }
            }
        }

        return $rows;
    }
}
