<?php

namespace App\Services\Pattern\Style\Sleeve;

/**
 * آستین رگلان.
 *
 * خط برش از خط یقه شروع می‌شود و به حلقه آستین می‌رسد؛ هر چه بالاتر روی خط یقه
 * شروع شود بند رگلان پهن‌تر و شانه نرم‌تر می‌شود. سرشانه و بالای حلقه از بالاتنه
 * جدا و به آستین بسته می‌شود، پس بالاتنه دیگر نه درز سرشانه دارد و نه حلقه آستین
 * کامل.
 *
 * سه حالت سر آستین در دسترس است: یک‌تکه ساده (نرم‌ترین)، یک‌تکه با ساسون سرشانه
 * (قالب‌ترین بدون درز اضافه) و دوتکه با درزی که از یقه تا مچ می‌رود.
 */
class RaglanSleeveStyle extends RaglanCutStyle
{
    public static function key(): string
    {
        return 'sleeve_raglan';
    }

    public function label(): string
    {
        return 'آستین رگلان';
    }

    public function description(): string
    {
        return 'خط برش از یقه تا زیر بغل؛ سرشانه به آستین می‌رود و شانه گرد و راحت می‌ایستد.';
    }

    public function paramsSchema(): array
    {
        return [
            'neck_start' => [
                'label' => 'شروع خط رگلان روی خط یقه', 'min' => 1.5, 'max' => 14, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر',
                'hint' => 'از سرگردن به سمت مرکز اندازه گرفته می‌شود؛ بیشتر یعنی بند رگلان پهن‌تر و یقه بازتر.',
            ],
            'armhole_join' => [
                'label' => 'باقی حلقه روی بالاتنه', 'min' => 3, 'max' => 20, 'step' => 0.5, 'default' => 8,
                'unit' => 'سانتی‌متر',
                'hint' => 'از زیر بغل به بالا؛ کمتر یعنی خط رگلان پایین‌تر به حلقه می‌رسد و بند باریک‌تر می‌شود.',
            ],
            'head' => $this->headField(),
        ] + $this->sharedFields();
    }

    protected function cutPlan(array $anchors, array $params): array
    {
        return [
            'neck' => (float) $params['neck_start'],
            'armhole' => min((float) $params['armhole_join'], $anchors['armhole_length'] * 0.75),
        ];
    }
}
