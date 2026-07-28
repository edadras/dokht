<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * شناسنامه فیزیکی پارچه.
 *
 * این کلاس تنها منبع حقیقت برای کلیدهای مشخصات پارچه است. هم بانک پایه پارچه
 * (fabric_types.profile) و هم پارچه‌های کارگاه (fabrics.profile_overrides) از
 * همین کلیدها استفاده می‌کنند تا موتور قواعد، شبیه‌سازی و برش با یک ساختار کار کنند.
 */
class FabricProfile implements Arrayable
{
    /**
     * کلیدها با مقدار پیش‌فرض، واحد و برچسب فارسی.
     *
     * type: ratio (۰ تا ۱) | percent | number | bool | option
     */
    public const SCHEMA = [
        // وزن و ضخامت
        'weight_gsm' => ['label' => 'وزن', 'unit' => 'گرم بر متر مربع', 'type' => 'number', 'default' => 150, 'min' => 20, 'max' => 900, 'group' => 'body'],
        'thickness_mm' => ['label' => 'ضخامت', 'unit' => 'میلی‌متر', 'type' => 'number', 'default' => 0.5, 'min' => 0.05, 'max' => 8, 'group' => 'body'],

        // رفتار پارچه
        'drape' => ['label' => 'لختی', 'unit' => '', 'type' => 'ratio', 'default' => 0.5, 'group' => 'behavior', 'hint' => 'هرچه بیشتر، پارچه ریزش و افتادگی بیشتری دارد'],
        'stiffness' => ['label' => 'سفتی', 'unit' => '', 'type' => 'ratio', 'default' => 0.4, 'group' => 'behavior', 'hint' => '۰ بسیار نرم، ۱ بسیار سفت'],

        // کشسانی در سه جهت
        'stretch_warp' => ['label' => 'کشسانی طول (تار)', 'unit' => '٪', 'type' => 'percent', 'default' => 0, 'min' => 0, 'max' => 150, 'group' => 'stretch'],
        'stretch_weft' => ['label' => 'کشسانی عرض (پود)', 'unit' => '٪', 'type' => 'percent', 'default' => 0, 'min' => 0, 'max' => 200, 'group' => 'stretch'],
        'stretch_bias' => ['label' => 'کشسانی اریب', 'unit' => '٪', 'type' => 'percent', 'default' => 15, 'min' => 0, 'max' => 200, 'group' => 'stretch'],
        'recovery' => ['label' => 'برگشت‌پذیری', 'unit' => '٪', 'type' => 'percent', 'default' => 85, 'min' => 0, 'max' => 100, 'group' => 'stretch'],

        // ظاهر
        'transparency' => ['label' => 'شفافیت', 'unit' => '', 'type' => 'ratio', 'default' => 0.05, 'group' => 'look'],
        'sheen' => ['label' => 'براقی', 'unit' => '', 'type' => 'ratio', 'default' => 0.15, 'group' => 'look'],
        'roughness' => ['label' => 'زبری سطح', 'unit' => '', 'type' => 'ratio', 'default' => 0.5, 'group' => 'look'],

        // کاربری
        'shrinkage' => ['label' => 'آب‌رفت', 'unit' => '٪', 'type' => 'percent', 'default' => 2, 'min' => 0, 'max' => 20, 'group' => 'care'],
        'breathability' => ['label' => 'تنفس‌پذیری', 'unit' => '', 'type' => 'ratio', 'default' => 0.6, 'group' => 'care'],
        'warmth' => ['label' => 'گرمی', 'unit' => '', 'type' => 'ratio', 'default' => 0.4, 'group' => 'care'],
        'moisture_absorption' => ['label' => 'جذب رطوبت', 'unit' => '', 'type' => 'ratio', 'default' => 0.5, 'group' => 'care'],
        'wrinkle_resistance' => ['label' => 'مقاومت به چین‌خوردگی', 'unit' => '', 'type' => 'ratio', 'default' => 0.5, 'group' => 'care'],

        // رفتار در برش و دوخت
        'fraying' => ['label' => 'ریش‌شدن لبه', 'unit' => '', 'type' => 'ratio', 'default' => 0.3, 'group' => 'cutting'],
        'curling' => ['label' => 'لوله‌شدن لبه', 'unit' => '', 'type' => 'ratio', 'default' => 0.1, 'group' => 'cutting'],
        'slippage' => ['label' => 'سرشدن زیر قیچی', 'unit' => '', 'type' => 'ratio', 'default' => 0.2, 'group' => 'cutting'],
        'distortion' => ['label' => 'تغییر شکل هنگام برش', 'unit' => '', 'type' => 'ratio', 'default' => 0.2, 'group' => 'cutting'],
        'heat_sensitivity' => ['label' => 'حساسیت به حرارت', 'unit' => '', 'type' => 'ratio', 'default' => 0.3, 'group' => 'cutting'],
        'steam_sensitivity' => ['label' => 'حساسیت به بخار', 'unit' => '', 'type' => 'ratio', 'default' => 0.3, 'group' => 'cutting'],

        // جهت‌داری
        'has_nap' => ['label' => 'خواب‌دار', 'unit' => '', 'type' => 'bool', 'default' => false, 'group' => 'direction', 'hint' => 'مثل مخمل و جیر؛ همه قطعات باید یک‌جهت بریده شوند'],
        'directional' => ['label' => 'طرح جهت‌دار', 'unit' => '', 'type' => 'bool', 'default' => false, 'group' => 'direction'],

        'pressing' => ['label' => 'نیاز به اتو', 'unit' => '', 'type' => 'option', 'default' => 'medium', 'options' => ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'], 'group' => 'cutting'],
    ];

    public const GROUPS = [
        'body' => 'وزن و ضخامت',
        'behavior' => 'رفتار پارچه',
        'stretch' => 'کشسانی',
        'look' => 'ظاهر',
        'care' => 'نگهداری و راحتی',
        'cutting' => 'برش و دوخت',
        'direction' => 'جهت پارچه',
    ];

    /** @var array<string, mixed> */
    protected array $values;

    public function __construct(array $values = [])
    {
        $this->values = static::normalize($values);
    }

    public static function make(array $values = []): static
    {
        return new static($values);
    }

    /** مقادیر پیش‌فرض کامل. */
    public static function defaults(): array
    {
        return collect(static::SCHEMA)->map(fn ($def) => $def['default'])->all();
    }

    /** فقط کلیدهای معتبر را نگه می‌دارد و مقادیر را به نوع درست تبدیل می‌کند. */
    public static function normalize(array $values): array
    {
        $out = static::defaults();

        foreach ($values as $key => $value) {
            if (! isset(static::SCHEMA[$key]) || $value === null || $value === '') {
                continue;
            }

            $def = static::SCHEMA[$key];

            $out[$key] = match ($def['type']) {
                'bool' => (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'ratio' => (float) max(0, min(1, (float) $value)),
                'percent', 'number' => (float) max($def['min'] ?? 0, min($def['max'] ?? 1000, (float) $value)),
                'option' => isset($def['options'][$value]) ? $value : $def['default'],
                default => $value,
            };
        }

        return $out;
    }

    /** ادغام شناسنامه پایه با بازنویسی‌های کارگاه. */
    public static function merge(?array $base, ?array $overrides): static
    {
        return new static(array_merge($base ?? [], array_filter(
            $overrides ?? [],
            fn ($v) => $v !== null && $v !== ''
        )));
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->values[$key] ?? $fallback ?? static::SCHEMA[$key]['default'] ?? null;
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function toArray(): array
    {
        return $this->values;
    }

    /** بیشترین کشسانی در هر جهت. */
    public function maxStretch(): float
    {
        return (float) max($this->get('stretch_warp'), $this->get('stretch_weft'));
    }

    public function isStretchy(): bool
    {
        return $this->maxStretch() >= 15;
    }

    public function isSheer(): bool
    {
        return $this->get('transparency') >= 0.5;
    }

    /** توضیح فارسی درجه لختی. */
    public function drapeLabel(): string
    {
        return match (true) {
            $this->get('drape') >= 0.8 => 'خیلی لخت',
            $this->get('drape') >= 0.6 => 'لخت',
            $this->get('drape') >= 0.4 => 'متوسط',
            $this->get('drape') >= 0.2 => 'کم‌لختی',
            default => 'بسیار سفت و ایستا',
        };
    }

    public function weightLabel(): string
    {
        return match (true) {
            $this->get('weight_gsm') >= 350 => 'بسیار سنگین',
            $this->get('weight_gsm') >= 250 => 'سنگین',
            $this->get('weight_gsm') >= 160 => 'متوسط',
            $this->get('weight_gsm') >= 100 => 'سبک',
            default => 'بسیار سبک',
        };
    }

    public function thicknessLabel(): string
    {
        return match (true) {
            $this->get('thickness_mm') >= 2.0 => 'بسیار ضخیم',
            $this->get('thickness_mm') >= 1.0 => 'ضخیم',
            $this->get('thickness_mm') >= 0.45 => 'متوسط',
            $this->get('thickness_mm') >= 0.25 => 'نازک',
            default => 'بسیار نازک',
        };
    }

    /** سختی کار با پارچه: ۰ آسان تا ۱ دشوار. */
    public function difficulty(): float
    {
        return round(min(1, (
            $this->get('slippage') * 1.2
            + $this->get('fraying') * 0.8
            + $this->get('distortion') * 0.9
            + $this->get('transparency') * 0.5
            + ($this->maxStretch() / 100) * 0.6
        ) / 3.4), 2);
    }

    /**
     * پارامترهای ورودی موتور شبیه‌سازی پارچه.
     *
     * مشخصات انسانی پارچه به پارامترهای فیزیکی حل‌کننده تبدیل می‌شود؛ همان‌طور که
     * در معماری پیشنهادی آمده، بانک پارچه از موتور شبیه‌سازی جدا نگه داشته می‌شود.
     */
    public function physics(): array
    {
        $gsm = (float) $this->get('weight_gsm');
        $stiffness = (float) $this->get('stiffness');

        return [
            'weight' => round($gsm / 1000, 4),                                  // کیلوگرم بر متر مربع
            'thickness' => round((float) $this->get('thickness_mm'), 3),
            'stretch_warp' => round($this->get('stretch_warp') / 100, 4),
            'stretch_weft' => round($this->get('stretch_weft') / 100, 4),
            'stretch_bias' => round($this->get('stretch_bias') / 100, 4),
            'bending' => round(0.02 + $stiffness * 0.28, 4),
            'shear' => round(0.05 + $stiffness * 0.35, 4),
            'damping' => round(0.04 + (1 - (float) $this->get('drape')) * 0.12, 4),
            'friction' => round(0.2 + (float) $this->get('roughness') * 0.5, 4),
            'recovery' => round($this->get('recovery') / 100, 4),
            'transparency' => round((float) $this->get('transparency'), 3),
            'sheen' => round((float) $this->get('sheen'), 3),
            'roughness' => round((float) $this->get('roughness'), 3),
        ];
    }
}
