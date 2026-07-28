<?php

namespace App\Services\Assistant;

use Illuminate\Contracts\Container\Container;

/**
 * انتخاب پاسخ‌دهنده دستیار از روی تنظیمات.
 *
 * پیش‌فرض همیشه «قواعد» است. اگر درایور مدل زبانی خواسته شده باشد، خودِ آن درایور
 * نبود کلید یا خطای سرویس را می‌گیرد و پاسخ قاعده‌محور را با توضیح فارسی برمی‌گرداند؛
 * پس صفحه هرگز به‌خاطر تنظیمات ناقص از کار نمی‌افتد.
 */
class AssistantManager
{
    public function __construct(protected Container $container) {}

    public function driver(?string $name = null): AssistantDriver
    {
        $config = (array) config('services.assistant', []);
        $name ??= (string) ($config['driver'] ?? 'rules');

        if ($name !== 'claude') {
            return $this->container->make(WorkshopAssistant::class);
        }

        return new ClaudeAssistant($this->container->make(WorkshopAssistant::class), $config);
    }

    /** نام درایوری که همین حالا کار می‌کند. */
    public function driverName(): string
    {
        return $this->driver()->driverName();
    }
}
