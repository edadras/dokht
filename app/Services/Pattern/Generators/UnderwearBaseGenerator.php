<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\CutsStretchFabric;
use App\Services\Pattern\Geometry;

/**
 * پایه مشترک لباس زیر.
 *
 * لباس زیر از هر لباس دیگری در این کاتالوگ کوچک‌تر و پرجزئیات‌تر است، و چهار
 * قاعده کل خانواده را می‌سازد:
 *
 *   ۱. **آزادی منفی.** پارچهٔ لباس زیر (تور، جرسی، مایکرو) کش می‌آید و اگر الگو
 *      به اندازهٔ بدن بریده شود، شورت روی تن می‌چرخد و سوتین بالا می‌رود. پس
 *      «ضریب کشسانی» این‌جا هم مثل مایو یک پارامتر اصلی است، نه یک تنظیم ریز.
 *   ۲. **هر لبهٔ باز کش می‌خورد.** زیر سینه، دور پا و کمر هیچ‌کدام درزی ندارند که
 *      نگهشان دارد؛ فقط کشِ کوتاه‌تر از لبه نگهشان می‌دارد.
 *   ۳. **نوار فاق جدا.** هر شورتی — زنانه یا مردانه — نوار فاق پنبه‌ای جدا دارد.
 *      این بهداشتی است و اختیاری نیست.
 *   ۴. **کاپ سوتین هندسهٔ خودش را دارد.** کاپ دوتکه است و درزِ میان دو تکه باید
 *      دقیقاً هم‌اندازه باشد؛ لبهٔ پایین کاپ هم باید با لبهٔ کاپِ روی سینه‌بند جور
 *      دربیاید، وگرنه کاپ روی سینه‌بند نمی‌نشیند. هر دو در این کلاس با ساخت
 *      تضمین می‌شوند، نه با حدس: درزِ کاپ در هر دو تکه یک منحنیِ جابه‌جاشده است،
 *      و لبهٔ کاپ روی سینه‌بند همان منحنیِ آینه‌شدهٔ لبهٔ پایین کاپ است.
 *
 * اندازهٔ کاپ از اختلاف دور سینه و دور زیر سینه می‌آید. اگر زیر سینه گرفته نشده
 * باشد، از دور سینه تخمین زده می‌شود و همین را هم در یادداشت قطعه می‌گوید.
 */
abstract class UnderwearBaseGenerator extends TopBaseGenerator
{
    use CutsStretchFabric;

