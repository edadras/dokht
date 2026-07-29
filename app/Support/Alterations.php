<?php

namespace App\Support;

/**
 * واژگان اصلاح الگو پس از پرو.
 *
 * خیاط بعد از پرو روی تن مشتری با جمله حرف می‌زند، نه با عدد: «آستین دو سانتی
 * بلند است»، «کمر گشاد است»، «زیر کمر چین افقی افتاده». این کلاس همان جمله‌ها را
 * به چیزی ترجمه می‌کند که الگو می‌فهمد:
 *
 *   measurement → اندازه بدن عوض می‌شود (قد آستین، عرض سرشانه، قوز پشت…)
 *   ease        → آزادی همان ناحیه کم و زیاد می‌شود (سینه، کمر، باسن)
 *   param       → پارامتر درفت عوض می‌شود (گودی حلقه، عرض یقه، بلندی بالاتنه)
 *
 * علامت مقدار همیشه از دید خیاط است: مثبت یعنی «بیشترش کن». مثلاً «آستین کوتاه
 * است» با ‎+۲‎ ثبت می‌شود و دو سانتی‌متر به قد آستین می‌افزاید؛ «آستین بلند است»
 * با ‎−۲‎.
 *
 * هیچ اصلاحی مستقیم به هندسه دست نمی‌زند؛ همه از راه بازتولید الگو با ورودی
 * اصلاح‌شده اعمال می‌شوند، پس نسخه‌گیری و بازگشت‌پذیری خودبه‌خود سر جایش می‌ماند.
 */
class Alterations
{
    /**
     * اصلاح‌های شناخته‌شده.
     *
     * target: measurement | ease | param — key: کلید همان هدف
     * min/max: بازه پذیرفتنی یک اصلاح (سانتی‌متر)
     * sign: ضریب علامت؛ ‎-۱‎ یعنی «بیشتر کردن» در آن هدف با عدد منفی انجام می‌شود
     */
    public const CATALOGUE = [
        'sleeve_length' => [
            'label' => 'قد آستین',
            'symptom' => 'آستین کوتاه یا بلند است',
            'target' => 'measurement', 'key' => 'arm_length',
            'min' => -15, 'max' => 15, 'sign' => 1,
        ],
        'bodice_length' => [
            'label' => 'بلندی بالاتنه',
            'symptom' => 'خط کمر لباس بالاتر یا پایین‌تر از کمر مشتری می‌افتد',
            'target' => 'param', 'key' => 'bodice_length_extra',
            'min' => -8, 'max' => 12, 'sign' => 1,
        ],
        'shoulder_width' => [
            'label' => 'عرض سرشانه',
            'symptom' => 'سرشانه از نوک شانه بیرون زده یا تو رفته',
            'target' => 'measurement', 'key' => 'shoulder_width',
            'min' => -6, 'max' => 6, 'sign' => 1,
        ],
        'bust_ease' => [
            'label' => 'آزادی دور سینه',
            'symptom' => 'روی سینه تنگ است یا زیادی گشاد',
            'target' => 'ease', 'key' => 'bust',
            'min' => -10, 'max' => 20, 'sign' => 1,
        ],
        'waist_ease' => [
            'label' => 'آزادی دور کمر',
            'symptom' => 'کمر تنگ است یا زیادی گشاد',
            'target' => 'ease', 'key' => 'waist',
            'min' => -10, 'max' => 25, 'sign' => 1,
        ],
        'hip_ease' => [
            'label' => 'آزادی دور باسن',
            'symptom' => 'روی باسن تنگ است یا زیادی گشاد',
            'target' => 'ease', 'key' => 'hip',
            'min' => -10, 'max' => 20, 'sign' => 1,
        ],
        'armhole_depth' => [
            'label' => 'گودی حلقه آستین',
            'symptom' => 'زیر بغل می‌زند یا حلقه زیادی گود است',
            'target' => 'param', 'key' => 'armhole_depth_extra',
            'min' => -4, 'max' => 8, 'sign' => 1,
        ],
        'neck_width' => [
            'label' => 'عرض یقه',
            'symptom' => 'یقه به گردن می‌چسبد یا از شانه می‌افتد',
            'target' => 'param', 'key' => 'neck_width_extra',
            'min' => -3, 'max' => 8, 'sign' => 1,
        ],
        'shoulder_slope' => [
            'label' => 'شیب سرشانه',
            'symptom' => 'زیر سرشانه چین مورب افتاده (شانه افتاده یا صاف)',
            'target' => 'param', 'key' => 'shoulder_slope',
            'min' => -3, 'max' => 3, 'sign' => 1,
        ],
        'back_curve' => [
            'label' => 'قوز پشت',
            'symptom' => 'یقه پشت از گردن فاصله می‌گیرد و پشت کوتاه می‌آید',
            'target' => 'measurement', 'key' => 'back_curve',
            'min' => 0, 'max' => 6, 'sign' => 1,
        ],
        'sway_back' => [
            'label' => 'گودی کمر',
            'symptom' => 'زیر کمر پشت، چین افقی افتاده',
            'target' => 'measurement', 'key' => 'sway_back',
            'min' => 0, 'max' => 6, 'sign' => 1,
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return static::CATALOGUE;
    }

    public static function label(string $key): string
    {
        return static::CATALOGUE[$key]['label'] ?? $key;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, static::CATALOGUE);
    }

