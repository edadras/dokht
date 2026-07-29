<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;

/**
 * درفت پایه همه شلوارها و شلوارک‌ها.
 *
 * قرارداد این درفت — و دلیل هر بند آن:
 *
 *   ۱. «فاق ایستاده» (body rise) از دو راه حساب و میانگین گرفته می‌شود: قد بیرون
 *      پا منهای قد داخل پا، و باسن÷۴ + ۲٫۵. اولی با قد بدن بالا می‌رود و دومی با
 *      دور باسن؛ میانگینشان هم با قد و هم با تنومندی درست سایزبندی می‌شود. بعد
 *      بین «کمر تا باسن + ۵» و «کمر تا باسن + ۱۵» مهار می‌شود تا هیچ سایزی فاقِ
 *      بی‌ربط نگیرد.
 *
 *   ۲. «بلندی فاق» (کوتاه/متوسط/بلند) یک پارامتر است نه یک مدل جدا: خط کمرِ الگو
 *      به اندازه drop از گودی کمر پایین می‌آید. چون بدن آنجا گشادتر است، دور کمرِ
 *      هدف هم بین گودی کمر، باسن کوچک و باسن درون‌یابی می‌شود؛ شلوار کمرکوتاه
 *      روی دور کمرِ گودی بریده نمی‌شود.
 *
 *   ۳. پای جلو و پشت دو نیمه یک لوله‌اند، پس دو لبه‌ای که به هم دوخته می‌شوند —
 *      درز پهلو و درز داخل پا — باید هم‌شکل باشند. برای همین:
 *        • سهم درز پهلو از کاهش کمر در جلو و پشت یک عدد است،
 *        • اختلاف پهنای جلو و پشت در همه ترازهای زیر فاق یک عدد ثابت است:
 *          legBalance = (پنجه فاق پشت − پنجه فاق جلو) + ۲ × اختلاف باسن
 *          و همین عدد پهنای زانو و دم پا را هم بین جلو و پشت تقسیم می‌کند.
 *      با این یک قاعده، درز پهلو و درز داخل پای جلو و پشت بدون راست‌سازی هم‌اندازه
 *      درمی‌آیند — چیزی که در بیشتر درفت‌ها با «کمی چین دادن پشت» رفع می‌شود.
 *
 *   ۴. منحنی فاق جلو و پشت یکی نیستند: پنجه فاق پشت (باسن÷۱۰) بلندتر از جلو
 *      (باسن÷۱۶) است، منحنی پشت گودتر و قلاب‌دارتر بریده می‌شود، خط مرکز پشت به
 *      اندازه tilt به سمت پهلو می‌خوابد و سرِ کمرش به اندازه back_rise بالاتر
 *      می‌ایستد — همان چیزی که نشستن را ممکن می‌کند.
 *
 *   ۵. کاهش کمر تا باسن بین چهار جا پخش می‌شود: درز پهلو، خوابیدن خط مرکز (lean
 *      در جلو، tilt در پشت)، و ساسون یا پیلی یا چین. هرچه بماند دقیقاً به ساسون
 *      می‌رود، پس دور کمر تمام‌شده همیشه با دور کمر هدف برابر است:
 *
 *        ۲ × (کمر جلو − ساسون جلو) + ۲ × (کمر پشت − ساسون پشت) = دور کمر هدف
 *
 *   ۶. هر چین و پیلی هم برای رسم در piece.pleats می‌آید و هم با FullnessRecorder
 *      در meta ثبت می‌شود. «depth» هر پیلی همان مقداری است که روی خط کمر خورده
 *      می‌شود (نه ژرفای تای پارچه)، چون همه مصرف‌کننده‌های این سامانه depth را به
 *      همین معنا می‌خوانند؛ ژرفای واقعی تا در fold_depth است.
 *
 * مبدأ هر پای شلوار نوکِ پنجه فاق است (x=0) و خط مرکز جلو/پشت به اندازه پنجه فاق
 * از آن فاصله دارد. y از خط کمرِ همان قطعه به پایین می‌رود.
 */
trait PantsBlock
{
    /** بلندی فاق: کلید ⇒ [نام فارسی، پایین آمدن خط کمر از گودی کمر]. */
    public const RISES = [
        'low' => ['کمر کوتاه', 9.0],
        'mid' => ['کمر متوسط', 4.0],
        'high' => ['کمر بلند', 0.0],
    ];

