<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsPants;
use App\Services\Pattern\Geometry;

/**
 * پایه مشترک لباس‌های یک‌تکه و لباس کار.
 *
 * لباس یک‌تکه یک لباس نیست؛ دو لباس است که روی یک خط به هم می‌رسند. جای شکستنش
 * خط کمر است و تقریباً همه سختی این خانواده در همان خط جمع می‌شود:
 *
 *   ۱. لبه کمر بالاتنه و لبه کمر پاچه باید دقیقاً هم‌اندازه باشند. برای همین در
 *      این خانواده بالاتنه هیچ‌وقت ساسون کمر نمی‌گیرد — کاهش کمر تماماً روی درز
 *      پهلو می‌نشیند — و همان آزادی که به بالاتنه داده شده عیناً به شلوار هم
 *      می‌رسد (legEase). یک سانتی‌متر اختلاف یعنی یکی از دو لبه کشیده دوخته
 *      می‌شود و خط کمر روی تن می‌پیچد.
 *   ۲. «رایز کل»: فاصله گردن تا فاق. لباس دوتکه این اندازه را ندارد چون پیراهن
 *      روی شلوار می‌لغزد؛ لباس یک‌تکه نمی‌لغزد. اگر رایز کوتاه باشد، پوشنده که
 *      می‌نشیند لباس از سرشانه بالا می‌کشد. پس هر مدل این خانواده دو آزادی جدا
 *      دارد — یکی روی قد بالاتنه (rise_ease) و یکی روی قد فاق (rise_extra) — و
 *      جمعشان را روی meta.rise اعلام می‌کند تا آزمون و کاربر هر دو ببینندش.
 *   ۳. جلوی سرتاسری «اضافه جای دکمه» روی خود پنل نمی‌گیرد. اگر بگیرد، لبه کمر
 *      بالاتنه به اندازه همان اضافه از لبه کمر پاچه بلندتر می‌شود. پس بست
 *      سرتاسری یا روی درز مرکزی زیپ می‌خورد، یا با پاتلت جدا دوخته می‌شود.
 *
 * قد پاچه: legPanel قد داخل پا را دست‌کم سی سانتی‌متر می‌گیرد، پس با آن نمی‌شود
 * شورت درفت کرد؛ برای پاچه کوتاه shortLegPanel این‌جا هست که همان لبه کمر را
 * می‌سازد ولی خط زانو ندارد.
 */
abstract class OnePieceBaseGenerator extends BodiceGarmentBase
{
    use DraftsPants;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'onepiece';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای درفت بالاتنه این خانواده.
     *
     * @param  array<string, float>  $defaults
     * @return array<string, array<string, mixed>>
     */
    protected function onePieceSchema(array $defaults = []): array
    {
        return $this->outerSchema(array_merge([
            'shoulder_slope' => 4,
            'neck_width_extra' => 1,
            'front_neck_depth_extra' => 2.5,
            'back_neck_depth' => 2.5,
            'armhole_depth_extra' => 2.5,
        ], $defaults));
    }

