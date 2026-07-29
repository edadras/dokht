<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه مشترک کت‌وشلوار رسمی.
 *
 * جدی‌ترین درفت کاتالوگ، و جدی بودنش از چهار جا می‌آید:
 *
 *   ۱. برش پنلی. تنه با درزی که از حلقهٔ آستین می‌آید، از کنار نوک سینه رد
 *      می‌شود و روی کمر و باسن پایین می‌رود به چهار پنل تقسیم می‌شود (میانی و
 *      پهلو، در جلو و در پشت). این درز جای ساسون سینه و ساسون کمر را با هم
 *      می‌گیرد؛ برای همین کتِ خوب هیچ ساسونی روی رویه ندارد. دو لبهٔ هر درز از
 *      یک خط ساخته و بعد «راه برده» می‌شوند، پس هم‌اندازه‌اند.
 *   ۲. آستین دوتکه. آستین رو و آستین زیر، با شکستِ آرنج. سهم هرکدام از سرآستین
 *      این‌جا حساب می‌شود نه حدس زده: جمع دو لبهٔ حلقهٔ آستین دقیقاً به «حلقهٔ
 *      اندازه‌گرفته‌شده + آزادی سرآستین» می‌رسد، و آزادی سرآستین پارامتر است.
 *   ۳. یقه در لایهٔ سبک می‌نشیند، پس تنه باید «یقه‌پذیر» بماند: لبهٔ neck پنل
 *      میانی جلو و پشت سالم و اندازه‌گرفتنی است و طولش در meta.neck_length ثبت
 *      می‌شود. یقهٔ زیر و سجافِ برگردان‌دار هم این‌جا ساخته می‌شوند تا کت بدون
 *      هیچ سبکی هم کامل باشد.
 *   ۴. جیب فیلتاب، سجاف جلو، سجاف یقهٔ پشت و آستر — چیزهایی که کت را از «بالاتنهٔ
 *      بلند» جدا می‌کنند.
 *
 * یک صراحت لازم: برگردان یقه (لپل) این‌جا با تنه یک‌تکه بریده نمی‌شود؛ شکل
 * برگردان روی سجاف درفت می‌شود و روی تنه فقط خط برگردان علامت می‌خورد. برای
 * دوختِ واقعی درست است (سجاف همان لایه‌ای است که دیده می‌شود) ولی یک ساده‌سازی
 * است و در یادداشت قطعه هم گفته شده.
 */