    /**
     * اندازه‌های کلیدی درفت شلوار.
     *
     * گزینه‌های $o که سبک شلوار تعیین می‌کند: stretch، waist_balance، hip_balance،
     * fork_scale، thigh_ease، knee_ease، hem_ease، hem_total، leg_length،
     * crotch_drop، front_waist (dart|pleat|none|gather)، back_waist (dart|none)،
     * side_share، lean_share، back_tilt، back_rise، scoop_front، scoop_back.
     *
     * @return array<string, mixed>
     */
    protected function pantsMetrics(array $m, array $ease, array $params, array $o = []): array
    {
        $stretch = max(0.55, (float) ($o['stretch'] ?? 1.0));

        $waistBody = $this->m($m, 'waist', 74);
        $hipBody = $this->m($m, 'hip', 98);
        $highHip = max($waistBody + 1, $this->m($m, 'high_hip', $hipBody - 8));
        $waistToHip = max(12.0, $this->m($m, 'waist_to_hip', 21));
        $inseamBody = max(45.0, $this->m($m, 'inseam', 75));
        $outseamBody = $this->m($m, 'outseam', 0);
        $thighBody = $this->m($m, 'thigh', $hipBody * 0.58);
        $kneeBody = $this->m($m, 'knee', $hipBody * 0.38);
        $ankleBody = $this->m($m, 'ankle', $hipBody * 0.24);

        // ۱) فاق ایستاده
        $fromHip = ($hipBody / 4) + 2.5;
        $fromLegs = $outseamBody > $inseamBody ? $outseamBody - $inseamBody : null;
        $bodyRise = $fromLegs !== null ? (($fromLegs + $fromHip) / 2) : $fromHip;
        $bodyRise = min($waistToHip + 15, max($waistToHip + 5, $bodyRise));
        $bodyRise = max(14.0, $bodyRise + (float) $this->param($params, 'rise_extra', 0));

        // ۲) بلندی فاق و دور کمرِ همان تراز
        [$riseKey, $drop] = $this->riseDrop($params, $waistToHip, $o);
        $waistGirth = $this->girthAtDrop($waistBody, $highHip, $hipBody, $waistToHip, $drop);

        $crotchDrop = max(0.0, (float) ($o['crotch_drop'] ?? 0));
        $crotchDepth = max(12.0, $bodyRise - $drop) + $crotchDrop;
        $hipY = max(6.0, $waistToHip - $drop);

        $waistEase = $this->ease($ease, 'waist', (float) ($o['waist_ease'] ?? 4));
        $hipEase = $this->ease($ease, 'hip', (float) ($o['hip_ease'] ?? 6));
        $waistTarget = max(30.0, ($waistGirth + $waistEase) * $stretch);
        $hipTarget = max(40.0, ($hipBody + $hipEase) * $stretch);
        $quarterHip = $hipTarget / 4;

        // بلندی پا و ترازهای زانو و دم پا
        $legLength = max(4.0, ((float) ($o['leg_length'] ?? $inseamBody))
            + (float) $this->param($params, 'length_extra', 0)
            + (float) ($o['length_offset'] ?? 0)
            - $crotchDrop);
        $kneeY = $crotchDepth + ($inseamBody * 0.47) - $crotchDrop;
        $hemY = $crotchDepth + $legLength;
        $hasKnee = $hemY > $kneeY + 4 && $kneeY > $crotchDepth + 6;

        $kneeTotal = max(18.0, ($kneeBody + (float) ($o['knee_ease'] ?? $this->param($params, 'knee_ease', 7))) * $stretch);

        if (isset($o['hem_total'])) {
            $hemTotal = max(14.0, (float) $o['hem_total']);
        } else {
            $atHem = $this->legGirthAt($thighBody, $kneeBody, $ankleBody, $legLength / max(1.0, $inseamBody));
            $hemTotal = max(14.0, ($atHem + (float) ($o['hem_ease'] ?? $this->param($params, 'hem_ease', 10))) * $stretch);
        }

        // نسبت دم پا به زانو، همان چیزی که مدل را باریک‌شونده یا دم‌پا گشاد می‌کند
        if ($hasKnee && isset($o['hem_vs_knee'])) {
            $delta = (float) $o['hem_vs_knee'];
            $hemTotal = $delta >= 0
                ? max($hemTotal, $kneeTotal + $delta)
                : min($hemTotal, $kneeTotal + $delta);
            $hemTotal = max(14.0, $hemTotal);
        }

        // ۳) پنجه فاق و اختلاف ثابت جلو و پشت در همه ترازهای زیر فاق
        $forkScale = max(0.55, (float) ($o['fork_scale'] ?? 1.0));
        $forkFront = ($hipTarget / 16) * $forkScale;
        $forkBack = ($hipTarget / 10) * $forkScale;
        $hipBalance = max(0.0, (float) ($o['hip_balance'] ?? 1.0));
        $legBalance = ($forkBack - $forkFront) + (2 * $hipBalance);

        $room = 2 * ($hasKnee
            ? min(($kneeTotal / 2) - 7.0, ($hemTotal / 2) - 6.0)
            : ($hemTotal / 2) - 6.0);

        if ($legBalance > $room && $legBalance > 0.01) {
            $shrink = max(0.0, $room) / $legBalance;
            $forkBack = $forkFront + (($forkBack - $forkFront) * $shrink);
            $hipBalance *= $shrink;
            $legBalance *= $shrink;
        }

        $frontPanel = $quarterHip - $hipBalance;
        $backPanel = $quarterHip + $hipBalance;

        $kneeFront = ($kneeTotal - $legBalance) / 2;
        $kneeBack = ($kneeTotal + $legBalance) / 2;
        $hemFront = ($hemTotal - $legBalance) / 2;
        $hemBack = ($hemTotal + $legBalance) / 2;

        // آزادی دور ران: خط فاق نسبت به خط باسن تو یا بیرون می‌رود، در دو پنل یکسان
        $naturalThigh = $forkFront + $forkBack + (2 * $quarterHip);
        $thighAdjust = 0.0;

        if (isset($o['thigh_ease'])) {
            $thighTarget = ($thighBody + (float) $o['thigh_ease']) * $stretch;
            $floor = max($kneeFront, $kneeBack) / 2 + 1.5 - $frontPanel;
            $thighAdjust = max($floor, min(9.0, ($thighTarget - $naturalThigh) / 2));
        }

        // ۵) پخش کاهش کمر
        $waist = $this->waistPlan($o, $waistTarget, $frontPanel, $backPanel, $hipY);

        return array_merge([
            'stretch' => $stretch,
            'waist_ease' => $waistEase,
            'hip_ease' => $hipEase,
            'waist_body' => round($waistGirth, 2),
            'waist_target' => round($waistTarget, 3),
            'hip_target' => round($hipTarget, 3),
            'quarter_hip' => $quarterHip,
            'front_panel' => $frontPanel,
            'back_panel' => $backPanel,
            'fork_front' => $forkFront,
            'fork_back' => $forkBack,
            'leg_balance' => $legBalance,
            'hip_balance' => $hipBalance,
            'body_rise' => round($bodyRise, 2),
            'rise_key' => $riseKey,
            'waist_drop' => round($drop, 2),
            'crotch_drop' => round($crotchDrop, 2),
            'crotch_depth' => $crotchDepth,
            'hip_y' => $hipY,
            'knee_y' => $kneeY,
            'hem_y' => $hemY,
            'has_knee' => $hasKnee,
            'leg_length' => $legLength,
            'inseam_body' => $inseamBody,
            'knee_total' => $kneeTotal,
            'hem_total' => $hemTotal,
            'knee_w_front' => $kneeFront,
            'knee_w_back' => $kneeBack,
            'hem_w_front' => $hemFront,
            'hem_w_back' => $hemBack,
            'thigh_body' => $thighBody,
            'thigh_adjust' => $thighAdjust,
            'thigh_finished' => round($naturalThigh + (2 * $thighAdjust), 2),
            'scoop_front' => (float) ($o['scoop_front'] ?? 0.55),
            'scoop_back' => (float) ($o['scoop_back'] ?? 0.38),
        ], $waist);
    }