    /**
     * دو آزادی رایز: یکی روی قد بالاتنه و یکی روی قد فاق.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function riseSchema(float $bodice = 3, float $crotch = 2.5): array
    {
        return [
            'rise_ease' => [
                'label' => 'آزادی قد بالاتنه', 'min' => 0, 'max' => 10, 'step' => 0.5,
                'default' => $bodice, 'unit' => 'سانتی‌متر',
                'hint' => 'بالاتنه این‌قدر بلندتر درفت می‌شود تا نشستن و خم شدن راحت باشد.',
            ],
            'rise_extra' => [
                'label' => 'آزادی قد فاق', 'min' => 0, 'max' => 10, 'step' => 0.5,
                'default' => $crotch, 'unit' => 'سانتی‌متر',
                'hint' => 'جمع این دو عدد همان آزادی نشستن است و روی الگو اعلام می‌شود.',
            ],
        ];
    }

    /**
     * پارامترهای پاچه بلند.
     *
     * @param  array<string, float>  $defaults
     * @return array<string, array<string, mixed>>
     */
    protected function legSchema(array $defaults = []): array
    {
        $schema = [
            'length_extra' => [
                'label' => 'تغییر قد پا', 'min' => -40, 'max' => 12, 'step' => 1,
                'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'knee_ease' => [
                'label' => 'آزادی دور زانو', 'min' => 2, 'max' => 34, 'step' => 1,
                'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دم پا', 'min' => 2, 'max' => 48, 'step' => 1,
                'default' => 14, 'unit' => 'سانتی‌متر',
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (isset($schema[$key])) {
                $schema[$key]['default'] = $value;
            }
        }

        return $schema;
    }

    /**
     * پارامترهای پاچه کوتاه (شورت).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function shortLegSchema(float $length = 14, float $hemEase = 8): array
    {
        return [
            'short_length' => [
                'label' => 'بلندی پاچه از فاق', 'min' => 4, 'max' => 42, 'step' => 1,
                'default' => $length, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دم پاچه روی ران', 'min' => 2, 'max' => 30, 'step' => 1,
                'default' => $hemEase, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  رایز
     * ------------------------------------------------------------------- */

    /**
     * آزادی قد بالاتنه را به درفت بلوک می‌رساند.
     *
     * باید یک بار و پیش از blockMetrics صدا زده شود؛ دو بار زدنش رایز را دو
     * برابر می‌کند.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function withRise(array $params): array
    {
        return array_merge($params, [
            'bodice_length_extra' => (float) $this->param($params, 'bodice_length_extra', 0)
                + (float) $this->param($params, 'rise_ease', 0),
        ]);
    }

    /**
     * جمع دو آزادی رایز.
     *
     * @param  array<string, mixed>  $params
     */
    protected function riseEase(array $params): float
    {
        return (float) $this->param($params, 'rise_ease', 0) + (float) $this->param($params, 'rise_extra', 0);
    }

    /**
     * ثبت رایز کل روی قطعه‌های تنه.
     *
     * قد کل تن = قد بالاتنه جلو (از گردن تا خط کمر) + قد فاق پاچه. آزادی نشستن
     * همان جمع دو پارامتر رایز است و «قد بدن» از کم کردن همین آزادی درمی‌آید.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function stampRise(array $pieces, array $g, array $params): array
    {
        $crotch = 0.0;

        foreach ($pieces as $piece) {
            $crotch = max($crotch, (float) ($piece['meta']['crotch_depth'] ?? 0));
        }

        if ($crotch <= 0.0) {
            return $pieces;
        }

        $ease = $this->riseEase($params);
        $total = round($g['front_waist_y'] + $crotch, 2);

        $rise = [
            'bodice' => round($g['front_waist_y'], 2),
            'crotch' => round($crotch, 2),
            'total' => $total,
            'ease' => round($ease, 2),
            'body' => round($total - $ease, 2),
        ];

        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['girth_role'] ?? '', ['shell', 'bottom'], true)) {
                continue;
            }

            $pieces[$index]['meta']['rise'] = $rise;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'قد کل تن (گردن تا فاق روی مرکز جلو) '.$this->fa($total).' سانتی‌متر درفت شد و '
                .$this->fa(round($ease, 1)).' سانتی‌متر آن آزادی نشستن است؛ سرهمی با رایز کوتاه'
                .' وقتی می‌نشینید از سرشانه بالا می‌کشد.',
        ]);

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  تنه
     * ------------------------------------------------------------------- */

    /**
     * همان آزادی بالاتنه، رسیده به شلوار.
     *
     * بلوک بالاتنه آزادی را روی «یک‌چهارم» می‌گیرد و شلوار روی «دور»؛ پس هر
     * سانتی‌متر رشد بالاتنه چهار سانتی‌متر آزادی شلوار است. بدون این تبدیل، لبه
     * کمر دو قطعه هم‌اندازه درنمی‌آید.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function legEase(array $ease, float $grow): array
    {
        return array_merge($ease, [
            'waist' => $this->ease($ease, 'waist', 4) + (4 * $grow),
            'hip' => $this->ease($ease, 'hip', 6) + (4 * $grow),
        ]);
    }

    /**
     * تنه کامل یک لباس یک‌تکه: بالاتنه، آستین و پاچه با کمرِ هم‌اندازه.
     *
     * گزینه‌ها: prefix، grow، panel (گزینه‌های مشترک دو پنل)، front، back،
     * sleeve، leg (گزینه‌های پاچه)، short (پاچه کوتاه به‌جای بلند)،
     * front_name، back_name، leg_front_name، leg_back_name.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function onePieceBody(array $m, array $ease, array $params, array $g, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $grow = (float) ($o['grow'] ?? $this->fitGrow($params));

        $shared = array_merge([
            'shape' => 'waist',
            'grow' => $grow,
            // ساسون کمر بسته است: هر سانتی‌متری که ساسون بخورد، لبه کمر بالاتنه
            // را از لبه کمر پاچه کوتاه‌تر می‌کند و آن دو دیگر به هم دوخته نمی‌شوند
            'waist_dart' => false,
            'bust_dart' => false,
        ], $o['panel'] ?? []);

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => $prefix.'bodice-front',
            'name' => $o['front_name'] ?? 'بالاتنه جلو',
        ], $o['front'] ?? []));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => $prefix.'bodice-back',
            'name' => $o['back_name'] ?? 'بالاتنه پشت',
        ], $o['back'] ?? []));

        [$front, $back] = $this->walkSideSeams($front, $back);

        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $m,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            array_merge(['prefix' => $prefix], $o['sleeve'] ?? []),
        ));

        $legEase = $this->legEase($ease, $grow);
        $short = (bool) ($o['short'] ?? false);

        // ساسون کمرِ پاچه پشت خودش را جبران می‌کند (لبه به همان اندازه پهن‌تر
        // بریده می‌شود)، ولی روی بدنی که کمر و باسنش نزدیک‌اند این پهنای بیشتر
        // به سقفِ پهنای پاچه می‌خورد و آن‌وقت لبه کمر پاچه از لبه کمر بالاتنه
        // کوتاه‌تر می‌ماند. در لباس یک‌تکه خودِ درز کمر این شکل را می‌دهد، پس
        // ساسون پاچه بسته است.
        $legParams = array_merge($params, ['back_darts' => (int) ($o['leg_darts'] ?? 0)]);

        foreach (['front', 'back'] as $side) {
            $options = array_merge([
                'side' => $side,
                'code' => $prefix.'leg-'.$side,
                'name' => $side === 'front'
                    ? ($o['leg_front_name'] ?? 'پاچه جلو')
                    : ($o['leg_back_name'] ?? 'پاچه پشت'),
            ], $o['leg'] ?? []);

            $leg = $short
                ? $this->shortLegPanel($m, $legEase, $legParams, $options)
                : $this->legPanel($m, $legEase, $legParams, $options);

            $leg['meta']['girth_role'] = 'bottom';
            $leg['meta']['notes'] = array_merge($leg['meta']['notes'] ?? [], [
                'لبه کمر این قطعه به لبه کمر بالاتنه دوخته می‌شود؛ هر دو با یک آزادی درفت شده‌اند.',
            ]);

            $pieces[] = $leg;
        }

        return $this->stampRise($pieces, $g, $params);
    }

    /**
     * پاچه کوتاه (شورت سرهمی).
     *
     * قاعده‌های پاچه شلوار نگه داشته شده — پیش‌آمدگی فاقِ جلو کم و پشت زیاد،
     * بالا آمدن لبه کمر پشت، لبه کمر دقیقاً به اندازه لبه کمر پاچه بلند — ولی
     * خط زانو حذف شده و لبه پایین از دور ران گرفته می‌شود، نه از دور مچ پا.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function shortLegPanel(array $m, array $ease, array $params, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $hip = $this->m($m, 'hip', 98) + $this->ease($ease, 'hip', 6);
        $waist = $this->m($m, 'waist', 74) + $this->ease($ease, 'waist', 4);

        $quarterHip = max(12.0, $hip / 4);
        $quarterWaist = max(9.0, $waist / 4);
        $crotchDepth = ($hip / 4) + 2.5 + (float) $this->param($params, 'rise_extra', 0);
        $hipY = min($crotchDepth - 5, max(10.0, $this->m($m, 'waist_to_hip', 21)));

        $panelWidth = $quarterHip + ($isFront ? -1 : 1);
        $crotchExtension = $isFront ? $hip / 16 : $hip / 10;
        $waistWidth = min($panelWidth, $quarterWaist + ($isFront ? -0.5 : 0.5));
        $backRise = $isFront ? 0.0 : 2.0;

        $legLength = max(4.0, (float) ($o['leg_length'] ?? $this->param($params, 'short_length', 14)));
        $hemTotal = (float) ($o['hem_total'] ?? (
            $this->m($m, 'thigh', $hip * 0.58) + (float) $this->param($params, 'hem_ease', 8)
        ));

        $hemWidth = max(10.0, ($hemTotal / 2) + ($isFront ? -1 : 1));
        $centerX = ($crotchExtension + $panelWidth) / 2;
        $sideX = $crotchExtension + $panelWidth;
        $hemY = $crotchDepth + $legLength;

        $hemOuter = max($centerX + 4.0, $centerX + ($hemWidth / 2));
        $hemInner = max(0.5, $hemOuter - $hemWidth);

        $outline = [
            Geometry::point($crotchExtension, 0),
            Geometry::point($crotchExtension + $waistWidth, $backRise),
            Geometry::curve($sideX, $hipY, $crotchExtension + $waistWidth + (($sideX - $crotchExtension - $waistWidth) * 0.6), $hipY * 0.45),
            Geometry::point($hemOuter, $hemY),
            Geometry::point($hemInner, $hemY),
            // درز داخل پا: کوتاه است و تقریباً عمودی، همان چیزی که شورت دارد
            Geometry::curve(0, $crotchDepth, max(0.2, $hemInner - 0.4), $crotchDepth + (($hemY - $crotchDepth) * 0.45)),
            Geometry::curve($crotchExtension, $hipY, $crotchExtension * ($isFront ? 0.2 : 0.32), $crotchDepth * 0.99),
        ];

        return $this->piece([
            'code' => $o['code'] ?? ($isFront ? 'short-front' : 'short-back'),
            'name' => $o['name'] ?? ($isFront ? 'پاچه جلو' : 'پاچه پشت'),
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($centerX, $hipY, $hemY - 2),
            'notches' => [
                $this->notch($sideX, $hipY, 2, 'نشانه باسن روی پهلو', 'side'),
                $this->notch(0, $crotchDepth, 5, 'نقطه فاق', 'crotch'),
            ],
            'markers' => [
                $this->marker('hip', 'خط باسن', $crotchExtension, $hipY, $sideX),
                $this->marker('crotch', 'خط فاق', 0, $crotchDepth, $sideX),
                $this->marker(
                    $isFront ? 'cf' : 'cb',
                    $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت',
                    $crotchExtension,
                    0,
                    $crotchExtension,
                    $hipY,
                ),
            ],
            'meta' => [
                'part' => $isFront ? 'front_leg' : 'back_leg',
                'edges' => ['waist', 'side', 'side', 'hem', 'side', 'side', 'default'],
                'fold_edges' => [],
                'side' => $isFront ? 'front' : 'back',
                'crotch_depth' => round($crotchDepth, 2),
                'hem_y' => round($hemY, 2),
                'panel_width' => round($panelWidth, 2),
                'hem_width' => round($hemWidth, 2),
                'leg_length' => round($legLength, 2),
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  بست سرتاسری
     * ------------------------------------------------------------------- */

    /**
     * بست سرتاسری جلو، بدون دست زدن به پهنای لبه کمر.
     *
     * زیپ روی همان درز مرکز جلو می‌نشیند (بالاتنه و پاچه هر دو درز مرکزی دارند)
     * و برای دکمه یک پاتلت جدا دوخته می‌شود. اگر «اضافه جای دکمه» را روی خود
     * پنل بگذاریم، لبه کمر بالاتنه بلندتر از لبه کمر پاچه می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function frontClosureSet(array $pieces, array $g, array $params, array $o = []): array
    {
        $kind = (string) ($o['kind'] ?? $this->param($params, 'closure', 'zip'));
        $prefix = (string) ($o['prefix'] ?? '');
        $legLength = (float) ($o['leg_drop'] ?? 0);
        $top = (float) $g['front_neck_depth'];
        $bottom = (float) $g['front_waist_y'] + $legLength;

        $index = null;

        foreach ($pieces as $i => $piece) {
            if (($piece['meta']['part'] ?? '') === 'front_bodice') {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return $pieces;
        }

        if ($kind === 'zip') {
            $pieces[$index]['markers'][] = $this->marker('zip', 'زیپ سرتاسری مرکز جلو', 0, $top, 0, (float) $g['front_waist_y']);
            $pieces[$index]['meta']['notions'][] = [
                'type' => 'zip',
                'label' => 'زیپ جداشونده سرتاسری',
                'count' => 1,
                'length' => round($bottom - $top, 1),
            ];
            $pieces[$index]['meta']['notes'] = array_merge($pieces[$index]['meta']['notes'] ?? [], [
                'زیپ روی درز مرکز جلو می‌نشیند و از یقه تا پایین‌تر از خط کمر ادامه دارد؛'
                    .' نیمه پایین آن روی درز مرکز پاچه جلو دوخته می‌شود.',
            ]);

            return $pieces;
        }

        $count = max(2, (int) $this->param($params, 'buttons', 7));
        $stand = max(1.5, (float) $this->param($params, 'button_stand', 3));

        $pieces[$index]['meta']['notes'] = array_merge($pieces[$index]['meta']['notes'] ?? [], [
            'جای دکمه روی پاتلت جدا دوخته می‌شود، نه روی خود تنه؛ اگر اضافه جای دکمه'
                .' را به تنه بدهید، لبه کمر بالاتنه از لبه کمر پاچه بلندتر می‌شود.',
        ]);

        $pieces[] = $this->placketPiece($prefix, $bottom - $top, $stand, $count, (string) ($o['notion'] ?? 'button'));

        return $pieces;
    }

    /**
     * پاتلت جای دکمه یا دکمه فشاری برای بست سرتاسری.
     *
     * @return array<string, mixed>
     */
    protected function placketPiece(string $prefix, float $length, float $stand, int $count, string $notion = 'button'): array
    {
        $width = max(3.0, $stand * 2);
        $length = max(20.0, $length);
        $step = ($length - 6) / max(1, $count - 1);
        $drills = [];

        for ($i = 0; $i < $count; $i++) {
            $drills[] = [
                'key' => 'button_'.($i + 1),
                'label' => ($notion === 'snap' ? 'دکمه فشاری ' : 'جای دکمه ').$this->fa($i + 1),
                'x' => round($width / 2, 2),
                'y' => round(3 + ($step * $i), 2),
            ];
        }

        $piece = $this->bandPiece($prefix.'front-placket', 'پاتلت جلو', $length, $width, [
            'cut' => 2, 'part' => 'placket', 'fold_line' => true,
            'meta' => [
                'interfacing' => true,
                'girth_role' => 'trim',
                'notions' => [[
                    'type' => $notion,
                    'label' => $notion === 'snap' ? 'دکمه فشاری جلو' : 'دکمه جلو',
                    'count' => $count,
                ]],
                'notes' => ['دو تکه بریده می‌شود: یکی جادکمه و یکی دکمه‌خور.'],
            ],
        ]);

        $piece['drills'] = $drills;

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  جیب کار
     * ------------------------------------------------------------------- */

    /**
     * جیب کارگاهی با درپوش: کیسه و درپوش، هر دو با یک پهنا.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function utilityPocketSet(float $width, float $height, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $gusset = (float) ($o['gusset'] ?? 0);
        $bagWidth = $width + $gusset;

        $pleats = $gusset > 0.1 ? [[
            'type' => 'pleat',
            'label' => 'پیلی حجم جیب',
            'edge' => 0,
            'intake' => round($gusset, 2),
            'depth' => round($gusset / 2, 2),
            'from' => Geometry::point($bagWidth / 2, 0),
            'to' => Geometry::point($bagWidth / 2, $height),
        ]] : [];

        $bag = $this->piece([
            'code' => $prefix.'utility-pocket',
            'name' => $o['name'] ?? 'جیب کار',
            'cut_quantity' => (int) ($o['cut'] ?? 2),
            'mirror' => false,
            'pleats' => $pleats,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($bagWidth, 0),
                Geometry::point($bagWidth, $height - 2.5),
                Geometry::curve($bagWidth - 2.5, $height, $bagWidth - 0.6, $height - 0.6),
                Geometry::point(2.5, $height),
                Geometry::curve(0, $height - 2.5, 0.6, $height - 0.6),
            ],
            'grainline' => $this->grainline($bagWidth * 0.5, 2, $height - 2),
            'markers' => [$this->marker('fold', 'تای دهانه جیب', 0, 3, $bagWidth)],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'hem', 'hem', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => $gusset > 0.1
                    ? ['پهنای این جیب '.$this->fa(round($gusset, 1)).' سانتی‌متر بیشتر از جای دوختش است؛'
                        .' همان اضافه با پیلی وسط جیب جا می‌شود و به جیب حجم می‌دهد.']
                    : [],
            ],
        ]);

        $flapHeight = max(4.0, (float) ($o['flap'] ?? 6));

        $flap = $this->piece([
            'code' => $prefix.'utility-flap',
            'name' => 'درپوش جیب کار',
            'cut_quantity' => (int) ($o['cut'] ?? 2) * 2,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width + 1, 0),
                Geometry::point($width + 1, $flapHeight - 1.5),
                Geometry::curve($width - 0.5, $flapHeight, $width + 1, $flapHeight),
                Geometry::point(1.5, $flapHeight),
                Geometry::curve(0, $flapHeight - 1.5, 0, $flapHeight),
            ],
            'grainline' => $this->grainline(($width + 1) * 0.5, 1, $flapHeight - 1),
            'drills' => [[
                'key' => 'snap_1',
                'label' => 'دکمه فشاری درپوش',
                'x' => round(($width + 1) / 2, 2),
                'y' => round($flapHeight - 2, 2),
            ]],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'hem', 'hem', 'hem', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
                'notions' => [['type' => 'snap', 'label' => 'دکمه فشاری درپوش جیب', 'count' => 1, 'per_cut' => true]],
                'notes' => ['دو لایه برای هر درپوش بریده می‌شود.'],
            ],
        ]);

        return [$bag, $flap];
    }
}
