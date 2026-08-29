<?php

namespace Tests\Unit;

/**
 * همان ممیزی کاتالوگ، ولی روی بدن‌های خیلی سخت‌تر.
 *
 * سامانه برای «هر مشتری با اندازهٔ خودش» الگو می‌سازد، پس هفت بدنِ ممیزیِ
 * همیشگی کافی نیست. این‌جا هشت بدنِ دیگر اضافه می‌شود که عمداً بیرونِ عادت
 * انتخاب شده‌اند: خردسالِ ۹۲ سانتی، بزرگسالِ ۱۵۰ سانتی‌متر دورِ سینه، اندامِ
 * سیبی که کمرش از سینه‌اش بزرگ‌تر است، گلابیِ با اختلاف ۳۴ سانتی سینه تا باسن،
 * و شانه‌پهنی که سرشانه‌اش از نسبتِ معمول بیرون است.
 *
 * موقتی نیست — همان‌قدر که ممیزی اصلی جای دارد، این هم دارد.
 */
class WideBodyAuditTest extends CatalogAuditTest
{
    protected const SIZES = [
        '36', '44', 'خردسال', 'ریزنقش', 'خیلی‌درشت',
        'ساعت‌شنی', 'سیبی', 'گلابی', 'شانه‌پهن', 'قدبلندِ درشت',
    ];

    protected const BESPOKE = [
        'خردسال' => ['height' => 92, 'bust' => 52, 'waist' => 50, 'hip' => 54, 'shoulder_width' => 22, 'arm_length' => 30],
        'ریزنقش' => ['height' => 145, 'bust' => 76, 'waist' => 60, 'hip' => 84, 'shoulder_width' => 33, 'arm_length' => 50],
        'خیلی‌درشت' => ['height' => 175, 'bust' => 150, 'waist' => 140, 'hip' => 155, 'shoulder_width' => 47, 'arm_length' => 62],
        'ساعت‌شنی' => ['height' => 165, 'bust' => 110, 'waist' => 62, 'hip' => 112, 'shoulder_width' => 36, 'arm_length' => 58],
        'سیبی' => ['height' => 160, 'bust' => 100, 'waist' => 110, 'hip' => 102, 'shoulder_width' => 38, 'arm_length' => 56],
        'گلابی' => ['height' => 162, 'bust' => 82, 'waist' => 70, 'hip' => 116, 'shoulder_width' => 35, 'arm_length' => 57],
        'شانه‌پهن' => ['height' => 172, 'bust' => 88, 'waist' => 72, 'hip' => 92, 'shoulder_width' => 48, 'arm_length' => 60],
        'قدبلندِ درشت' => ['height' => 190, 'bust' => 122, 'waist' => 112, 'hip' => 124, 'shoulder_width' => 48, 'arm_length' => 70],
    ];

    /** سنجهٔ سرعت یک بار در ممیزی اصلی بس است. */
    public function test_building_the_whole_catalogue_stays_fast(): void
    {
        $this->markTestSkipped('سنجهٔ سرعت در ممیزی اصلی انجام می‌شود.');
    }

    /** همتاهای تکراری به بدن ربطی ندارند؛ ممیزی اصلی می‌سنجدشان. */
    public function test_no_two_rows_of_one_family_are_the_same_pattern(): void
    {
        $this->markTestSkipped('یکتایی الگوها در ممیزی اصلی سنجیده می‌شود.');
    }
}