    /**
     * بلندی فاق: کلید و مقدار پایین آمدن خط کمر از گودی کمر.
     *
     * @return array{0: string, 1: float}
     */
    protected function riseDrop(array $params, float $waistToHip, array $o = []): array
    {
        $key = (string) $this->param($params, 'rise', $o['rise'] ?? 'mid');
        $key = isset(static::RISES[$key]) ? $key : 'mid';
        $drop = static::RISES[$key][1] + (float) $this->param($params, 'waist_drop_extra', 0);

        return [$key, max(0.0, min($waistToHip - 6, $drop))];
    }

    /**
     * دور بدن در فاصله drop پایین‌تر از گودی کمر.
     *
     * بین سه اندازه واقعی درون‌یابی می‌شود: گودی کمر (۰)، باسن کوچک (نیمه راه) و
     * باسن (کمر تا باسن).
     */
    protected function girthAtDrop(float $waist, float $highHip, float $hip, float $waistToHip, float $drop): float
    {
        if ($drop <= 0.01) {
            return $waist;
        }

        $mid = $waistToHip / 2;

        if ($drop <= $mid) {
            return $waist + (($highHip - $waist) * ($drop / $mid));
        }

        if ($drop >= $waistToHip) {
            return $hip;
        }

        return $highHip + (($hip - $highHip) * (($drop - $mid) / $mid));
    }

    /**
     * دور پا در تراز دم شلوار.
     *
     * بین سه اندازه واقعی درون‌یابی می‌شود: دور ران (سر فاق)، دور زانو (۴۷٪ قد
     * داخل پا) و دور مچ پا (ته پا). پا در بالای زانو تندتر باریک می‌شود تا زیر
     * آن، و همین دو پاره خط همان رفتار را می‌دهد. برای شلوارک لازم است، وگرنه دم
     * شلوارک با دور مچ پا حساب می‌شود و روی ران گیر می‌کند.
     */
    protected function legGirthAt(float $thigh, float $knee, float $ankle, float $ratio, float $kneeAt = 0.47): float
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $kneeAt = min(0.9, max(0.15, $kneeAt));

        if ($ratio <= $kneeAt) {
            return $thigh + (($knee - $thigh) * ($ratio / $kneeAt));
        }

