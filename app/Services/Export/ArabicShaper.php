<?php

namespace App\Services\Export;

/**
 * شکل‌دهی متن فارسی/عربی و مرتب‌سازی راست‌به‌چپ برای موتورهایی که خودشان
 * این کار را نمی‌کنند (نوشتن PDF با دست و کشیدن متن با GD).
 *
 * دو کار انجام می‌شود:
 *
 * ۱) شکل‌دهی وابسته به جایگاه: هر حرف بسته به این‌که به حرف پیش و پس از خود
 *    می‌چسبد یا نه، یکی از چهار شکل «تنها، آغازین، میانی، پایانی» را می‌گیرد و
 *    به کدنقطه معادلش در بلوک‌های «شکل‌های نمایشی عربی» (U+FB50…U+FDFF و
 *    U+FE70…U+FEFF) تبدیل می‌شود. ترکیب واجب «لام‌الف» هم به یک گلیف تبدیل
 *    می‌شود. حرکت‌ها (اعراب) شفاف‌اند و پیوند را نمی‌شکنند، ولی نیم‌فاصله
 *    (U+200C) پیوند را می‌شکند — که در فارسی بسیار پرکاربرد است.
 *
 * ۲) مرتب‌سازی دوجهته ساده‌شده بر پایه قاعده L2 استاندارد یونیکد: به هر نویسه
 *    یک «تراز» داده می‌شود (راست‌به‌چپ ⇒ ۱، لاتین و عدد ⇒ ۲، خنثی از همسایه‌ها
 *    ارث می‌برد)، سپس از بالاترین تراز به پایین، بازه‌های هم‌تراز وارونه می‌شوند.
 *    نتیجه: کل رشته وارونه می‌شود ولی تکه‌های لاتین و عددی سرجای خود می‌مانند.
 *    جفت‌های آینه‌ای (پرانتز، گیومه، کمانک) در بازه راست‌به‌چپ عوض می‌شوند.
 *
 * این پیاده‌سازی جای موتور کامل OpenType را نمی‌گیرد (GSUB و GPOS خوانده
 * نمی‌شوند)، ولی برای نام قطعه‌ها، برچسب‌ها و جمله‌های کوتاه فارسی درست است و
 * تنها به قلمی نیاز دارد که شکل‌های نمایشی را داشته باشد — قلم Vazirmatn دارد.
 */