abstract class SuitBaseGenerator extends BodiceGarmentBase
{
    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'suit';
    }

    /* ---------------------------------------------------------------------
     |  پارامترها
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک کت رسمی.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function suitSchema(array $defaults = [], array $extra = []): array
    {
        return array_merge(
            $this->outerSchema(array_merge([
                'shoulder_slope' => 4,
                'neck_width_extra' => 1.5,
                'front_neck_depth_extra' => 1,
                'back_neck_depth' => 2.5,
                'armhole_depth_extra' => 3,
                'waist_dart_share' => 0.6,
            ], $defaults)),
            $this->fitParam('fitted'),
            $this->garmentLengthParam(24, 8, 50, 'بلندی از خط کمر'),
            [
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 10, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه جلو', 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 2,
                ],
                'lapel_width' => [
                    'label' => 'پهنای برگردان یقه', 'min' => 5, 'max' => 16, 'step' => 0.5,
                    'default' => 8.5, 'unit' => 'سانتی‌متر',
                ],
                'lapel_break' => [
                    'label' => 'محل شکست یقه از خط کمر', 'min' => -12, 'max' => 16, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                    'hint' => 'مثبت یعنی پایین‌تر از کمر؛ کتِ دو دکمه شکست یقه‌اش نزدیک خط کمر است.',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه زیر', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 7.5, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین', 'min' => 30, 'max' => 78, 'step' => 1,
                    'default' => 60, 'unit' => 'سانتی‌متر',
                ],
                'cap_ease' => [
                    'label' => 'آزادی سرآستین', 'min' => 1, 'max' => 6, 'step' => 0.25,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'کت شانه‌ی پُر می‌خواهد؛ همین آزادی است که سرِ آستین را گرد نگه می‌دارد.',
                ],
                'sleeve_buttons' => [
                    'label' => 'تعداد دکمه سر آستین', 'min' => 0, 'max' => 5, 'step' => 1, 'default' => 3,
                ],
                'back_vent' => [
                    'label' => 'بلندی چاک پشت', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 20, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بدون چاک.',
                ],
                'lining' => [
                    'label' => 'قطعه‌های آستر ساخته شود', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $extra,
        );
    }

    /* ---------------------------------------------------------------------
     |  تنه
     * ------------------------------------------------------------------- */

    /**
     * چهار پنل تنه: میانی و پهلوی جلو، میانی و پهلوی پشت.
     *
     * پنل میانی جلو «اضافه جای دکمه» می‌گیرد و پنل میانی پشت درز مرکزی دارد —
     * بدون درز مرکز پشت نه چاک پشت ممکن است و نه قالب‌کردن گودی کمر.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function suitShell(array $g, array $params, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? 'suit-');
        $length = (float) ($o['length'] ?? $this->param($params, 'length', 24));
        $grow = (float) ($o['grow'] ?? $this->fitGrow($params, ['fitted' => 1.5, 'regular' => 3.0, 'loose' => 5.0]));
        $stand = (float) ($o['stand'] ?? $this->param($params, 'button_stand', 2));

        $shared = [
            'shape' => 'fitted',
            'length' => $length,
            'grow' => $grow,
            'origin' => 'armhole',
            'bottom_tag' => 'hem',
            'hem_flare' => (float) ($o['hem_flare'] ?? 1.5),
            'layer' => 'outer',
            'prefix' => $prefix,
        ];

        $front = $this->princessPanels($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => false,
            'center_cut' => 2,
            'bust_dart' => true,
            'center_name' => 'تنه جلو',
            'side_name' => 'پنل پهلوی جلو',
            'seam_key' => 'suit_front_panel',
        ]));

        $back = $this->princessPanels($g, array_merge($shared, [
            'side' => 'back',
            'on_fold' => false,
            'center_cut' => 2,
            'center_name' => 'تنه پشت',
            'side_name' => 'پنل پهلوی پشت',
            'seam_key' => 'suit_back_panel',
        ]));

        return array_merge($front, $back);
    }

    /**
     * طول حلقهٔ آستین از روی همهٔ پنل‌هایی که تکه‌ای از آن را با خود دارند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function armholeTotal(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if (($piece['layer'] ?? 'outer') !== 'outer') {
                continue;
            }

            foreach (Geometry::edgesWithTag($piece, 'armhole') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /**
     * طول نیمِ خط یقه (جلو + پشت) از روی پنل‌های میانی.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function neckTotal(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if (($piece['layer'] ?? 'outer') !== 'outer' || ($piece['meta']['panel'] ?? '') !== 'center') {
                continue;
            }

            foreach (Geometry::edgesWithTag($piece, 'neck') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  آستین دوتکه
     * ------------------------------------------------------------------- */

    /**
     * آستین دوتکهٔ خیاطی که سرآستینش دقیقاً به حلقه می‌خورد.
     *
     * آستین دوتکه دو دام دارد و هر دو این‌جا بسته شده‌اند:
     *
     *   ۱. سرآستین از دو تکه ساخته می‌شود و اگر هرکدام جداگانه درفت شود، جمعشان
     *      هیچ‌وقت به حلقه نمی‌رسد. پس اول «طول هدف» حساب می‌شود (حلقه + آزادی
     *      سرآستین)، سهم آستین رو با تنظیم پهنا و ارتفاع کپ به دست می‌آید، و سهم
     *      آستین زیر دقیقاً «هدف منهای آنچه آستین رو ساخت» است.
     *   ۲. درز جلو و درز پشتِ دو تکه به هم دوخته می‌شوند و باید هم‌اندازه باشند.
     *      اگر آستین زیر با ارتفاع کپِ خودش درفت شود، درزهایش به اندازهٔ اختلاف
     *      دو کپ بلندتر درمی‌آید. برای همین آستین زیر از خطِ زیر بغلِ *آستین رو*
     *      شروع می‌شود و کمان زیر بغلش به داخل قطعه گود می‌شود؛ بعد طول هر دو درز
     *      عیناً روی طول درز آستین رو تنظیم می‌شود.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function suitSleeve(array $m, array $ease, array $params, float $armhole, array $o = []): array
    {
        $bicep = $this->m($m, 'bicep', 28.5) + $this->ease($ease, 'bicep', 8);
        $wrist = $this->m($m, 'wrist', 16.5) + (float) ($o['wrist_ease'] ?? 9);
        $length = max(24.0, (float) ($o['length'] ?? $this->param($params, 'sleeve_length', 60)));
        $capEase = (float) $this->param($params, 'cap_ease', 3);
        $buttons = (int) ($o['buttons'] ?? $this->param($params, 'sleeve_buttons', 3));

        $target = max(20.0, $armhole + $capEase);
        $share = 0.72;

        // آستین زیر: پهنایش هرگز از سهمش از سرآستین بیشتر نشود، وگرنه کمانی که
        // باید آن سهم را بسازد کوتاه‌تر از وترِ خودش می‌شود و اصلاً وجود ندارد
        $underWidth = min($bicep * 0.34, $target * (1 - $share) * 0.86);
        $upperWidth = max(8.0, $bicep - $underWidth);

        [$upperWidth, $upperCap] = $this->fitCap($upperWidth, $target * $share);

        $upperHem = max(5.0, $wrist * 0.62);
        $underHem = max(3.0, $wrist * 0.38);
        $length = max($length, $upperCap + 12.0);
        $elbow = $upperCap + (($length - $upperCap) * 0.55);

        // ── آستین رو ──
        $upperOutline = $this->capOutline($upperWidth, $upperCap);
        $upperOutline[] = Geometry::curve($upperWidth - 1.5, $length, $upperWidth + 1.4, $elbow);
        $upperOutline[] = Geometry::point($upperWidth - 1.5 - $upperHem, $length);
        // لبهٔ بسته‌شدن (درز جلوی آستین) منحنی است، پس نقطهٔ اول نقطهٔ کنترل می‌گیرد
        $upperOutline[0] = Geometry::curve(0, $upperCap, ($upperWidth - $upperHem) * 0.24, $elbow);

        $upperEdges = ['armhole', 'armhole', 'armhole', 'armhole', 'side', 'hem', 'side'];

        $upper = $this->piece([
            'code' => ($o['prefix'] ?? 'suit-').'upper-sleeve',
            'name' => 'آستین رو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $upperOutline,
            'grainline' => $this->grainline($upperWidth * 0.5, $upperCap * 0.4, $length - 4),
            'notches' => [
                $this->notchAt($upperOutline, $upperEdges, $upperWidth * 0.5, 0, 'نوک آستین (سرشانه)', 'sleeve_top', 'armhole'),
                $this->notchAt($upperOutline, $upperEdges, 0, $upperCap, 'نشانه آستین زیر — درز جلو', 'under_sleeve_front', 'side'),
                $this->notchAt($upperOutline, $upperEdges, $upperWidth, $upperCap, 'نشانه آستین زیر — درز پشت', 'under_sleeve_back', 'side'),
            ],
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $upperCap, $upperWidth),
                $this->marker('elbow', 'خط آرنج', 0, $elbow, $upperWidth - 0.5),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => $upperEdges,
                'fold_edges' => [],
                'girth_role' => 'sleeve',
                'two_piece' => 'upper',
                'cap_height' => round($upperCap, 2),
                'cap_length' => round(Geometry::edgesLength($upperOutline, [0, 1, 2, 3]), 2),
                'target_armhole' => round($armhole, 2),
                'cap_ease' => round($capEase, 2),
                'sleeve_length' => round($length, 2),
                'notes' => ['درز جلوی آستین «خط پرس» است و باید صاف اتو شود؛ شکستِ آستین روی خط آرنج می‌افتد.'],
            ],
        ]);

        if ($buttons > 0) {
            $upper = $this->markSleeveVent($upper, $upperWidth, $length, $buttons);
        }

        // ── آستین زیر ──
        // مبدأ آستین زیر روی خط زیر بغلِ آستین روست (y = 0)، نه روی کپِ خودش؛ پس
        // فاصلهٔ عمودی سرِ درز تا دم آستین در هر دو تکه یکی است.
        $upperCapLength = Geometry::edgesLength($upperOutline, [0, 1, 2, 3]);
        $underTarget = max($underWidth * 1.03, $target - $upperCapLength);
        $drop = $length - $upperCap;
        $frontSeam = Geometry::edgeLength($upperOutline, 6);
        $backSeam = Geometry::edgeLength($upperOutline, 4);
        $underHemX = $underWidth - 1 - $underHem;

        $underOutline = [
            // نقطهٔ صفر؛ نقطهٔ کنترلش مال لبهٔ بسته‌شدن (درز جلو) است و پایین‌تر گذاشته می‌شود
            Geometry::point(0, 0),
            // کمان زیر بغل: به داخل قطعه گود می‌شود، چون آستین زیر کپ ندارد
            $this->curveOfLength(['x' => 0.0, 'y' => 0.0], ['x' => $underWidth, 'y' => 0.0], $underTarget, -1.0),
            $this->curveOfLength(['x' => $underWidth, 'y' => 0.0], ['x' => $underWidth - 1, 'y' => $drop], $backSeam, 1.0),
            Geometry::point($underHemX, $drop),
        ];

        $underOutline[0] = $this->curveOfLength(
            ['x' => $underHemX, 'y' => $drop],
            ['x' => 0.0, 'y' => 0.0],
            $frontSeam,
            1.0,
        );

        $underEdges = ['armhole', 'side', 'hem', 'side'];

        $under = $this->piece([
            'code' => ($o['prefix'] ?? 'suit-').'under-sleeve',
            'name' => 'آستین زیر',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $underOutline,
            'grainline' => $this->grainline(max(1.0, $underWidth * 0.5), 2.0, $drop - 2),
            'notches' => [
                $this->notchAt($underOutline, $underEdges, 0, 0, 'نشانه آستین رو — درز جلو', 'under_sleeve_front', 'side'),
                $this->notchAt($underOutline, $underEdges, $underWidth, 0, 'نشانه آستین رو — درز پشت', 'under_sleeve_back', 'side'),
            ],
            'markers' => [
                $this->marker('elbow', 'خط آرنج', min(0.0, $underHemX), $drop * 0.55, $underWidth - 0.5),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => $underEdges,
                'fold_edges' => [],
                'girth_role' => 'sleeve',
                'two_piece' => 'under',
                'cap_height' => round($underTarget - $underWidth, 2),
                'cap_length' => round(Geometry::edgeLength($underOutline, 0), 2),
                'sleeve_length' => round($drop, 2),
                'notes' => [
                    'آستین زیر کپ ندارد: کمان زیر بغلش به داخل قطعه گود می‌شود و همان گودی، سهمش از حلقه را می‌سازد.',
                    'دو درزش عیناً هم‌اندازهٔ درز جلو و درز پشتِ آستین روست، پس بدون کشیدن روی هم می‌نشینند.',
                ],
            ],
        ]);

        return [$upper, $under];
    }

    /**
     * منحنی‌ای که طول کمانش دقیقاً به اندازهٔ خواسته‌شده است.
     *
     * نقطهٔ کنترل روی عمودمنصفِ وتر جابه‌جا می‌شود و با نصف‌کردن پی‌درپی، طول به
     * هدف می‌رسد — همان کاری که خیاط با پیستوله و متر روی کاغذ می‌کند.
     *
     * @param  array{x: float, y: float}  $from
     * @param  array{x: float, y: float}  $to
     * @return array<string, mixed> نقطهٔ پایانی، با اطلاعات منحنی
     */
    protected function curveOfLength(array $from, array $to, float $target, float $sign = 1.0): array
    {
        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $chord = sqrt(($dx * $dx) + ($dy * $dy));

        if ($chord < 1e-6 || $target <= $chord + 0.01) {
            return Geometry::point($to['x'], $to['y']);
        }

        $nx = ($dy / $chord) * $sign;
        $ny = (-$dx / $chord) * $sign;
        $midX = ($from['x'] + $to['x']) / 2;
        $midY = ($from['y'] + $to['y']) / 2;

        $lengthAt = function (float $bulge) use ($from, $to, $midX, $midY, $nx, $ny): float {
            return Geometry::edgeLength([
                Geometry::point($from['x'], $from['y']),
                Geometry::curve($to['x'], $to['y'], $midX + (2 * $bulge * $nx), $midY + (2 * $bulge * $ny)),
            ], 0);
        };

        $low = 0.0;
        $high = max(1.0, $chord);

        while ($lengthAt($high) < $target && $high < ($chord * 8) + 60) {
            $high *= 1.6;
        }

        for ($i = 0; $i < 44; $i++) {
            $bulge = ($low + $high) / 2;

            if ($lengthAt($bulge) < $target) {
                $low = $bulge;
            } else {
                $high = $bulge;
            }
        }

        $bulge = ($low + $high) / 2;

        return Geometry::curve($to['x'], $to['y'], $midX + (2 * $bulge * $nx), $midY + (2 * $bulge * $ny));
    }

    /**
     * چاک و دکمه‌های سر آستین.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markSleeveVent(array $piece, float $width, float $length, int $buttons): array
    {
        $vent = min(12.0, max(6.0, $length * 0.18));
        $x = max(1.5, $width - 3.0);

        $piece['markers'][] = $this->marker('vent', 'چاک سر آستین', $x, $length - $vent, $x, $length);

        for ($i = 0; $i < $buttons; $i++) {
            $piece['drills'][] = [
                'key' => 'sleeve_button_'.($i + 1),
                'label' => 'دکمه سر آستین '.$this->fa($i + 1),
                'x' => round(max(1.0, $x - 1.5), 2),
                'y' => round($length - 2.5 - ($i * 2.6), 2),
            ];
        }

        $piece['meta']['vent'] = round($vent, 2);
        $piece['meta']['notions'][] = [
            'type' => 'button',
            'label' => 'دکمه سر آستین',
            'count' => $buttons,
            'per_cut' => true,
        ];

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  یقه و سجاف
     * ------------------------------------------------------------------- */

    /**
     * یقهٔ زیر: تکه‌ای که پشت گردن می‌ایستد و جلو روی سینه می‌خوابد.
     *
     * روی اریب بریده می‌شود؛ یقهٔ راستا پشت گردن بلند می‌شود و نمی‌خوابد.
     *
     * @return array<string, mixed>
     */
    protected function underCollarPiece(float $halfNeck, float $height, array $o = []): array
    {
        $collar = $this->turnCollarPiece($halfNeck, $height, array_merge([
            'prefix' => $o['prefix'] ?? 'suit-',
            'name' => $o['name'] ?? 'یقه زیر',
            'point' => (float) ($o['point'] ?? 3.0),
        ], $o));

        $collar['code'] = ($o['prefix'] ?? 'suit-').'under-collar';
        $collar['grainline'] = [
            'from' => Geometry::point(max(1.0, $halfNeck * 0.25), 1.0),
            'to' => Geometry::point(max(1.0, $halfNeck * 0.25) + max(1.5, $height * 0.6), max(1.5, $height * 0.6) + 1.0),
            'label' => 'راستای پارچه (اریب)',
        ];
        $collar['meta']['bias'] = true;
        $collar['meta']['stand'] = round($height * 0.45, 2);
        $collar['meta']['notes'] = [
            'روی اریب بریده می‌شود؛ یقهٔ راستا پشت گردن بلند می‌ایستد و نمی‌خوابد.',
            'پایهٔ یقه حدود '.$this->fa(round($height * 0.45, 1)).' سانتی‌متر است و بقیه روی شانه می‌خوابد.',
            'در دوخت خیاطی از پارچهٔ نمدی (ملتون) یقهٔ زیر بریده و با کوک لایه‌دوزی می‌شود.',
        ];

        return $collar;
    }

    /**
     * سجاف جلو با شکل برگردان یقهٔ انگلیسی (نازک‌دار).
     *
     * برگردان یقه همان لایه‌ای است که دیده می‌شود، و آن لایه سجاف است؛ پس شکل
     * برگردان این‌جا درفت می‌شود و روی تنه فقط خط برگردان علامت می‌خورد.
     *
     * @param  array<string, float>  $g
     * @return array<string, mixed>
     */
    protected function notchedFacingPiece(array $g, float $stand, float $bottom, float $lapel, float $breakY, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? 'suit-');
        $top = $g['neck_width'] + $stand;
        $width = max(6.0, (float) ($o['width'] ?? 9));
        $gorge = max(3.0, min($breakY * 0.35, $g['bust_y'] * 0.4));
        $point = max(2.0, $lapel * 0.35);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($top, 0),
            Geometry::point($top + $point, $gorge),
            Geometry::point(max($width + 1.0, $lapel), $breakY),
            Geometry::curve($width, $bottom, $lapel + 1.5, $breakY + (($bottom - $breakY) * 0.45)),
            Geometry::point(0, $bottom),
        ];

        return $this->piece([
            'code' => $prefix.'front-facing',
            'name' => 'سجاف جلو با برگردان یقه',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, $gorge + 2, $bottom - 3),
            'markers' => [
                $this->marker('roll_line', 'خط برگردان یقه', 0, $breakY, min($top, max($width + 1.0, $lapel)), $gorge * 0.5),
            ],
            'notches' => [
                $this->notch($top + $point, $gorge, 2, 'گلوگاه (محل رسیدن یقه زیر)', 'gorge'),
            ],
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'default', 'default', 'default', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
                'lapel_width' => round($lapel, 2),
                'lapel_break_y' => round($breakY, 2),
                'notes' => [
                    'برگردان یقه با همین سجاف بریده می‌شود، نه با تنه؛ روی تنه فقط خط برگردان علامت می‌خورد.',
                    'همهٔ سجاف لایی می‌خورد؛ ناحیهٔ برگردان با لایی‌چسبِ نرم‌تر تا خط برگردان.',
                ],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  جیب فیلتاب
     * ------------------------------------------------------------------- */

    /**
     * جیب فیلتاب: فیلتاب، درپوش (اختیاری) و کیسه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function jettedPocketSet(float $opening, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? 'suit-');
        $key = (string) ($o['key'] ?? 'hip');
        $name = (string) ($o['name'] ?? 'جیب پهلو');
        $width = max(9.0, $opening);
        $pieces = [];

        $pieces[] = $this->bandPiece($prefix.$key.'-jet', 'فیلتاب '.$name, $width + 3, 4.5, [
            'cut' => (int) ($o['cut'] ?? 4),
            'part' => 'pocket',
            'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'pocket_opening' => round($width, 2),
                'notes' => [
                    'دو فیلتاب برای هر جیب؛ هرکدام از وسط تا می‌خورد و دهانهٔ '
                        .$this->fa(round($width, 1)).' سانتی‌متری را می‌سازد.',
                    'راستای پارچه در طول فیلتاب است، وگرنه دهانهٔ جیب باز می‌ماند.',
                ],
            ],
        ]);

        if ($o['flap'] ?? false) {
            $pieces[] = $this->flapPiece($prefix.$key.'-flap', 'درپوش '.$name, $width, (float) ($o['flap_height'] ?? 5.5));
        }

        $pieces[] = $this->bandPiece($prefix.$key.'-bag', 'کیسه '.$name, $width + 4, (float) ($o['depth'] ?? 30), [
            'cut' => 2,
            'part' => 'pocket',
            'layer' => 'lining',
            'meta' => [
                'girth_role' => 'lining',
                'notes' => ['از آستر بریده می‌شود؛ لبهٔ بالایی‌اش نواری از پارچهٔ رو می‌گیرد تا از دهانه دیده نشود.'],
            ],
        ]);

        return $pieces;
    }

    /** درپوش جیب، با گوشه‌های گرد. */
    protected function flapPiece(string $code, string $name, float $width, float $height): array
    {
        $radius = min(2.0, $height * 0.3);

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => 4,
            'mirror' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height - $radius),
                Geometry::curve($width - $radius, $height, $width, $height),
                Geometry::point($radius, $height),
                Geometry::curve(0, $height - $radius, 0, $height),
            ],
            'grainline' => $this->grainline($width * 0.5, 1, $height - 1),
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'default', 'hem', 'default', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
                'notes' => [
                    'دو تکه برای هر درپوش (رو و آستر)؛ آستر یک میلی‌متر کوچک‌تر بریده می‌شود تا درز به پشت برگردد.',
                ],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  آستر
     * ------------------------------------------------------------------- */

    /**
     * آستر تنه: جلو تا خط سجاف، پشت با پیلی راحتی مرکز.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function suitLining(array $g, array $params, array $o = []): array
    {
        if (! $this->flag($params, 'lining', true)) {
            return [];
        }

        $prefix = (string) ($o['prefix'] ?? 'suit-');
        $length = max(4.0, (float) ($o['length'] ?? $this->param($params, 'length', 24)) - 2);
        $grow = (float) ($o['grow'] ?? 0);

        $shared = [
            'shape' => 'fitted',
            'length' => $length,
            'grow' => $grow + 0.4,
            'bottom_tag' => 'hem',
            'layer' => 'lining',
            'part' => 'lining',
            'waist_dart' => false,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => $prefix.'lining-front',
            'name' => 'آستر جلو',
            'meta' => ['notes' => ['لبهٔ جلو تا خط سجاف بریده می‌شود؛ همین‌جا به سجاف دوخته می‌شود.']],
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => $prefix.'lining-back',
            'name' => 'آستر پشت',
            'meta' => ['notes' => ['مرکز پشت یک‌و‌نیم سانتی‌متر پیلی راحتی می‌گیرد تا آستر با حرکت شانه کشیده نشود.']],
        ]));

        return [$front, $back];
    }
}
