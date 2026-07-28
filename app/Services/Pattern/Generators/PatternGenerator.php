<?php

namespace App\Services\Pattern\Generators;

/**
 * قرارداد هر تولیدکننده الگو.
 *
 * خروجی generate() آرایه‌ای از «قطعه»هاست؛ هر قطعه دقیقاً همان کلیدهای جدول
 * pattern_pieces را دارد و آماده ذخیره است. مسیر قطعه (outline) با قرارداد
 * توضیح‌داده‌شده در App\Services\Pattern\Geometry ساخته می‌شود.
 */
interface PatternGenerator
{
    /**
     * ساخت قطعه‌های الگو از اندازه بدن، آزادی و پارامترها.
     *
     * @param  array<string, float>  $measurements  اندازه‌های کامل‌شده بدن (سانتی‌متر)
     * @param  array<string, float>  $ease  آزادی هر ناحیه؛ مقدار منفی هم مجاز است
     * @param  array<string, mixed>  $params  پارامترهای مدل
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $measurements, array $ease, array $params): array;

    /** مقادیر پیش‌فرض پارامترها. */
    public function defaultParams(): array;

    /**
     * توضیح پارامترها برای ساختن فرم: برچسب فارسی، کمینه، بیشینه و گام.
     *
     * @return array<string, array{label: string, type?: string, min?: float, max?: float, step?: float, default: mixed, unit?: string, hint?: string}>
     */
    public function paramsSchema(): array;

    /** نام فارسی این تولیدکننده برای نمایش. */
    public function label(): string;
}