    public static function group(): string
    {
        return 'underwear';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک هر لباس زیر.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function underwearSchema(array $extra = [], float $stretch = 0.85): array
    {
        return array_merge($this->stretchSchema($stretch), $extra);
    }

    /**
     * پارامترهای شورت زیر.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function bottomSchema(float $riseDrop = 2, float $gusset = 8, string $coverage = 'full'): array
    {
        return [
            'rise_drop' => [
                'label' => 'پایین‌تر نشستن کمر از خط کمر', 'min' => 0, 'max' => 18, 'step' => 0.5,
                'default' => $riseDrop, 'unit' => 'سانتی‌متر',
                'hint' => 'صفر یعنی کمرِ شورت روی گودی کمر می‌نشیند.',
            ],
            'side_seam' => [
                'label' => 'بلندی درز پهلو', 'min' => 1, 'max' => 16, 'step' => 0.5,
                'default' => 7, 'unit' => 'سانتی‌متر',
                'hint' => 'درز پهلوی کوتاه یعنی خط پا بالاتر می‌آید.',
            ],
            'coverage' => [
                'label' => 'پوشش پشت', 'type' => 'select', 'default' => $coverage,
                'options' => ['full' => 'کامل', 'medium' => 'معمولی', 'cheeky' => 'کم'],
            ],
            'gusset' => [
                'label' => 'پهنای نوار فاق', 'min' => 6, 'max' => 14, 'step' => 0.5,
                'default' => $gusset, 'unit' => 'سانتی‌متر',
                'hint' => 'نوار فاق پنبه‌ای است و جدا بریده می‌شود؛ اختیاری نیست.',
            ],
        ];
    }

    /**
     * پارامترهای مشترک سوتین.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function braSchema(float $bandHeight = 3.5, float $cupRatio = 0.22): array
    {
        return [
            'cup_ratio' => [
                'label' => 'پهنای پای کاپ (نسبت به دور زیر سینه)', 'min' => 0.16, 'max' => 0.3, 'step' => 0.01,
                'default' => $cupRatio,
                'hint' => 'پهن‌تر یعنی کاپ بازتر روی قفسهٔ سینه می‌نشیند و بال سینه‌بند کوتاه‌تر می‌شود.',
            ],
            'band_height' => [
                'label' => 'بلندی سینه‌بند زیر کاپ', 'min' => 2, 'max' => 8, 'step' => 0.5,
                'default' => $bandHeight, 'unit' => 'سانتی‌متر',
            ],
            'hook_rows' => [
                'label' => 'تعداد قزن روی بست پشت', 'min' => 1, 'max' => 4, 'step' => 1, 'default' => 2,
                'hint' => 'قزن‌ها روی هم چیده می‌شوند؛ سینه‌بند پهن‌تر قزن بیشتری می‌خواهد.',
            ],
            'hook_columns' => [
                'label' => 'تعداد ردیف تنظیم بست', 'min' => 1, 'max' => 3, 'step' => 1, 'default' => 3,
                'hint' => 'هر ردیف حدود دو سانتی‌متر دور سینه‌بند را تنگ‌تر می‌کند.',
            ],
            'strap_width' => [
                'label' => 'پهنای بند شانه', 'min' => 0.8, 'max' => 4, 'step' => 0.1,
                'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  اندازه‌های بدن
     * ------------------------------------------------------------------- */

    /**
     * دور زیر سینه، و اینکه گرفته شده یا تخمینی است.
     *
     * `Measurements` خودش وقتی این اندازه خالی باشد آن را از «دور سینه منهای
     * چهارده» می‌سازد. آن عدد برای یک بلوز بی‌ضرر است ولی برای سوتین نیست: اندازهٔ
     * کاپ دقیقاً از اختلاف همین دو عدد درمی‌آید. پس اگر عدد تخمینی بود، قطعه
     * خودش این را می‌گوید و کاربر می‌داند کجا باید متر بگیرد.
     *
     * @return array{value: float, estimated: bool}
     */
    protected function underBust(array $m): array
    {
        $bust = $this->m($m, 'bust', 92);
        $given = (float) ($m['under_bust'] ?? $m['underbust'] ?? 0);
        $guess = round(max(40.0, min(160.0, $bust - 14)), 1);

        if ($given <= 0) {
            return ['value' => max(30.0, round($bust - 14, 1)), 'estimated' => true];
        }

        return ['value' => $given, 'estimated' => abs($given - $guess) < 0.05];
    }

    /**
     * فاق ایستادهٔ بدن: فاصلهٔ خط کمر تا نشیمن.
     *
     * همان فرمول درفت شلوار: میانگین «باسن ÷ ۴ + ۲٫۵» و «قد بیرون پا منهای قد
     * داخل پا»، محدودشده به بازه‌ای که نسبت به کمر تا باسن معقول بماند.
     */
    protected function bodyRise(array $m): float
    {
        $hip = $this->m($m, 'hip', 98);
        $waistToHip = max(12.0, $this->m($m, 'waist_to_hip', 21));
        $inseam = $this->m($m, 'inseam', 0);
        $outseam = $this->m($m, 'outseam', 0);

        $fromHip = ($hip / 4) + 2.5;
        $rise = ($inseam > 0 && $outseam > $inseam)
            ? ((($outseam - $inseam) + $fromHip) / 2)
            : $fromHip;

        return round(min($waistToHip + 15, max($waistToHip + 5, $rise)), 2);
    }

    /* ---------------------------------------------------------------------
     |  شورت زیر
     * ------------------------------------------------------------------- */

    /**
     * شورت زیر: جلو، پشت و نوار فاق.
     *
     * «پاچهٔ شلوار» نیست و نامش هم نباید باشد: نه درز داخل پا دارد، نه پیش‌آمدگی
     * فاق، و لبهٔ پایینش دم پاچه نیست بلکه خط پاست که کش می‌خورد.
     *
     * قاعده‌ای که شکل پنل‌ها را می‌سازد: درز پهلوی جلو و پشت باید دقیقاً هم‌اندازه
     * باشد، پس بلندی بیشترِ پشت روی **مرکز پشت** گرفته می‌شود نه روی پهلو —
     * همان‌جایی که گودی نشیمن پارچه می‌خواهد.
     *
     * $o: rise_drop، side_seam، coverage، gusset، gusset_length، seat، prefix، part
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pantyPanels(array $m, array $params, array $o = []): array
    {
        $stretch = $this->stretchOf($params);

        $waist = $this->m($m, 'waist', 74) * $stretch;
        $hip = $this->m($m, 'hip', 98) * $stretch;
        $rise = $this->bodyRise($m);

        $drop = max(0.0, (float) ($o['rise_drop'] ?? 0));
        $sideSeam = max(1.0, (float) ($o['side_seam'] ?? 7));
        $gusset = max(6.0, (float) ($o['gusset'] ?? 8));
        $gussetLength = max(9.0, (float) ($o['gusset_length'] ?? ($rise * 0.5)));
        $seat = max(0.0, (float) ($o['seat'] ?? 4));
        $prefix = (string) ($o['prefix'] ?? 'panty');
        $part = (string) ($o['part'] ?? 'panty');

        // کمرِ پایین‌تر روی دور بزرگ‌تری می‌نشیند؛ همین‌طور تهِ درز پهلو
        $girthAt = fn (float $below) => $waist + (($hip - $waist) * min(1.0, $below / max(1.0, $rise)));

        $quarter = max(6.0, $girthAt($drop) / 4);
        $quarterSide = max($quarter, $girthAt($drop + $sideSeam) / 4);

        $half = $gusset / 2;
        $crotchY = max(8.0, $rise - $drop - ($gussetLength * 0.5));
        $legStartY = min($sideSeam, $crotchY - 2.5);

        $coverage = (string) ($o['coverage'] ?? 'full');
        $backCurve = match ($coverage) {
            'cheeky' => 0.34,
            'medium' => 0.58,
            default => 0.82,
        };

        $pieces = [];

        foreach ([
            ['front', 'شورت زیر — جلو', $part.'_front', 0.0, 0.6],
            ['back', 'شورت زیر — پشت', $part.'_back', $seat, $backCurve],
        ] as [$side, $name, $partName, $lift, $curve]) {
            $isFront = $side === 'front';

            // نقطهٔ کنترل میان دو گوشهٔ «تنگ» و «گشاد» حرکت می‌کند؛ نزدیک شدنش به
            // گوشهٔ پایین-بیرون یعنی پوشش کامل و نزدیک شدنش به گوشهٔ بالا-درون یعنی
            // خط پای بالا. اگر فقط یک عدد جابه‌جا شود، «پوشش کم» ممکن است پارچهٔ
            // بیشتری از «پوشش کامل» بخورد.
            $controlX = $half + (($quarterSide - $half) * $curve);
            $controlY = $legStartY + (($crotchY - $legStartY) * $curve);

            $outline = [
                Geometry::point(0, -$lift),
                $lift > 0.2
                    ? Geometry::curve($quarter, 0, $quarter * 0.45, -$lift * 0.75)
                    : Geometry::point($quarter, 0),
                Geometry::point($quarterSide, $legStartY),
                Geometry::curve($half, $crotchY, $controlX, $controlY),
                Geometry::point(0, $crotchY),
            ];

            $piece = $this->piece([
                'code' => $prefix.'-'.$side,
                'name' => $name,
                'cut_quantity' => 1,
                'on_fold' => true,
                'outline' => $outline,
                'grainline' => $this->grainline($quarter * 0.45, 1.5, $crotchY - 1.5),
                'markers' => [
                    $this->marker(
                        $isFront ? 'cf' : 'cb',
                        $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت',
                        0,
                        -$lift,
                        0,
                        $crotchY,
                    ),
                ],
                'meta' => [
                    'part' => $partName,
                    'edges' => ['waist', 'side', 'hem', 'default', 'default'],
                    'fold_edges' => [4],
                    'side' => $side,
                    'stretch' => $stretch,
                    'girth_role' => 'shell',
                    'girth' => ['waist' => round($quarter * 2, 2)],
                    'girth_factor' => 2,
                    'leg_edge' => 2,
                    'gusset_edge' => 3,
                    'side_edge' => 1,
                    'coverage' => $coverage,
                    'notes' => [
                        'کمر و خط پا هر دو کش می‌خورند؛ بدون کش هیچ درزی نیست که لبه را نگه دارد.',
                        'بلندی بیشترِ پشت روی مرکز پشت گرفته شده، نه روی پهلو؛ پس درز پهلوی جلو و پشت هم‌اندازه است.',
                    ],
                ],
            ]);

            $piece = $this->elasticOn($piece, 'waist', 'کش کمر شورت — '.($isFront ? 'جلو' : 'پشت'), $params);
            $piece = $this->elasticOn($piece, 'hem', 'کش خط پا — '.($isFront ? 'جلو' : 'پشت'), $params);

            $pieces[] = $piece;
        }

        $pieces[] = $this->gussetPiece($gusset, $gussetLength, $prefix);

        return $pieces;
    }

    /**
     * نوار فاق: دو لایه، پنبه‌ای، جدا از پوسته.
     *
     * @return array<string, mixed>
     */
    protected function gussetPiece(float $width, float $length, string $prefix = 'panty'): array
    {
        $width = max(6.0, $width);
        $length = max(9.0, $length);

        return $this->piece([
            'code' => $prefix.'-gusset',
            'name' => 'نوار فاق',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $length),
                Geometry::point(0, $length),
            ],
            'grainline' => $this->grainline($width * 0.5, 1, $length - 1),
            'meta' => [
                'part' => 'gusset',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'lining',
                'gusset_width' => round($width, 2),
                'notes' => [
                    'دو لایه بریده می‌شود: یکی از پارچهٔ رو و یکی از پنبهٔ سفید.',
                    'لایهٔ پنبه‌ای بهداشتی است و اختیاری نیست؛ بدون آن شورت روی پوست نفس نمی‌کشد.',
                    'دو سر نوار میان لایهٔ رو و لایهٔ آستر گرفته می‌شود تا هیچ درزی روی پوست نیفتد.',
                ],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  باکسر مردانه
     * ------------------------------------------------------------------- */

    /**
     * باکسر: پاچهٔ کوتاه با درز کناری، به‌علاوهٔ نوار فاق.
     *
     * فرقش با شورت زیر یکی است و مهم: پاچه دارد، پس درز داخل پا هم دارد. فرقش با
     * پاچهٔ شلوار هم یکی است و همان‌قدر مهم: پهنای پاچه روی الگو نصفِ **دور** دم
     * پاست، نه فاصلهٔ افقی دو طرف ران؛ پارچه تخت بریده می‌شود و روی پا لوله
     * می‌شود.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function boxerPanels(array $m, array $params, array $o = []): array
    {
        $stretch = $this->stretchOf($params);

        $waist = $this->m($m, 'waist', 82) * $stretch;
        $hip = $this->m($m, 'hip', 98) * $stretch;
        $thigh = $this->m($m, 'thigh', $this->m($m, 'hip', 98) * 0.58);
        $rise = $this->bodyRise($m);

        $drop = max(0.0, (float) ($o['rise_drop'] ?? 4));
        $legLength = max(4.0, (float) ($o['leg_length'] ?? 14));
        $gusset = max(6.0, (float) ($o['gusset'] ?? 9));
        $gussetLength = max(9.0, (float) ($o['gusset_length'] ?? ($rise * 0.45)));
        $seat = max(0.0, (float) ($o['seat'] ?? 3.5));
        $prefix = (string) ($o['prefix'] ?? 'boxer');

        $girthAt = fn (float $below) => $waist + (($hip - $waist) * min(1.0, $below / max(1.0, $rise)));

        $quarter = max(7.0, $girthAt($drop) / 4);
        $half = $gusset / 2;
        $crotchY = max(9.0, $rise - $drop - ($gussetLength * 0.5));
        $hemY = $crotchY + $legLength;

        // دور دم پاچه: ران به همان نسبتی که پایین می‌رویم باریک‌تر می‌شود
        $legGirth = max(20.0, $thigh * (1 - min(0.35, $legLength / 90)) * $stretch);

        $pieces = [];

        // نیمِ دور دم پاچه، یکی برای جلو و یکی برای پشت. عمداً برابرند: هم درز
        // کناری و هم درز داخل پا به هم دوخته می‌شوند، پس دو گوشهٔ دم پاچه باید
        // روی جلو و پشت دقیقاً یک‌جا باشند. فرقِ جلو و پشت در باکسر روی فاق و
        // نشیمن است، نه روی پهنای پاچه.
        $legHalf = max(6.0, $legGirth * 0.5);

        foreach ([
            ['front', 'باکسر — جلو', 'boxer_front', 0.0],
            ['back', 'باکسر — پشت', 'boxer_back', $seat],
        ] as [$side, $name, $partName, $lift]) {
            $isFront = $side === 'front';

            $outline = [
                Geometry::point(0, -$lift),
                $lift > 0.2
                    ? Geometry::curve($quarter, 0, $quarter * 0.45, -$lift * 0.75)
                    : Geometry::point($quarter, 0),
                Geometry::point($half + $legHalf, $hemY),
                Geometry::point($half, $hemY),
                // درز داخل پا کمی به بیرون کمان می‌گیرد تا پاچه روی ران نچسبد
                Geometry::curve($half, $crotchY, max(0.4, $half - 0.9), $crotchY + (($hemY - $crotchY) * 0.5)),
                // لبهٔ فاق عمداً صاف است: نوار فاق هم لبهٔ صاف دارد و این دو باید
                // میلی‌متری بر هم بنشینند، وگرنه فاق چین می‌خورد
                Geometry::point(0, $crotchY),
            ];

            $piece = $this->piece([
                'code' => $prefix.'-'.$side,
                'name' => $name,
                'cut_quantity' => 1,
                'on_fold' => true,
                'outline' => $outline,
                'grainline' => $this->grainline($quarter * 0.5, 1.5, $hemY - 1.5),
                'markers' => [
                    $this->marker(
                        $isFront ? 'cf' : 'cb',
                        $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت',
                        0,
                        -$lift,
                        0,
                        $crotchY,
                    ),
                    $this->marker('crotch', 'خط فاق', 0, $crotchY, $half),
                ],
                'meta' => [
                    'part' => $partName,
                    'edges' => ['waist', 'side', 'hem', 'side', 'default', 'default'],
                    'fold_edges' => [5],
                    'side' => $side,
                    'stretch' => $stretch,
                    'girth_role' => 'shell',
                    'girth' => ['waist' => round($quarter * 2, 2)],
                    'girth_factor' => 2,
                    'side_edge' => 1,
                    'inseam_edge' => 3,
                    'gusset_edge' => 4,
                    'leg_opening' => round($legHalf * 2, 2),
                    'notes' => [
                        'درز کناری از کمر تا دم پاچه یک‌سره است؛ باکسر بدون درز کناری روی ران می‌پیچد.',
                        'پهنای پاچه روی الگو نصفِ دور دم پاست، نه فاصلهٔ افقی دو طرف ران.',
                    ],
                ],
            ]);

            $piece = $this->elasticOn($piece, 'waist', 'کش کمر باکسر — '.($isFront ? 'جلو' : 'پشت'), $params);
            $pieces[] = $piece;
        }

        $pieces[] = $this->gussetPiece($gusset, $gussetLength, $prefix);

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  سوتین
     * ------------------------------------------------------------------- */

    /**
     * همهٔ قطعه‌های یک سوتین کاپ‌دار: دو تکهٔ کاپ، سینه‌بند جلو، بال پشت و بند شانه.
     *
     * دو «جور بودن» این‌جا با ساخت تضمین می‌شود، نه با تنظیم دستی:
     *
     *   الف) **درز کاپ**: منحنی درز در کاپ بالا و کاپ پایین یک منحنیِ جابه‌جاشده
     *        است، پس طولشان دقیقاً برابر است. جهت کمانش عمداً یکی است: در کاپ
     *        پایین بیرون‌زده و در کاپ بالا فرورفته، و همین اختلاف است که کاپ را
     *        از تخت بودن درمی‌آورد.
     *   ب) **لبهٔ کاپ روی سینه‌بند**: لبهٔ بالای سینه‌بند همان منحنیِ آینه‌شدهٔ لبهٔ
     *        پایین کاپ است، و اگر کاپ ساسون داشته باشد (پوش‌آپ) به همان اندازه
     *        کوچک می‌شود. آینه‌کردن و بزرگ‌نمایی یکنواخت هر دو طول را دقیق نگه
     *        می‌دارند.
     *
     * $o: prefix، cup_ratio، band_height، cup_dart، padded، underwire، hook_rows،
     *     hook_columns، strap_width، depth_scale
     *
     * @return array<int, array<string, mixed>>
     */
    protected function braPieces(array $m, array $params, array $o = []): array
    {
        $stretch = $this->stretchOf($params);
        $prefix = (string) ($o['prefix'] ?? 'bra');

        $under = $this->underBust($m);
        $bust = $this->m($m, 'bust', 92);
        $band = max(26.0, $under['value'] * $stretch);

        $depth = max(2.5, ($bust - $under['value']) * (float) ($o['depth_scale'] ?? 1.0));
        $base = max(8.0, $under['value'] * min(0.3, max(0.16, (float) ($o['cup_ratio'] ?? 0.22))));
        // بال سینه‌بند دست‌کم دوازده سانتی‌متر بماند، وگرنه بست پشت جایی ندارد
        $base = min($base, max(5.0, ($band - 12.0) / 2));

        $lowerH = ($depth * 0.72) + ($base * 0.16);
        $upperH = ($depth * 0.5) + ($base * 0.1);
        $bulge = min($base * 0.18, $lowerH * 0.45);

        $rows = (int) max(1, min(4, (float) ($o['hook_rows'] ?? 2)));
        $columns = (int) max(1, min(3, (float) ($o['hook_columns'] ?? 3)));
        $bandHeight = max((float) ($o['band_height'] ?? 3.5), ($rows * 1.3) + 0.6);

        $lower = $this->lowerCupPiece($base, $lowerH, $bulge, $prefix, $params, $o);
        $upper = $this->upperCupPiece($base, $upperH, $bulge, $prefix, $params, $o);

        $seat = (float) $lower['meta']['cup_seat_length'];
        $cradle = $this->cradlePiece($base, $lowerH, $seat, $bandHeight, $prefix, $params, $o);

        $wing = $this->wingPiece(
            max(5.0, ($band / 2) - (float) $cradle['meta']['cradle_width']),
            $bandHeight,
            $rows,
            $columns,
            $prefix,
            $params,
        );

        $strap = $this->strapSet($m, $params, $lowerH + $upperH, $prefix, $o);

        $pieces = [$upper, $lower, $cradle, $wing, $strap];

        if (! empty($o['padded'])) {
            $pieces[] = $this->cupPadPiece($lower, $prefix);
        }

        $pieces[2]['meta']['underwire'] = (bool) ($o['underwire'] ?? false);

        if (! empty($o['underwire'])) {
            $pieces[2]['meta']['notions'][] = [
                'type' => 'underwire',
                'label' => 'فنر کاپ به بلندی '.$this->fa(round($seat)).' سانتی‌متر',
                'count' => 2,
            ];
            $pieces[2]['meta']['notes'][] = 'فنر داخل نوارِ فنر روی لبهٔ کاپ دوخته می‌شود؛ سرِ فنر باید کلاهک داشته باشد.';
        }

        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['band_girth'] = round($band, 2);
            $pieces[$index]['meta']['cup_depth'] = round($depth, 2);
            $pieces[$index]['meta']['under_bust'] = round($under['value'], 1);
        }

        if ($under['estimated']) {
            $pieces[2]['meta']['notes'][] = 'دور زیر سینه گرفته نشده و از دور سینه تخمین زده شده'
                .' (دور سینه منهای ۱۴ = '.$this->fa(round($under['value'], 1)).' سانتی‌متر).'
                .' اندازهٔ کاپ تماماً از همین اختلاف می‌آید، پس پیش از برش این یک عدد را با متر بگیرید.';
            $pieces[2]['meta']['under_bust_estimated'] = true;
        }

        return $pieces;
    }

    /**
     * کاپ پایین: درز کاپ در بالا، لبهٔ نشستن روی سینه‌بند در پایین.
     *
     * @return array<string, mixed>
     */
    protected function lowerCupPiece(float $base, float $height, float $bulge, string $prefix, array $params, array $o = []): array
    {
        $outline = [
            // نقطهٔ کنترل به نقطه‌ای می‌چسبد که *به آن* می‌رسیم؛ پس کنترلِ لبهٔ
            // بسته‌شونده روی نقطهٔ نخست می‌نشیند.
            Geometry::curve(0, 0, $base * 0.16, $height * 0.72),
            Geometry::curve($base, 0, $base * 0.5, -$bulge),
            Geometry::curve($base * 0.45, $height, $base * 0.96, $height * 0.62),
        ];

        $seatLength = Geometry::edgeLength($outline, 1) + Geometry::edgeLength($outline, 2);
        $seamLength = Geometry::edgeLength($outline, 0);

        $darts = [];
        $intake = 0.0;
        $arc = 0.0;
        $wanted = max(0.0, (float) ($o['cup_dart'] ?? 0));

        if ($wanted > 0.3) {
            [$darts, $intake, $arc] = $this->cupDart($outline, 2, $wanted);
        }

        $piece = $this->piece([
            'code' => $prefix.'-cup-lower',
            'name' => 'کاپ پایین',
            'cut_quantity' => (int) ($o['cup_cut'] ?? 4),
            'mirror' => true,
            'outline' => $outline,
            'darts' => $darts,
            'grainline' => $this->grainline($base * 0.5, 0.6, $height - 0.6),
            'meta' => [
                'part' => 'bra_cup_lower',
                'edges' => ['default', 'default', 'default'],
                'fold_edges' => [],
                'girth_role' => 'shell',
                'cup_seam_edge' => 0,
                'cup_seam_length' => round($seamLength, 2),
                'cup_seat_edges' => [1, 2],
                'cup_seat_length' => round($seatLength - $arc, 2),
                'cup_seat_raw' => round($seatLength, 2),
                'cup_dart_intake' => round($intake, 2),
                'notes' => array_filter([
                    'چهار تکه بریده می‌شود: دو رو و دو آستر؛ آستر از تور بی‌کشش تا کاپ فرم بگیرد.',
                    'لبهٔ بالا درزِ کاپ است و روی کاپ بالا دوخته می‌شود؛ طول این درز در هر دو تکه یکی است.',
                    $intake > 0.3
                        ? 'ساسون پایین کاپ '.$this->fa(round($intake, 1)).' سانتی‌متر است؛'
                            .' همین ساسون است که کاپ را بالا می‌آورد و لبهٔ پایین کاپ را به اندازهٔ خودش کوتاه می‌کند.'
                        : null,
                ]),
            ],
        ]);

        return $piece;
    }

    /**
     * کاپ بالا: همان درز در پایین، لبهٔ رویی (یقه) در بالا.
     *
     * @return array<string, mixed>
     */
    protected function upperCupPiece(float $base, float $height, float $bulge, string $prefix, array $params, array $o = []): array
    {
        $outline = [
            Geometry::curve(0, $height, $base * 0.2, $height * 0.4),
            // همان منحنیِ درزِ کاپ پایین، فقط جابه‌جاشده: کمانش این‌جا به داخل
            // قطعه می‌افتد و آن‌جا به بیرون؛ طولشان دقیقاً یکی است.
            Geometry::curve($base, $height, $base * 0.5, $height - $bulge),
            Geometry::curve($base * 0.6, 0, $base * 1.02, $height * 0.34),
        ];

        $piece = $this->piece([
            'code' => $prefix.'-cup-upper',
            'name' => 'کاپ بالا',
            'cut_quantity' => (int) ($o['cup_cut'] ?? 4),
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($base * 0.5, $height * 0.25, $height - 0.6),
            'notches' => [
                $this->notch($base * 0.6, 0, 1, 'جای بند شانه', 'strap'),
            ],
            'meta' => [
                'part' => 'bra_cup_upper',
                'edges' => ['default', 'neck', 'neck'],
                'fold_edges' => [],
                'girth_role' => 'shell',
                'cup_seam_edge' => 0,
                'cup_seam_length' => round(Geometry::edgeLength($outline, 0), 2),
                'notes' => [
                    'چهار تکه بریده می‌شود: دو رو و دو آستر.',
                    'لبهٔ پایین درزِ کاپ است؛ روی کاپ پایین دوخته می‌شود و طولش با آن یکی است.',
                    'بند شانه روی گوشهٔ بیرونی همین تکه می‌نشیند، نه وسط لبهٔ بالا.',
                ],
            ],
        ]);

        return $this->elasticOn($piece, 'neck', 'کش (یا نوار پیکو) لبهٔ رویی کاپ', $params);
    }

    /**
     * ساسون پایین کاپ: دو پا دقیقاً روی همان لبه‌ای که ادعا می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float}
     */
    protected function cupDart(array $outline, int $edge, float $wanted): array
    {
        $length = Geometry::edgeLength($outline, $edge);

        if ($length < $wanted + 2.0) {
            return [[], 0.0, 0.0];
        }

        $step = $wanted / $length;
        $from = max(0.05, 0.42 - ($step / 2));
        $to = min(0.95, $from + $step);

        $legA = Geometry::pointOnEdge($outline, $edge, $from);
        $legB = Geometry::pointOnEdge($outline, $edge, $to);

        // طول کمانی که با بستن ساسون از لبه کم می‌شود (نه فاصلهٔ مستقیم دو پا)
        $arc = 0.0;
        $previous = $legA;

        for ($i = 1; $i <= 16; $i++) {
            $point = Geometry::pointOnEdge($outline, $edge, $from + ((($to - $from) * $i) / 16));
            $arc += Geometry::distance($previous, $point);
            $previous = $point;
        }

        $intake = Geometry::distance($legA, $legB);
        $middle = Geometry::lerp($legA, $legB, 0.5);
        $centroid = Geometry::centroid($outline);
        $apex = Geometry::lerp($middle, $centroid, 0.8);

        $dart = $this->dart(
            'cup',
            'ساسون پایین کاپ',
            $edge,
            $middle['x'],
            $middle['y'],
            $intake,
            $apex['x'],
            $apex['y'],
            'x',
            ['legs' => [Geometry::point($legA['x'], $legA['y']), Geometry::point($legB['x'], $legB['y'])]],
        );

        return [[$dart], $intake, $arc];
    }

    /**
     * سینه‌بند جلو (قاب کاپ): لبهٔ بالایش همان لبهٔ پایین کاپ است.
     *
     * @return array<string, mixed>
     */
    protected function cradlePiece(
        float $base,
        float $height,
        float $seatTarget,
        float $bandHeight,
        string $prefix,
        array $params,
        array $o = [],
    ): array {
        // لبهٔ پایین کاپ آینه می‌شود (آینه‌کردن طول را عوض نمی‌کند) و بعد یکنواخت
        // بزرگ/کوچک می‌شود تا دقیقاً به طول خواسته‌شده برسد؛ بزرگ‌نمایی یکنواخت
        // طول را به همان نسبت عوض می‌کند، پس یک بار حساب کافی است.
        $mirrored = [
            Geometry::point(0, $height),
            Geometry::curve($base * 0.45, 0, $base * 0.16, $height * 0.28),
            Geometry::curve($base, $height, $base * 0.96, $height * 0.38),
        ];

        $raw = Geometry::edgeLength($mirrored, 0) + Geometry::edgeLength($mirrored, 1);

        $scale = $raw > 0.1 ? max(0.4, min(1.6, $seatTarget / $raw)) : 1.0;
        $width = $base * $scale;
        $arch = $height * $scale;

        $outline = [
            Geometry::point(0, $arch),
            Geometry::curve($width * 0.45, 0, $width * 0.16, $arch * 0.28),
            Geometry::curve($width, $arch, $width * 0.96, $arch * 0.38),
            Geometry::point($width, $arch + $bandHeight),
            Geometry::point(0, $arch + $bandHeight),
        ];

        $piece = $this->piece([
            'code' => $prefix.'-cradle',
            'name' => 'سینه‌بند جلو (قاب کاپ)',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, $arch * 0.6, $arch + $bandHeight - 0.6),
            'notches' => [
                $this->notch($width * 0.45, 0, 0, 'بالای قوس کاپ', 'cup_apex'),
            ],
            'markers' => [
                $this->marker('cf', 'خط مرکز جلو', 0, $arch, 0, $arch + $bandHeight),
            ],
            'meta' => [
                'part' => 'bra_cradle',
                'edges' => ['default', 'default', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'girth_role' => 'shell',
                'cup_seat_edges' => [0, 1],
                'cup_seat_length' => round(Geometry::edgeLength($outline, 0) + Geometry::edgeLength($outline, 1), 2),
                'side_edge' => 2,
                'cradle_width' => round($width, 2),
                'band_height' => round($bandHeight, 2),
                'stretch' => $this->stretchRatio,
                'notes' => [
                    'لبهٔ بالا (قوس) همان لبهٔ پایین کاپ است؛ کاپ روی همین می‌نشیند و طولشان یکی است.',
                    'دو تکه بریده می‌شود، یکی برای هر سینه، و در مرکز جلو به هم می‌رسند.',
                    'از پارچهٔ کم‌کشش (پاورنت) ببرید؛ اگر قاب کاپ کش بیاید، کاپ زیر سینه می‌افتد.',
                ],
            ],
        ]);

        return $this->elasticOn($piece, 'hem', 'کش زیر سینه — قاب جلو', $params, 0.85);
    }

    /**
     * بال پشت سینه‌بند، با بست قزنی.
     *
     * @return array<string, mixed>
     */
    protected function wingPiece(
        float $length,
        float $bandHeight,
        int $rows,
        int $columns,
        string $prefix,
        array $params,
    ): array {
        $hook = min($bandHeight, max(1.6, ($rows * 1.3) + 0.4));

        $outline = [
            Geometry::point(0, 0),
            Geometry::curve($length, $bandHeight - $hook, $length * 0.45, -0.8),
            Geometry::point($length, $bandHeight),
            Geometry::point(0, $bandHeight),
        ];

        $piece = $this->piece([
            'code' => $prefix.'-wing',
            'name' => 'بال پشت سینه‌بند',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 0.6, $bandHeight - 0.4),
            'notches' => [
                $this->notch(0, $bandHeight * 0.5, 3, 'درز پهلو — به قاب کاپ می‌رسد', 'bra_side'),
            ],
            'meta' => [
                'part' => 'bra_wing',
                'edges' => ['default', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'shell',
                'wing_length' => round($length, 2),
                'side_edge' => 3,
                'hook_edge' => 1,
                'hook_rows' => $rows,
                'hook_columns' => $columns,
                'stretch' => $this->stretchRatio,
                'notions' => [
                    [
                        'type' => 'hook',
                        'label' => 'بست قزنی '.$this->fa($rows).' قزن در '.$this->fa($columns).' ردیف تنظیم',
                        'count' => $rows * $columns,
                    ],
                    [
                        'type' => 'ring',
                        'label' => 'حلقه و سگک تنظیم بند شانه',
                        'count' => 4,
                    ],
                ],
                'notes' => [
                    'بال از پهلو به قاب کاپ دوخته می‌شود و بلندی درز پهلوی هر دو یکی است.',
                    'بست پشت '.$this->fa($columns).' ردیف تنظیم دارد؛ هر ردیف حدود دو سانتی‌متر سینه‌بند را تنگ‌تر می‌کند.',
                    'راستای پارچه در بال باید در راستای دور بدن کشسان باشد، وگرنه سینه‌بند بالا می‌رود.',
                ],
            ],
        ]);

        return $this->elasticOn($piece, 'hem', 'کش زیر سینه — بال پشت', $params, 0.85);
    }

    /**
     * بند شانه، با بلندی حساب‌شده از خودِ بدن.
     *
     * بند باید واقعاً از پشت، روی شانه، به جلو برسد؛ پس بلندی‌اش از دو تکه ساخته
     * می‌شود: از سرشانه تا بالای کاپ در جلو، و از سرشانه تا خط زیر سینه در پشت.
     * چند سانتی‌متر هم برای حلقه و سگک تنظیم اضافه می‌شود، چون بند چیزی است که در
     * پرو کوتاه می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function strapSet(array $m, array $params, float $cupHeight, string $prefix, array $o = []): array
    {
        $g = $this->blockMetrics($m, [], $params);

        $bandY = (float) ($g['under_bust_y'] ?? 30);
        $shoulderY = (float) ($g['shoulder_drop'] ?? 4.5);
        $cupTopY = max($shoulderY + 3.0, $bandY - $cupHeight);

        $front = round(max(5.0, $cupTopY - $shoulderY), 1);
        $back = round(max(8.0, $bandY - $shoulderY), 1);
        $path = round($front + $back, 1);

        $piece = $this->strapPiece($path + 12.0, (float) ($o['strap_width'] ?? 1.5), [
            'code' => $prefix.'-strap',
            'name' => 'بند شانه',
            'cut' => 2,
            'meta' => [
                'strap_front' => $front,
                'strap_back' => $back,
                'strap_path' => $path,
                'adjustable' => true,
            ],
        ]);

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'مسیر بند روی بدن '.$this->fa($path).' سانتی‌متر است ('.$this->fa($front)
                .' جلو + '.$this->fa($back).' پشت)؛ بند دوازده سانتی‌متر بلندتر بریده شده'
                .' تا حلقه و سگک تنظیم جا بگیرند و در پرو کوتاه شود.',
            'یک سرش به گوشهٔ بیرونی کاپ بالا و سرِ دیگرش به بال پشت می‌رسد.',
        ]);

        return $piece;
    }

    /**
     * لایهٔ پوش‌آپ: همان کاپ پایین، کمی کوچک‌تر، از اسفنج.
     *
     * @param  array<string, mixed>  $lower
     * @return array<string, mixed>
     */
    protected function cupPadPiece(array $lower, string $prefix): array
    {
        $outline = Geometry::scale($lower['outline'], 0.92);

        return $this->piece([
            'code' => $prefix.'-cup-pad',
            'name' => 'لایهٔ پوش‌آپ',
            'cut_quantity' => 2,
            'mirror' => true,
            'layer' => 'lining',
            'outline' => $outline,
            'grainline' => $this->grainline(
                Geometry::width($outline) * 0.5,
                Geometry::height($outline) * 0.2,
                Geometry::height($outline) * 0.85,
            ),
            'meta' => [
                'part' => 'cup_pad',
                'edges' => $lower['meta']['edges'],
                'fold_edges' => [],
                'girth_role' => 'lining',
                'notes' => [
                    'از اسفنج سه‌لایه بریده می‌شود و میان کاپ رو و آستر می‌ماند.',
                    'هشت درصد کوچک‌تر از خود کاپ است تا لبه‌اش زیر درز پنهان شود و از رو دیده نشود.',
                    'ضخامت لایه در پایین کاپ بیشتر از بالاست؛ همین است که سینه را بالا می‌آورد نه بزرگ‌تر.',
                ],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  بستن کار
     * ------------------------------------------------------------------- */

    /**
     * شماره‌گذاری نهایی، به‌همراه مهرِ ضریب کشسانی روی قطعه‌های پوسته.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finish(array $pieces): array
    {
        return parent::finish($this->stampStretch(array_values(array_filter($pieces))));
    }

    /**
     * یادداشت‌های همیشگی لباس زیر.
     *
     * @return array<int, string>
     */
    protected function underwearNotes(array $params, array $extra = []): array
    {
        $notes = [];

        if ($this->negativeEase) {
            $stretch = $this->stretchOf($params);
            $notes[] = 'الگو '.$this->fa(round((1 - $stretch) * 100)).' درصد کوچک‌تر از دور بدن بریده شده؛'
                .' لباس زیر با کش آمدن روی تن می‌نشیند و همین تنگی است که نمی‌گذارد جابه‌جا شود.';
        } else {
            $notes[] = 'این مدل از پارچهٔ بافته بریده می‌شود و آزادی مثبت دارد؛ کوچک‌تر از بدن نیست.';
        }

        $notes[] = 'همهٔ لبه‌های باز کش می‌خورند؛ کش همیشه کوتاه‌تر از لبه بریده می‌شود.';
        $notes[] = 'با نخ کشی (استرچ) و سوزن جرسی بدوزید؛ درز معمولی زیر کشیدن پارچه پاره می‌شود.';

        return array_merge($notes, $extra);
    }

    /**
     * یادداشت‌ها را روی قطعه‌ها می‌نشاند و کار را می‌بندد.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function finishUnderwear(array $pieces, array $notes): array
    {
        return $this->finish($this->noted(
            $pieces,
            array_map(fn (string $text) => ['type' => 'info', 'text' => $text], $notes),
        ));
    }
}