    /** مقدار یک اصلاح، بریده‌شده در بازه خودش. */
    public static function clamp(string $key, float $value): float
    {
        $row = static::CATALOGUE[$key] ?? null;

        if ($row === null) {
            return 0.0;
        }

        return round(max((float) $row['min'], min((float) $row['max'], $value)), 2);
    }

    /**
     * تبدیل فهرست اصلاح‌ها به ورودی تازه الگو.
     *
     * ورودی: [['key' => 'sleeve_length', 'value' => -2], …]
     * خروجی: ['measurements' => [...], 'ease' => [...], 'params' => [...]]
     *
     * اندازه‌ها و آزادی و پارامترهای فعلی الگو مبنا هستند و اصلاح روی آن‌ها
     * نشانده می‌شود، پس دو بار پرو پشت سر هم روی هم جمع می‌شوند — همان‌طور که
     * خیاط دو بار پیاپی الگو را اصلاح می‌کند.
     *
     * @param  array<int, array{key: string, value: float|int|string}>  $adjustments
     * @param  array<string, float>  $measurements
     * @param  array<string, float>  $ease
     * @param  array<string, mixed>  $params
     * @return array{measurements: array<string, float>, ease: array<string, float>, params: array<string, mixed>, applied: array<int, array<string, mixed>>}
     */
    public static function apply(array $adjustments, array $measurements, array $ease, array $params): array
    {
        $applied = [];

        foreach ($adjustments as $row) {
            $key = (string) ($row['key'] ?? '');
            $value = static::clamp($key, (float) ($row['value'] ?? 0));

            if (! static::has($key) || abs($value) < 0.01) {
                continue;
            }

            $definition = static::CATALOGUE[$key];
            $target = $definition['key'];
            $delta = $value * (float) ($definition['sign'] ?? 1);

            $before = match ($definition['target']) {
                'measurement' => (float) ($measurements[$target] ?? 0),
                'ease' => (float) ($ease[$target] ?? 0),
                default => (float) ($params[$target] ?? 0),
            };

            $after = round($before + $delta, 2);

            match ($definition['target']) {
                'measurement' => $measurements[$target] = $after,
                'ease' => $ease[$target] = $after,
                default => $params[$target] = $after,
            };

            $applied[] = [
                'key' => $key,
                'label' => $definition['label'],
                'value' => $value,
                'target' => $definition['target'],
                'target_key' => $target,
                'from' => $before,
                'to' => $after,
            ];
        }

        // اندازه بدن پس از اصلاح هم باید در بازه خودش بماند
        $measurements = Measurements::clean($measurements);

        return [
            'measurements' => $measurements,
            'ease' => $ease,
            'params' => $params,
            'applied' => $applied,
        ];
    }

    /**
     * پیشنهاد اصلاح از روی گزارش تست تناسب.
     *
     * تست تناسب می‌گوید کدام ناحیه تنگ یا گشاد است؛ همان را به یک اصلاح آماده
     * تبدیل می‌کنیم تا خیاط فقط تأیید کند.
     *
     * @param  array<string, mixed>  $fitReport  خروجی FitAnalysisService::analyze()
     * @return array<int, array{key: string, value: float, note: string}>
     */
    public static function suggestFromFit(array $fitReport): array
    {
        $map = ['bust' => 'bust_ease', 'waist' => 'waist_ease', 'hip' => 'hip_ease', 'armhole' => 'armhole_depth'];
        $out = [];

        foreach ($fitReport['zones'] ?? [] as $zone) {
            $key = $map[$zone['key'] ?? ''] ?? null;

            if ($key === null || ! in_array($zone['level'] ?? '', ['tight', 'snug', 'loose'], true)) {
                continue;
            }

            // تنگ و چسبان → بازتر؛ گشاد → جمع‌تر. نصف اختلاف را پیشنهاد می‌کنیم
            // تا اصلاح یک‌باره از آن سر بام نیفتد.
            $value = match ($zone['level']) {
                'tight' => max(1.0, abs((float) $zone['ease_cm']) + 1),
                'snug' => 1.5,
                default => -round(((float) $zone['ease_cm']) / 3, 1),
            };

            if (abs($value) < 0.5) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'value' => static::clamp($key, $value),
                'note' => $zone['label'].': '.($zone['note'] ?? ''),
            ];
        }

        return $out;
    }
}