        return $knee + (($ankle - $knee) * (($ratio - $kneeAt) / (1 - $kneeAt)));
    }

    /**
     * پخش کاهش کمر بین درز پهلو، خوابیدن خط مرکز و ساسون/پیلی/چین.
     *
     * خروجی طوری بسته می‌شود که دور کمر تمام‌شده دقیقاً برابر دور کمر هدف دربیاید.
     *
     * @return array<string, mixed>
     */
    protected function waistPlan(array $o, float $waistTarget, float $frontPanel, float $backPanel, float $hipY): array
    {
        $mode = (string) ($o['front_waist'] ?? 'dart');
        $backMode = (string) ($o['back_waist'] ?? ($mode === 'gather' ? 'gather' : 'dart'));
        $balance = (float) ($o['waist_balance'] ?? 0.5);
        $backRise = max(0.0, (float) ($o['back_rise'] ?? 2.0));
        $tilt = max(0.6, (float) ($o['back_tilt'] ?? 2.0));
        $tiltMax = max($tilt, (float) ($o['back_tilt_max'] ?? 4.2));

        $targetFront = max(4.0, ($waistTarget / 4) - $balance);
        $targetBack = max(4.0, ($waistTarget / 4) + $balance);
        $notes = [];

        // کمر کشی: بالای خط باسن هیچ کاهشی داده نمی‌شود و همه‌اش چین می‌خورد.
        // دهانه کمر باید از دور باسن بزرگ‌تر بماند وگرنه شلوارِ بی‌بست از روی باسن
        // رد نمی‌شود؛ برای همین درز پهلو رو به کمر کمی باز می‌شود (سهم منفی).
        if ($mode === 'gather') {
            $tilt = max(0.0, (float) ($o['back_tilt'] ?? 1.5));
            $flare = max(0.0, ((float) ($o['waist_clearance'] ?? 3.0) + (2 * $tilt)) / 4);
            $frontWaist = $frontPanel + $flare;
            $backWaist = sqrt((max(2.0, $backPanel + $flare - $tilt) ** 2) + ($backRise ** 2));

            return [
                'front_waist' => $frontWaist,
                'back_waist' => $backWaist,
                'front_finished' => $targetFront,
                'back_finished' => $targetBack,
                'front_intake' => 0.0,
                'back_intake' => 0.0,
                'front_gather' => max(0.0, $frontWaist - $targetFront),
                'back_gather' => max(0.0, $backWaist - $targetBack),
                'side_supp' => -$flare,
                'lean' => 0.0,
                'tilt' => $tilt,
                'back_rise' => $backRise,
                'waist_mode' => 'gather',
                'waist_notes' => [],
            ];
        }

        $suppression = max(0.0, $frontPanel - $targetFront);
        $sideMax = min(5.5, $hipY * 0.32);
        $leanMax = (float) ($o['lean_max'] ?? 1.4);

        $side = min($sideMax, $suppression * (float) ($o['side_share'] ?? 0.33));
        $lean = min($leanMax, $suppression * (float) ($o['lean_share'] ?? 0.18));
        $frontIntake = max(0.0, $suppression - $side - $lean);

        if ($mode === 'none' || $frontIntake < 1.0) {
            $take = min(max(0.0, $sideMax - $side), $frontIntake);
            $side += $take;
            $lean += $frontIntake - $take;
            $frontIntake = 0.0;
            $leanHard = (float) ($o['lean_hard_max'] ?? 3.0);

            if ($lean > $leanHard) {
                $frontIntake = $lean - $leanHard;
                $notes[] = 'کاهش کمر جلو بیشتر از آن بود که تنها با درز پهلو و خوابیدن فاق گرفته شود، پس '
                    .$this->fa(round($frontIntake, 1)).' سانتی‌متر آن به ساسون جلو رفت.';
            }
        }

        // طول لبه کمر جلو دقیقاً «سهم تمام‌شده + ساسون» است؛ باقی به شیب فاق می‌رود
        $frontWaist = $targetFront + $frontIntake;
        $lean = max(0.0, $frontPanel - $side - $frontWaist);

        // پشت: همان سهم درز پهلو، بعد خوابیدن مرکز پشت، و باقی‌مانده به ساسون
        $sideWaistBack = $backPanel - $side;
        $backWaist = sqrt((max(2.0, $sideWaistBack - $tilt) ** 2) + ($backRise ** 2));
        $backIntake = max(0.0, $backWaist - $targetBack);

        if ($backMode === 'none' || $backIntake < 1.0) {
            $wanted = $sideWaistBack - sqrt(max(0.25, ($targetBack ** 2) - ($backRise ** 2)));

            if ($wanted <= $tiltMax) {
                $tilt = max(0.6, $wanted);
                $backWaist = $targetBack;
                $backIntake = 0.0;
            } else {
                $tilt = $tiltMax;
                $backWaist = sqrt((max(2.0, $sideWaistBack - $tilt) ** 2) + ($backRise ** 2));
                $backIntake = max(0.0, $backWaist - $targetBack);
                $notes[] = 'خط مرکز پشت بیشتر از این نمی‌خوابد، پس '.$this->fa(round($backIntake, 1))
                    .' سانتی‌متر کاهش کمر پشت به ساسون رفت.';
            }
        }

        return [
            'front_waist' => $frontWaist,
            'back_waist' => $backWaist,
            'front_finished' => $frontWaist - $frontIntake,
            'back_finished' => $backWaist - $backIntake,
            'front_intake' => $frontIntake,
            'back_intake' => $backIntake,
            'front_gather' => 0.0,
            'back_gather' => 0.0,
            'side_supp' => $side,
            'lean' => $lean,
            'tilt' => $tilt,
            'back_rise' => $backRise,
            'waist_mode' => $mode,
            'waist_notes' => $notes,
        ];
    }

    /**
     * یک پای شلوار (جلو یا پشت).
     *
     * گزینه‌ها: side، dart_count، pleat_count، pleat_style، paperbag (بلندی نوار
     * پاکتی بالای کمر)، hem_gather (دور تمام‌شده دم پا وقتی در مچ جمع می‌شود)،
     * hem_flare (باز شدن دم پا با منحنی)، code، name.
     *
     * @return array<string, mixed>
     */
    protected function legPanel(array $mx, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';

        $fork = (float) ($isFront ? $mx['fork_front'] : $mx['fork_back']);
        $panel = (float) ($isFront ? $mx['front_panel'] : $mx['back_panel']);
        $kneeW = (float) ($isFront ? $mx['knee_w_front'] : $mx['knee_w_back']);
        $hemW = (float) ($isFront ? $mx['hem_w_front'] : $mx['hem_w_back']);
        $waistEdge = (float) ($isFront ? $mx['front_waist'] : $mx['back_waist']);
        $intake = (float) ($isFront ? $mx['front_intake'] : $mx['back_intake']);
        $gather = (float) ($isFront ? $mx['front_gather'] : $mx['back_gather']);
        $centerLean = (float) ($isFront ? $mx['lean'] : $mx['tilt']);
        $scoop = (float) ($isFront ? $mx['scoop_front'] : $mx['scoop_back']);
        $drop = $isFront ? 0.0 : (float) $mx['back_rise'];

        $hipY = (float) $mx['hip_y'];
        $crotchY = (float) $mx['crotch_depth'];
        $kneeY = (float) $mx['knee_y'];
        $hemY = (float) $mx['hem_y'];
        $adjust = (float) $mx['thigh_adjust'];
        $side = (float) $mx['side_supp'];
        $hasKnee = (bool) $mx['has_knee'];
        $top = max(0.0, (float) ($o['paperbag'] ?? 0));
        $flare = (float) ($o['hem_flare'] ?? 0);

        $centerX = $fork + $centerLean;
        $sideWaistX = $fork + $panel - $side;
        $hipX = $fork + $panel;
        $crotchX = $hipX + $adjust;
        $crease = ($fork + $panel + $adjust) / 2;
        $kneeOut = $crease + ($kneeW / 2);
        $kneeIn = $crease - ($kneeW / 2);
        $hemOut = $crease + ($hemW / 2);
        $hemIn = $crease - ($hemW / 2);

        $y = fn (float $value) => $value + $drop + $top;

        $points = [];
        $tags = [];
        $add = function (array $point, string $tag) use (&$points, &$tags) {
            $points[] = $point;
            $tags[] = $tag;
        };

        // سر بالای خط مرکز؛ در پشت این نقطه بالاترین نقطه قطعه است
        $add(Geometry::point($centerX, 0), 'waist');

        if ($top > 0) {
            $add(Geometry::point($sideWaistX, $drop), 'side');
        }

        $add(Geometry::point($sideWaistX, $y(0)), 'side');
        $add(Geometry::curve($hipX, $y($hipY), $sideWaistX + ($side * 0.62), $y($hipY * 0.42)), 'side');
        $add(Geometry::curve($crotchX, $y($crotchY), $hipX + ($adjust * 0.4), $y($hipY + (($crotchY - $hipY) * 0.5))), 'side');

        // برچسبی که با هر نقطه ثبت می‌شود، برچسب لبه‌ای است که از همان نقطه بیرون
        // می‌رود؛ پس برچسب «دم» روی نقطه بیرونیِ دم می‌نشیند نه روی نقطه درونی.
        if ($hasKnee) {
            $add(Geometry::curve(
                $kneeOut,
                $y($kneeY),
                $crotchX + (($kneeOut - $crotchX) * 0.3),
                $y($crotchY + (($kneeY - $crotchY) * 0.55)),
            ), 'side');
            $add(abs($flare) > 0.05
                ? Geometry::curve($hemOut, $y($hemY), $kneeOut + (($hemOut - $kneeOut) * 0.25) - $flare, $y($kneeY + (($hemY - $kneeY) * 0.5)))
                : Geometry::point($hemOut, $y($hemY)), 'hem');
        } else {
            $add(Geometry::curve(
                $hemOut,
                $y($hemY),
                $crotchX + (($hemOut - $crotchX) * 0.35),
                $y($crotchY + (($hemY - $crotchY) * 0.55)),
            ), 'hem');
        }

        $add(Geometry::point($hemIn, $y($hemY)), 'side');

        if ($hasKnee) {
            $add(abs($flare) > 0.05
                ? Geometry::curve($kneeIn, $y($kneeY), $hemIn + (($kneeIn - $hemIn) * 0.25) + $flare, $y($kneeY + (($hemY - $kneeY) * 0.5)))
                : Geometry::point($kneeIn, $y($kneeY)), 'side');
            $add(Geometry::curve(0, $y($crotchY), $kneeIn * 0.55, $y($crotchY + (($kneeY - $crotchY) * 0.45))), 'default');
        } else {
            $add(Geometry::curve(0, $y($crotchY), $hemIn * 0.6, $y($crotchY + (($hemY - $crotchY) * 0.45))), 'default');
        }

        // منحنی فاق: دو پاره منحنی؛ پشت گودتر و قلاب‌دارتر از جلو
        $midX = $fork * $scoop;
        $midY = $hipY + (($crotchY - $hipY) * ($isFront ? 0.62 : 0.72));
        $add(Geometry::curve($midX, $y($midY), $midX * ($isFront ? 0.52 : 0.44), $y($crotchY * 0.995)), 'default');
        $add(Geometry::curve($fork, $y($hipY), $fork * ($isFront ? 0.95 : 0.92), $y($hipY + (($crotchY - $hipY) * ($isFront ? 0.3 : 0.33)))), 'default');

        $count = count($points);
        $offset = $top > 0 ? 1 : 0;

        $sideEdges = $hasKnee
            ? [1 + $offset, 2 + $offset, 3 + $offset, 4 + $offset]
            : [1 + $offset, 2 + $offset, 3 + $offset];

        if ($top > 0) {
            array_unshift($sideEdges, 1);
        }

        $hemEdge = ($hasKnee ? 5 : 4) + $offset;
        $inseamEdges = $hasKnee ? [$hemEdge + 1, $hemEdge + 2] : [$hemEdge + 1];
        $hipEdge = $sideEdges[$top > 0 ? 1 : 0];
        $thighEdge = $sideEdges[$top > 0 ? 2 : 1];

        $piece = [
            'code' => $o['code'] ?? ($isFront ? 'leg-front' : 'leg-back'),
            'name' => $o['name'] ?? ($isFront ? 'پای جلو' : 'پای پشت'),
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $points,
            'grainline' => $this->grainline($crease, $y($hipY * 0.4), $y($hemY) - 3),
            'darts' => [],
            'pleats' => [],
            'notches' => [
                $this->notch($hipX, $y($hipY), $hipEdge, 'نشانه باسن روی درز پهلو', 'hip'),
                $this->notch($crotchX, $y($crotchY), $thighEdge, 'نشانه خط فاق روی درز پهلو', 'thigh'),
                $this->notch(0, $y($crotchY), $inseamEdges[count($inseamEdges) - 1], 'پنجه فاق', 'fork'),
            ],
            'markers' => [
                $this->marker('hip', 'خط باسن', 0, $y($hipY), $hipX),
                $this->marker('crotch', 'خط فاق', 0, $y($crotchY), $crotchX),
                $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', $fork, $y(0), $fork, $y($hipY)),
                $this->marker('crease', 'خط اتوی پا', $crease, $y($hipY), $crease, $y($hemY)),
            ],
            'meta' => [
                'part' => $isFront ? 'front_leg' : 'back_leg',
                'edges' => $tags,
                'fold_edges' => [],
                'side' => $isFront ? 'front' : 'back',
                'waist_edges' => [0],
                'side_edges' => $sideEdges,
                'hem_edges' => [$hemEdge],
                'inseam_edges' => $inseamEdges,
                'crotch_edges' => [$count - 3, $count - 2],
                'center_edges' => [$count - 1],
                'seam_group' => 'pants',
                'waist_target' => round((float) $mx['waist_target'], 3),
                'waist_finished' => round($waistEdge - $intake - $gather, 2),
                'waist_fabric' => round($waistEdge, 2),
                'hip_target' => round((float) $mx['hip_target'], 3),
                'waist_ease' => round((float) $mx['waist_ease'], 2),
                'hip_ease' => round((float) $mx['hip_ease'], 2),
                'hip_y' => round($y($hipY), 2),
                'crotch_depth' => round($crotchY, 2),
                'body_rise' => (float) $mx['body_rise'],
                'waist_drop' => (float) $mx['waist_drop'],
                'crotch_drop' => (float) $mx['crotch_drop'],
                'rise' => (string) $mx['rise_key'],
                'knee_y' => $hasKnee ? round($y($kneeY), 2) : null,
                'hem_y' => round($y($hemY), 2),
                'panel_width' => round($panel, 2),
                'knee_width' => $hasKnee ? round($kneeW, 2) : null,
                'hem_width' => round($hemW, 2),
                'thigh_finished' => (float) $mx['thigh_finished'],
                'leg_length' => round((float) $mx['leg_length'], 2),
                'crease_x' => round($crease, 2),
                'fullness' => [],
            ],
        ];

        $notes = array_merge((array) ($mx['waist_notes'] ?? []), (array) ($o['notes'] ?? []));

        if ($notes !== []) {
            $piece['meta']['notes'] = $notes;
        }

        if ($hasKnee) {
            $piece['notches'][] = $this->notch($kneeOut, $y($kneeY), $sideEdges[count($sideEdges) - 2], 'نشانه زانو روی درز پهلو', 'knee_out');
            $piece['notches'][] = $this->notch($kneeIn, $y($kneeY), $inseamEdges[0], 'نشانه زانو روی درز داخل پا', 'knee_in');
            $piece['markers'][] = $this->marker('knee', 'خط زانو', $kneeIn, $y($kneeY), $kneeOut);
        }

        if ($top > 0) {
            $piece['markers'][] = $this->marker('waist', 'خط کمر', $centerX, $top, $sideWaistX, $top + $drop);
            $piece['meta']['paperbag'] = round($top, 2);
        }

        $piece = $this->addWaistShaping($piece, $mx, $o, [
            'is_front' => $isFront,
            'center_x' => $centerX,
            'side_x' => $sideWaistX,
            'top_y' => 0.0,
            'side_y' => $top > 0 ? $drop : $y(0),
            'hip_y' => $y($hipY),
            'hem_y' => $y($hemY),
            'intake' => $intake,
            'gather' => $gather,
            'edge_length' => $waistEdge,
        ]);

        $piece = $this->addHemFullness($piece, $mx, $o, $hemEdge, $hemW);

        return $this->piece($piece);
    }

    /**
     * ساسون، پیلی یا چین کمر روی یک پای شلوار.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function addWaistShaping(array $piece, array $mx, array $o, array $at): array
    {
        $intake = (float) $at['intake'];
        $gather = (float) $at['gather'];
        $isFront = (bool) $at['is_front'];
        $mode = (string) $mx['waist_mode'];
        $fabric = (float) $at['edge_length'];

        $point = fn (float $t) => [
            'x' => $at['center_x'] + (($at['side_x'] - $at['center_x']) * $t),
            'y' => $at['top_y'] + (($at['side_y'] - $at['top_y']) * $t),
        ];

        if ($gather > 0.05) {
            $piece = FullnessRecorder::gathers($piece, 0, $gather, [
                'label' => $isFront ? 'چین کمر جلو' : 'چین کمر پشت',
                'source' => 'elastic',
            ]);
            $piece['meta']['fullness'][] = $this->pantsFullness('gather', 0, $fabric, $fabric - $gather, [
                'label' => 'چین کمر',
            ]);
        }

        if ($intake <= 0.05) {
            return $piece;
        }

        $count = max(1, (int) ($isFront
            ? ($o['pleat_count'] ?? $o['front_darts'] ?? 1)
            : ($o['dart_count'] ?? 2)));
        $each = $intake / $count;

        if ($isFront && $mode === 'pleat') {
            $style = (string) ($o['pleat_style'] ?? 'knife');
            $depth = min($at['hip_y'] + 4, $at['hem_y'] * 0.35);

            for ($i = 0; $i < $count; $i++) {
                $spot = $point(($i + 1) / ($count + 1));
                $piece['pleats'][] = [
                    'label' => 'پیلی جلو '.$this->fa($i + 1),
                    'style' => $style,
                    'depth' => round($each, 2),
                    'fold_depth' => round($each / ($style === 'box' ? 4 : 2), 2),
                    'intake' => round($each, 2),
                    'from' => Geometry::point($spot['x'], $spot['y']),
                    'to' => Geometry::point($spot['x'], $depth),
                ];
                $piece['notches'][] = $this->notch($spot['x'], $spot['y'], 0, 'خط پیلی جلو', 'pleat');
            }

            $piece = FullnessRecorder::pleats($piece, 0, $intake, [
                'count' => $count,
                'type' => $style,
                'label' => 'پیلی کمر جلو',
            ]);
            $piece['meta']['fullness'][] = $this->pantsFullness('pleat', 0, $fabric, $fabric - $intake, [
                'label' => 'پیلی کمر جلو',
                'count' => $count,
                'style' => $style,
            ]);

            return $piece;
        }

        // ساسون پشت بلندتر از جلوست چون باید تا برجستگی باسن برسد
        $apex = $at['hip_y'] * ($isFront ? 0.5 : 0.68);

        for ($i = 0; $i < $count; $i++) {
            $spot = $point(($i + 1) / ($count + 1));
            $piece['darts'][] = $this->dart(
                'waist',
                $count > 1 ? 'ساسون کمر '.$this->fa($i + 1) : 'ساسون کمر',
                0,
                $spot['x'],
                $spot['y'],
                $each,
                $spot['x'],
                max($spot['y'] + 4, $apex - ($i * 1.5)),
            );
        }

        return $piece;
    }

    /**
     * جمع شدن دم پا در مچ یا نوار کش (جاگر، هارمی).
     *
     * $o['hem_gather'] دور تمام‌شده دم پاست؛ سهم هر پنل به نسبت پهنای خودش
     * برداشته می‌شود تا جلو و پشت با هم جور دربیایند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function addHemFullness(array $piece, array $mx, array $o, int $hemEdge, float $hemWidth): array
    {
        $finished = (float) ($o['hem_gather'] ?? 0);
        $total = (float) $mx['hem_total'];

        if ($finished <= 0.05 || $total <= 0.05 || $finished >= $total) {
            return $piece;
        }

        $amount = min($hemWidth - 4, ($total - $finished) * ($hemWidth / $total));

        if ($amount <= 0.05) {
            return $piece;
        }

        $piece = FullnessRecorder::gathers($piece, $hemEdge, $amount, [
            'label' => 'چین دم پا در مچ',
            'source' => 'cuff',
        ]);
        $piece['meta']['fullness'][] = $this->pantsFullness('gather', $hemEdge, $hemWidth, $hemWidth - $amount, [
            'label' => 'چین دم پا',
        ]);
        $piece['meta']['hem_finished'] = round($finished, 2);

        return $piece;
    }

    /**
     * پای جلو و پای پشت با هم.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function legPieces(array $mx, array $o = []): array
    {
        return [
            $this->legPanel($mx, array_merge($o, ['side' => 'front'])),
            $this->legPanel($mx, array_merge($o, ['side' => 'back'])),
        ];
    }

    /**
     * کمربند شلوار به اندازه دور کمر همین درفت.
     *
     * @return array<string, mixed>
     */
    protected function pantsBand(array $mx, array $params, array $o = []): array
    {
        $height = max(1.5, (float) ($o['height'] ?? $this->param($params, 'waistband_height', 4)));
        $girth = (float) ($o['girth'] ?? $mx['waist_target']);
        $overlap = (float) ($o['overlap'] ?? 3.5);
        $half = max(6.0, ($girth / 2) + $overlap);

        return $this->piece([
            'code' => $o['code'] ?? 'waistband',
            'name' => $o['name'] ?? 'کمربند شلوار',
            'cut_quantity' => (int) ($o['cut_quantity'] ?? 2),
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($half, 0),
                Geometry::point($half, $height * 2),
                Geometry::point(0, $height * 2),
            ],
            'grainline' => $this->grainline($half * 0.5, 1, ($height * 2) - 1),
            'markers' => [$this->marker('fold', 'خط تای کمربند', 0, $height, $half)],
            'meta' => [
                'part' => $o['part'] ?? 'waistband',
                'edges' => ['waist', 'side', 'waist', 'side'],
                'fold_edges' => [],
                'interfacing' => (bool) ($o['interfacing'] ?? true),
                'band_girth' => round($girth, 2),
                'band_overlap' => round($overlap, 2),
                'finished_height' => round($height, 2),
                'fullness' => [],
                'notes' => $o['notes'] ?? [],
            ],
        ]);
    }

    /**
     * نوار کش (کمر یا مچ پا).
     *
     * نوار کوتاه‌تر از دور تمام‌شده بریده می‌شود و موقع دوخت کشیده می‌شود؛ همین
     * اختلاف است که لباس را سر جایش نگه می‌دارد.
     *
     * @return array<string, mixed>
     */
    protected function elasticBand(float $girth, array $o = []): array
    {
        $stretch = min(0.98, max(0.55, (float) ($o['stretch'] ?? 0.85)));
        $height = max(1.5, (float) ($o['height'] ?? 4));
        $cut = $girth * $stretch;
        $half = max(5.0, ($cut / 2) + 1.5);

        return $this->piece([
            'code' => $o['code'] ?? 'elastic-band',
            'name' => $o['name'] ?? 'نوار کش کمر',
            'cut_quantity' => (int) ($o['cut_quantity'] ?? 1),
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($half, 0),
                Geometry::point($half, $height * 2),
                Geometry::point(0, $height * 2),
            ],
            'grainline' => $this->grainline($half * 0.5, 1, ($height * 2) - 1),
            'markers' => [$this->marker('fold', 'خط تای نوار', 0, $height, $half)],
            'meta' => [
                'part' => $o['part'] ?? 'band',
                'edges' => ['waist', 'side', 'waist', 'side'],
                'fold_edges' => [],
                'interfacing' => false,
                'band_girth' => round($cut, 2),
                'band_target' => round($girth, 2),
                'band_stretch' => $stretch,
                'finished_height' => round($height, 2),
                'fullness' => [],
                'notes' => [
                    'نوار '.$this->fa(round($cut, 1)).' سانتی‌متر بریده و روی '
                        .$this->fa(round($girth, 1)).' سانتی‌متر کشیده و دوخته می‌شود.',
                ],
                // طول کشی که باید خرید، همان طول بریده‌شده نوار است
                'notions' => [[
                    'type' => 'elastic',
                    'label' => 'کش '.$this->fa(round($height, 1)).' سانتی‌متری',
                    'count' => 1,
                    'length' => round($cut, 1),
                ]],
            ],
        ]);
    }

    /**
     * ثبت گشادی اضافه پارچه روی یک لبه (برای برگه فنی و نقشه برش).
     *
     * @return array<string, mixed>
     */
    protected function pantsFullness(string $type, int $edge, float $fabric, float $finished, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'edge' => $edge,
            'fabric' => round($fabric, 2),
            'finished' => round($finished, 2),
            'takeup' => round($fabric - $finished, 2),
            'ratio' => $finished > 0.01 ? round($fabric / $finished, 3) : 0.0,
        ], $extra);
    }

    /** عدد فارسی برای یادداشت‌ها. */
    protected function fa(float|int|string $value): string
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }
}
