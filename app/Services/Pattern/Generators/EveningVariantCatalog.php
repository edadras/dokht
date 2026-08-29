<?php

namespace App\Services\Pattern\Generators;

/**
 * خانوادهٔ جدولیِ لباس مجلسی و عروس.
 *
 * لباسِ مجلسی از سه انتخاب ساخته می‌شود و هر سه قطعه را عوض می‌کنند: خطِ بالای
 * بالاتنه، کدام دامن، و تا کجا. جای خطِ کمر (زیر سینه، طبیعی، افتاده) محورِ
 * چهارم است و آن هم قطعه را عوض می‌کند، نه فقط ظاهر را.
 */
class EveningVariantCatalog extends EveningGownBaseGenerator implements VariantAware
{
    use HasVariants;

    /**
     * دامن‌ها: کلید ⇒ [نام، دامنِ کاتالوگ، قدهای پذیرفته].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    protected const SKIRTS = [
        'aline' => ['خط A', 'skirt_a_line', ['knee', 'midi', 'floor']],
        'column' => ['ستونی', 'skirt_straight', ['knee', 'midi', 'floor']],
        'circle' => ['کلوش', 'skirt_circle_half', ['knee', 'midi', 'floor']],
        'gored' => ['ترک‌دار', 'skirt_gored', ['midi', 'floor']],
        'trumpet' => ['شیپوری', 'skirt_trumpet', ['midi', 'floor']],
        'mermaid' => ['ماهی', 'skirt_mermaid', ['floor']],
        'ballgown' => ['پفی', 'skirt_ball_gown', ['floor']],
        'tiered' => ['طبقه‌ای', 'skirt_tiered', ['midi', 'floor']],
        'pleated' => ['پیلی‌دار', 'skirt_pleat_knife', ['knee', 'midi', 'floor']],
    ];

    /**
     * قدها: کلید ⇒ [نام، سانتی‌متر از خط کمر].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    protected const LENGTHS = [
        'knee' => ['کوتاه', 56.0],
        'midi' => ['میدی', 78.0],
        'floor' => ['بلند', 112.0],
    ];

    /**
     * خطِ بالا: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const NECKLINES = [
        'sweetheart' => 'یقه قلبی',
        'straight' => 'خط بالای صاف',
        'v' => 'یقه هفت',
        'scoop' => 'یقه گرد',
        'strap' => 'بنددار',
        'halter' => 'هالتر',
    ];

    /**
     * جای خطِ کمر: کلید ⇒ نام.
     *
     * @var array<string, string>
     */
    protected const WAISTS = [
        'natural' => 'کمر طبیعی',
        'empire' => 'کمر زیر سینه',
    ];

    /**
     * بستِ پشت: کلید ⇒ نام.
     *
     * سه ساختِ متفاوت، نه سه نام: زیپِ مخفی فقط یک نشانه می‌خواهد، بندِ کشی
     * ردیفِ حلقه و پشت‌بندِ تقویتی می‌آورد، و دکمهٔ مروارید پاتلتِ زیرِ خودش را.
     *
     * @var array<string, string>
     */
    protected const CLOSURES = [
        'zip' => 'زیپ مخفی',
        'lacing' => 'بند کشی',
        'buttons' => 'دکمه مروارید',
    ];

    /**
     * آستر: کلید ⇒ [نام، مقدارِ پارامتر].
     *
     * لباس مجلسی بیش از هر لباسِ دیگری به آستر وابسته است: پارچهٔ ساتن و توری و
     * حریر بدون آستر پوشیده نمی‌شوند، و آستر یک دست قطعهٔ کاملِ دیگر است که
     * بریده و دوخته می‌شود. برندها همان مدل را در سه ساخت می‌دهند.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const LININGS = [
        'full' => ['آستر کامل', 'full'],
        'bodice' => ['آستر بالاتنه', 'bodice'],
        'unlined' => ['بی‌آستر', 'none'],
    ];

    public static function variants(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        foreach (static::SKIRTS as $skirt => [$skirtName, $base, $lengths]) {
            foreach ($lengths as $length) {
                [$lengthName, $cm] = static::LENGTHS[$length];

                foreach (static::NECKLINES as $neck => $neckName) {
                    foreach (static::WAISTS as $waist => $waistName) {
                        // کمرِ زیرِ سینه با دامنِ ماهی و شیپوری جور درنمی‌آید:
                        // آن دو فرمشان را از باسن می‌گیرند، نه از زیرِ سینه
                        if ($waist === 'empire' && in_array($skirt, ['mermaid', 'trumpet'], true)) {
                            continue;
                        }

                        foreach (static::CLOSURES as $closure => $closureName) {
                            foreach (static::LININGS as $lining => [$liningName, $liningValue]) {
                                $key = 'evening_'.$skirt.'_'.$length.'_'.$neck.'_'.$waist.'_'.$closure.'_'.$lining;

                                $rows[$key] = [
                                    'title' => 'لباس مجلسی '.$skirtName.' '.$lengthName.'، '.$neckName.'، '
                                        .$waistName.'، '.$closureName.'، '.$liningName,
                                    'skirt' => $base,
                                    'length' => $cm,
                                    'neckline' => $neck,
                                    'bodice_length' => $waist,
                                    'closure' => $closure,
                                    'lining' => $liningValue,
                                    'skirt_params' => $skirt === 'mermaid' ? ['flare_start' => round($cm * 0.68)] : [],
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }

    protected function gown(): array
    {
        return $this->spec();
    }
}