class ArabicShaper
{
    /**
     * حرف ⇒ [تنها، پایانی، آغازین، میانی].
     *
     * حرف‌هایی که فقط دو شکل دارند «راست‌چسب»اند: به حرف پیش از خود می‌چسبند
     * ولی حرف بعدی به آن‌ها نمی‌چسبد.
     *
     * @var array<int, array<int, int>>
     */
    public const FORMS = [
        0x0621 => [0xFE80],                                     // ء
        0x0622 => [0xFE81, 0xFE82],                             // آ
        0x0623 => [0xFE83, 0xFE84],                             // أ
        0x0624 => [0xFE85, 0xFE86],                             // ؤ
        0x0625 => [0xFE87, 0xFE88],                             // إ
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],             // ئ
        0x0627 => [0xFE8D, 0xFE8E],                             // ا
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],             // ب
        0x0629 => [0xFE93, 0xFE94],                             // ة
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],             // ت
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],             // ث
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],             // ج
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],             // ح
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],             // خ
        0x062F => [0xFEA9, 0xFEAA],                             // د
        0x0630 => [0xFEAB, 0xFEAC],                             // ذ
        0x0631 => [0xFEAD, 0xFEAE],                             // ر
        0x0632 => [0xFEAF, 0xFEB0],                             // ز
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],             // س
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],             // ش
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],             // ص
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],             // ض
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],             // ط
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],             // ظ
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],             // ع
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],             // غ
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],             // ف
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],             // ق
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],             // ك عربی
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],             // ل
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],             // م
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],             // ن
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],             // ه
        0x0648 => [0xFEED, 0xFEEE],                             // و
        0x0649 => [0xFEEF, 0xFEF0],                             // ى
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],             // ي عربی
        0x0671 => [0xFB50, 0xFB51],                             // ٱ
        0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69],             // ٹ
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],             // پ
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],             // چ
        0x0688 => [0xFB88, 0xFB89],                             // ڈ
        0x0691 => [0xFB8C, 0xFB8D],                             // ڑ
        0x0698 => [0xFB8A, 0xFB8B],                             // ژ
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],             // ک فارسی
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],             // گ
        0x06BA => [0xFB9E, 0xFB9F],                             // ں
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],             // ھ
        0x06C0 => [0xFBA4, 0xFBA5],                             // ۀ
        0x06C1 => [0xFBA6, 0xFBA7, 0xFBA8, 0xFBA9],             // ہ
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],             // ی فارسی
        0x06D2 => [0xFBAE, 0xFBAF],                             // ے
    ];

    /**
     * ترکیب واجب لام + الف ⇒ [تنها، پایانی].
     *
     * @var array<int, array<int, int>>
     */
    public const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    /** جفت‌های آینه‌ای که در بازه راست‌به‌چپ عوض می‌شوند. */
    public const MIRRORED = [
        '(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{',
        '<' => '>', '>' => '<', '«' => '»', '»' => '«', '‹' => '›', '›' => '‹',
    ];

    /** حرف کشیده (ـ): شکل ندارد ولی از هر دو سو می‌چسبد. */
    public const TATWEEL = 0x0640;

    public const ZWNJ = 0x200C;

    public const ZWJ = 0x200D;

    /**
     * متن آماده کشیدن: کدنقطه‌ها به ترتیب دیداری (چپ به راست روی کاغذ).
     *
     * @return array<int, int>
     */
    public function visualCodepoints(string $text): array
    {
        return $this->reorder($this->shape($this->codepoints($text)));
    }

    /** همان خروجی، ولی به شکل رشته UTF-8 (برای imagettftext). */
    public function visual(string $text): string
    {
        return $this->toUtf8($this->visualCodepoints($text));
    }

    /** آیا متن اصلاً نویسه عربی/فارسی دارد؟ */
    public function hasRtl(string $text): bool
    {
        foreach ($this->codepoints($text) as $codepoint) {
            if ($this->isRtlLetter($codepoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * تبدیل رشته UTF-8 به آرایه کدنقطه.
     *
     * @return array<int, int>
     */
    public function codepoints(string $text): array
    {
        $result = [];
        $length = strlen($text);

        for ($i = 0; $i < $length;) {
            $byte = ord($text[$i]);

            if ($byte < 0x80) {
                $result[] = $byte;
                $i++;
            } elseif ($byte < 0xE0) {
                $result[] = (($byte & 0x1F) << 6) | (ord($text[$i + 1] ?? "\0") & 0x3F);
                $i += 2;
            } elseif ($byte < 0xF0) {
                $result[] = (($byte & 0x0F) << 12)
                    | ((ord($text[$i + 1] ?? "\0") & 0x3F) << 6)
                    | (ord($text[$i + 2] ?? "\0") & 0x3F);
                $i += 3;
            } else {
                $result[] = (($byte & 0x07) << 18)
                    | ((ord($text[$i + 1] ?? "\0") & 0x3F) << 12)
                    | ((ord($text[$i + 2] ?? "\0") & 0x3F) << 6)
                    | (ord($text[$i + 3] ?? "\0") & 0x3F);
                $i += 4;
            }
        }

        return $result;
    }

    /** آرایه کدنقطه ⇒ رشته UTF-8. */
    public function toUtf8(array $codepoints): string
    {
        $out = '';

        foreach ($codepoints as $codepoint) {
            if ($codepoint < 0x80) {
                $out .= chr($codepoint);
            } elseif ($codepoint < 0x800) {
                $out .= chr(0xC0 | ($codepoint >> 6)).chr(0x80 | ($codepoint & 0x3F));
            } elseif ($codepoint < 0x10000) {
                $out .= chr(0xE0 | ($codepoint >> 12))
                    .chr(0x80 | (($codepoint >> 6) & 0x3F))
                    .chr(0x80 | ($codepoint & 0x3F));
            } else {
                $out .= chr(0xF0 | ($codepoint >> 18))
                    .chr(0x80 | (($codepoint >> 12) & 0x3F))
                    .chr(0x80 | (($codepoint >> 6) & 0x3F))
                    .chr(0x80 | ($codepoint & 0x3F));
            }
        }

        return $out;
    }

    /**
     * شکل‌دهی وابسته به جایگاه (ترتیب منطقی حفظ می‌شود).
     *
     * @param  array<int, int>  $codepoints
     * @return array<int, int>
     */
    public function shape(array $codepoints): array
    {
        $codepoints = array_values($codepoints);
        $count = count($codepoints);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $current = $codepoints[$i];

            if ($current === static::ZWNJ || $current === static::ZWJ) {
                continue; // نویسه کنترلی؛ فقط روی پیوند اثر دارد و گلیف نمی‌خواهد
            }

            if ($this->isTransparent($current)) {
                $result[] = $current;

                continue;
            }

            $previousJoins = $this->joinsToPrevious($codepoints, $i);
            $nextJoins = $this->joinsToNext($codepoints, $i);

            // ترکیب واجب لام + الف
            if ($current === 0x0644) {
                $next = $this->nextLetter($codepoints, $i);

                if ($next !== null && isset(static::LAM_ALEF[$codepoints[$next]])) {
                    $ligature = static::LAM_ALEF[$codepoints[$next]];
                    $result[] = $previousJoins ? $ligature[1] : $ligature[0];
                    $i = $next; // الف مصرف شد

                    continue;
                }
            }

            $result[] = $this->formOf($current, $previousJoins, $nextJoins);
        }

        return $result;
    }

    /** شکل درست یک حرف با توجه به همسایه‌هایش. */
    protected function formOf(int $codepoint, bool $previousJoins, bool $nextJoins): int
    {
        if ($codepoint === static::TATWEEL) {
            return $codepoint;
        }

        $forms = static::FORMS[$codepoint] ?? null;

        if ($forms === null) {
            return $codepoint;
        }

        $dual = count($forms) === 4;

        return match (true) {
            $dual && $previousJoins && $nextJoins => $forms[3], // میانی
            $dual && ! $previousJoins && $nextJoins => $forms[2], // آغازین
            $previousJoins && isset($forms[1]) => $forms[1], // پایانی
            default => $forms[0], // تنها
        };
    }

    /** آیا حرف پیشین به این حرف می‌چسبد (یعنی خودش دوچسب یا آغازین‌پذیر است)؟ */
    protected function joinsToPrevious(array $codepoints, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $codepoint = $codepoints[$i];

            if ($this->isTransparent($codepoint)) {
                continue;
            }

            if ($codepoint === static::ZWNJ) {
                return false;
            }

            if ($codepoint === static::ZWJ) {
                return true;
            }

            return $this->isDualJoining($codepoint);
        }

        return false;
    }

    /** آیا این حرف به حرف بعدی می‌چسبد؟ */
    protected function joinsToNext(array $codepoints, int $index): bool
    {
        if (! $this->isDualJoining($codepoints[$index])) {
            return false;
        }

        $next = $this->nextLetter($codepoints, $index);

        if ($next === null) {
            return false;
        }

        $codepoint = $codepoints[$next];

        return $codepoint === static::ZWJ
            || $codepoint === static::TATWEEL
            || isset(static::FORMS[$codepoint]);
    }

    /** اندیس نویسه معنادار بعدی (با رد کردن حرکت‌ها). */
    protected function nextLetter(array $codepoints, int $index): ?int
    {
        $count = count($codepoints);

        for ($i = $index + 1; $i < $count; $i++) {
            if ($this->isTransparent($codepoints[$i])) {
                continue;
            }

            if ($codepoints[$i] === static::ZWNJ) {
                return null;
            }

            return $i;
        }

        return null;
    }

    /** حرفی که هم به پیش و هم به پس می‌چسبد. */
    protected function isDualJoining(int $codepoint): bool
    {
        if ($codepoint === static::TATWEEL) {
            return true;
        }

        return isset(static::FORMS[$codepoint]) && count(static::FORMS[$codepoint]) === 4;
    }

    /** حرکت‌ها و نشانه‌های ترکیبی: در پیوند شفاف‌اند. */
    protected function isTransparent(int $codepoint): bool
    {
        return ($codepoint >= 0x064B && $codepoint <= 0x065F)
            || $codepoint === 0x0670
            || ($codepoint >= 0x06D6 && $codepoint <= 0x06ED)
            || ($codepoint >= 0x0610 && $codepoint <= 0x061A)
            || $codepoint === 0x200E || $codepoint === 0x200F || $codepoint === 0x061C;
    }

    /**
     * مرتب‌سازی دیداری (قاعده L2 ساده‌شده با جهت پایه راست‌به‌چپ).
     *
     * @param  array<int, int>  $codepoints
     * @return array<int, int>
     */
    public function reorder(array $codepoints): array
    {
        $codepoints = array_values($codepoints);
        $count = count($codepoints);

        if ($count === 0) {
            return [];
        }

        $classes = array_map(fn (int $codepoint) => $this->directionClass($codepoint), $codepoints);
        $levels = [];

        foreach ($classes as $index => $class) {
            $levels[$index] = match ($class) {
                'L', 'N' => 2,
                'R' => 1,
                default => 1,
            };
        }

        // خنثی‌ها از همسایه‌های غیرخنثی ارث می‌برند؛ اگر هر دو سو یکی نبود، پایه (۱)
        foreach ($classes as $index => $class) {
            if ($class !== 'W') {
                continue;
            }

            $before = null;
            $after = null;

            for ($i = $index - 1; $i >= 0; $i--) {
                if ($classes[$i] !== 'W') {
                    $before = $classes[$i];

                    break;
                }
            }

            for ($i = $index + 1; $i < $count; $i++) {
                if ($classes[$i] !== 'W') {
                    $after = $classes[$i];

                    break;
                }
            }

            $levels[$index] = ($before !== null && $before === $after && $before !== 'R') ? 2 : 1;
        }

        $result = $codepoints;

        // بازه‌های تراز ۲ را وارونه کن، سپس کل رشته را (تراز ۱)
        $start = null;

        for ($i = 0; $i <= $count; $i++) {
            $isHigh = $i < $count && $levels[$i] >= 2;

            if ($isHigh && $start === null) {
                $start = $i;
            } elseif (! $isHigh && $start !== null) {
                $slice = array_reverse(array_slice($result, $start, $i - $start));
                array_splice($result, $start, $i - $start, $slice);
                $start = null;
            }
        }

        $levels = array_reverse($levels);
        $result = array_reverse($result);

        // آینه کردن جفت‌ها در بازه‌های راست‌به‌چپ
        foreach ($result as $index => $codepoint) {
            if (($levels[$index] ?? 1) !== 1) {
                continue;
            }

            $character = $this->toUtf8([$codepoint]);

            if (isset(static::MIRRORED[$character])) {
                $result[$index] = $this->codepoints(static::MIRRORED[$character])[0];
            }
        }

        return array_values($result);
    }

    /** رده جهتی: R راست‌به‌چپ، L لاتین، N عدد، W خنثی. */
    protected function directionClass(int $codepoint): string
    {
        if ($this->isDigit($codepoint)) {
            return 'N';
        }

        if ($this->isRtlLetter($codepoint)) {
            return 'R';
        }

        if (($codepoint >= 0x41 && $codepoint <= 0x5A)
            || ($codepoint >= 0x61 && $codepoint <= 0x7A)
            || ($codepoint >= 0xC0 && $codepoint <= 0x24F)) {
            return 'L';
        }

        return 'W';
    }

    protected function isDigit(int $codepoint): bool
    {
        return ($codepoint >= 0x30 && $codepoint <= 0x39)      // ۰-۹ لاتین
            || ($codepoint >= 0x0660 && $codepoint <= 0x0669)  // عربی
            || ($codepoint >= 0x06F0 && $codepoint <= 0x06F9); // فارسی
    }

    protected function isRtlLetter(int $codepoint): bool
    {
        if ($this->isDigit($codepoint)) {
            return false;
        }

        return ($codepoint >= 0x0590 && $codepoint <= 0x05FF)
            || ($codepoint >= 0x0600 && $codepoint <= 0x06FF)
            || ($codepoint >= 0x0750 && $codepoint <= 0x077F)
            || ($codepoint >= 0x08A0 && $codepoint <= 0x08FF)
            || ($codepoint >= 0xFB50 && $codepoint <= 0xFDFF)
            || ($codepoint >= 0xFE70 && $codepoint <= 0xFEFF);
    }
}
