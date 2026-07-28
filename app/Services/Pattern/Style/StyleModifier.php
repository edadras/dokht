<?php

namespace App\Services\Pattern\Style;

/**
 * قرارداد یک «سبک» که روی قطعه‌های آماده اعمال می‌شود.
 *
 * تفاوت سبک با تولیدکننده الگو این است که تولیدکننده قطعه می‌سازد، ولی سبک
 * قطعه‌های موجود را تغییر می‌دهد یا قطعه تازه‌ای به آن‌ها می‌افزاید: یقه هفت،
 * آستین پفی، جیب، چین دامن و مانند این‌ها. به همین دلیل سبک‌ها ترکیب‌پذیرند و
 * می‌توان چند سبک را پشت سر هم روی یک لباس اجرا کرد.
 *
 * کلاس‌های سبک با پویش پوشه‌های زیرِ Style شناسایی می‌شوند؛ برای افزودن سبک تازه
 * فقط کافی است یک کلاس تازه در همان پوشه گذاشته شود.
 */
interface StyleModifier
{
    /** کلید یکتا و پایدار؛ در پارامترهای الگو ذخیره می‌شود. */
    public static function key(): string;

    /** گروه سبک: neckline | collar | sleeve | hem | pocket | fullness | closure | detail */
    public static function group(): string;

    /** نام فارسی برای نمایش در فهرست انتخاب. */
    public function label(): string;

    /** توضیح کوتاه فارسی؛ در کارت انتخاب زیر نام می‌آید. */
    public function description(): string;

    /**
     * پارامترهای این سبک برای ساخت فرم.
     *
     * @return array<string, array{label: string, type?: string, min?: float, max?: float, step?: float, default: mixed, unit?: string, hint?: string, options?: array}>
     */
    public function paramsSchema(): array;

    /**
     * آیا این سبک روی این مجموعه قطعه قابل اجراست؟
     *
     * مثلاً یقه شنل روی دامن معنا ندارد. پیام برگشتی در رابط کاربری نشان داده می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return true|string درست، یا دلیل فارسی نپذیرفتن
     */
    public function supports(array $pieces, array $context): true|string;

    /**
     * اجرای سبک.
     *
     * @param  array<int, array<string, mixed>>  $pieces  قطعه‌های فعلی لباس
     * @param  array<string, mixed>  $context  کلیدها: measurements, ease, params, garment, notes
     * @return array{pieces: array<int, array<string, mixed>>, notes?: array<int, string>, meta?: array<string, mixed>}
     */
    public function apply(array $pieces, array $context): array;
}
