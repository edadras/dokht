<?php

namespace App\Services\Assistant;

use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\Project;

/**
 * قرارداد مشترک پاسخ‌دهنده‌های دستیار.
 *
 * دو پیاده‌سازی داریم: «قواعد» (WorkshopAssistant) که کاملاً محلی و قطعی است و
 * همیشه پیش‌فرض و تکیه‌گاه می‌ماند، و «مدل زبانی» (ClaudeAssistant) که همان
 * داده‌ها را به یک مدل می‌دهد تا پاسخ روان‌تری بسازد و اگر نشد، به قواعد برمی‌گردد.
 *
 * خروجی هر دو یک شکل دارد و همیشه می‌گوید پاسخ از کجا آمده است (کلید source).
 */
interface AssistantDriver
{
    /**
     * پاسخ به یک پرسش کاربر.
     *
     * @return array{
     *     question: string, topic: string, topic_label: string, headline: string,
     *     points: array<int, string>, reasons: array<int, array{label: string, text: string}>,
     *     source: string, source_label: string
     * }
     */
    public function ask(
        string $question,
        ?Fabric $fabric = null,
        ?GarmentType $garmentType = null,
        ?Project $project = null,
    ): array;

    /** کلید کوتاه این پاسخ‌دهنده: rules یا claude. */
    public function driverName(): string;
}
