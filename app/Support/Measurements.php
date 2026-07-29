<?php

namespace App\Support;

/**
 * تعریف اندازه‌های بدن.
 *
 * برای ساده ماندن کار کاربر، اندازه‌ها به دو دسته تقسیم شده‌اند: «ضروری» که در
 * فرم اصلی دیده می‌شود و «تکمیلی» که پشت یک بخش بازشو پنهان است و در صورت خالی
 * بودن از روی اندازه‌های ضروری تخمین زده می‌شود.
 */
class Measurements
{
    public const FIELDS = [
        // ضروری — همان چند عددی که هر خیاطی همیشه می‌گیرد
        'height' => ['label' => 'قد', 'essential' => true, 'min' => 80, 'max' => 220, 'default' => 165],
        'bust' => ['label' => 'دور سینه', 'essential' => true, 'min' => 50, 'max' => 180, 'default' => 92],
        'waist' => ['label' => 'دور کمر', 'essential' => true, 'min' => 40, 'max' => 180, 'default' => 74],
        'hip' => ['label' => 'دور باسن', 'essential' => true, 'min' => 50, 'max' => 200, 'default' => 98],
        'shoulder_width' => ['label' => 'عرض سرشانه', 'essential' => true, 'min' => 20, 'max' => 70, 'default' => 39],
        'arm_length' => ['label' => 'قد آستین', 'essential' => true, 'min' => 20, 'max' => 90, 'default' => 58],

        // تکمیلی
        'neck' => ['label' => 'دور گردن', 'min' => 20, 'max' => 60, 'derive' => 'bust*0.40'],
        'under_bust' => ['label' => 'زیر سینه', 'min' => 40, 'max' => 160, 'derive' => 'bust-14'],
        'high_hip' => ['label' => 'باسن کوچک', 'min' => 45, 'max' => 190, 'derive' => 'hip-8'],
        'armhole' => ['label' => 'دور حلقه آستین', 'min' => 25, 'max' => 80, 'derive' => 'bust*0.46'],
        'bicep' => ['label' => 'دور بازو', 'min' => 15, 'max' => 70, 'derive' => 'bust*0.31'],
        'elbow' => ['label' => 'دور آرنج', 'min' => 15, 'max' => 60, 'derive' => 'bust*0.28'],
        'wrist' => ['label' => 'دور مچ', 'min' => 10, 'max' => 40, 'derive' => 'bust*0.18'],
        'back_length' => ['label' => 'قد بالاتنه پشت', 'min' => 25, 'max' => 60, 'derive' => 'height*0.245'],
        'front_length' => ['label' => 'قد بالاتنه جلو', 'min' => 25, 'max' => 65, 'derive' => 'height*0.255'],
        'shoulder_to_bust' => ['label' => 'سرشانه تا سینه', 'min' => 15, 'max' => 45, 'derive' => 'height*0.155'],
        'waist_to_hip' => ['label' => 'کمر تا باسن', 'min' => 12, 'max' => 35, 'derive' => 'height*0.125'],
        // اصلاح پوسچر — پیش‌فرض صفر است، پس بدنِ راست هیچ تغییری نمی‌بیند و
        // کاربر ساده هرگز با این دو رو‌به‌رو نمی‌شود.
        'back_curve' => ['label' => 'قوز پشت', 'min' => 0, 'max' => 6, 'default' => 0, 'posture' => true,
            'hint' => 'اگر پشت مشتری گرد است، مرکز پشت باید همین‌قدر بلندتر بریده شود.'],
        'sway_back' => ['label' => 'گودی کمر', 'min' => 0, 'max' => 6, 'default' => 0, 'posture' => true,
            'hint' => 'اگر زیر کمر چین افقی می‌افتد، مرکز پشت باید همین‌قدر کوتاه‌تر شود.'],

        'inseam' => ['label' => 'قد داخل پا', 'min' => 40, 'max' => 110, 'derive' => 'height*0.45'],
        'outseam' => ['label' => 'قد بیرون پا', 'min' => 50, 'max' => 130, 'derive' => 'height*0.62'],
        'thigh' => ['label' => 'دور ران', 'min' => 30, 'max' => 110, 'derive' => 'hip*0.58'],
        'knee' => ['label' => 'دور زانو', 'min' => 20, 'max' => 80, 'derive' => 'hip*0.38'],
        'ankle' => ['label' => 'دور مچ پا', 'min' => 15, 'max' => 50, 'derive' => 'hip*0.24'],
    ];

    /** جدول سایز استاندارد بانوان برای سایزبندی الگو. */
    public const SIZE_TABLE = [
        '34' => ['bust' => 80, 'waist' => 62, 'hip' => 86, 'shoulder_width' => 36, 'height' => 165, 'arm_length' => 57],
        '36' => ['bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 37, 'height' => 165, 'arm_length' => 57.5],
        '38' => ['bust' => 88, 'waist' => 70, 'hip' => 94, 'shoulder_width' => 38, 'height' => 168, 'arm_length' => 58],
        '40' => ['bust' => 92, 'waist' => 74, 'hip' => 98, 'shoulder_width' => 39, 'height' => 168, 'arm_length' => 58.5],
        '42' => ['bust' => 96, 'waist' => 78, 'hip' => 102, 'shoulder_width' => 40, 'height' => 170, 'arm_length' => 59],
        '44' => ['bust' => 100, 'waist' => 83, 'hip' => 106, 'shoulder_width' => 41, 'height' => 170, 'arm_length' => 59.5],
        '46' => ['bust' => 104, 'waist' => 88, 'hip' => 110, 'shoulder_width' => 42, 'height' => 172, 'arm_length' => 60],
        '48' => ['bust' => 110, 'waist' => 94, 'hip' => 116, 'shoulder_width' => 43, 'height' => 172, 'arm_length' => 60.5],
    ];

    public static function sizes(): array
    {
        return array_keys(static::SIZE_TABLE);
    }

    /** @return array<string, array> */
    public static function essential(): array
    {
        return array_filter(static::FIELDS, fn ($f) => $f['essential'] ?? false);
    }

    /** @return array<string, array> */
    public static function optional(): array
    {
        return array_filter(static::FIELDS, fn ($f) => ! ($f['essential'] ?? false));
    }

    public static function label(string $key): string
    {
        return static::FIELDS[$key]['label'] ?? $key;
    }

    /** فقط کلیدهای شناخته‌شده با مقدار عددی. */
    public static function clean(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (! isset(static::FIELDS[$key]) || $value === null || $value === '') {
                continue;
            }

            $def = static::FIELDS[$key];
            $out[$key] = round(max($def['min'], min($def['max'], (float) $value)), 1);
        }

        return $out;
    }

    /**
     * اندازه‌های خالی را از روی اندازه‌های ضروری تخمین می‌زند.
     *
     * این کار باعث می‌شود کاربر مبتدی بتواند تنها با شش عدد یک الگوی کامل بسازد.
     */
    public static function complete(array $values): array
    {
        $values = static::clean($values);

        foreach (static::FIELDS as $key => $def) {
            if (isset($values[$key])) {
                continue;
            }

            if (isset($def['derive'])) {
                $derived = static::evaluateDerive($def['derive'], $values);

                if ($derived !== null) {
                    $values[$key] = round(max($def['min'], min($def['max'], $derived)), 1);

                    continue;
                }
            }

            $values[$key] = $def['default'] ?? round(($def['min'] + $def['max']) / 4, 1);
        }

        return $values;
    }

    /** فرمول‌های ساده «کلید*ضریب» یا «کلید-عدد» را حساب می‌کند. */
    protected static function evaluateDerive(string $formula, array $values): ?float
    {
        if (! preg_match('/^([a-z_]+)\s*([*+\-])\s*([0-9.]+)$/', $formula, $m)) {
            return null;
        }

        [$_, $key, $op, $operand] = $m;

        if (! isset($values[$key])) {
            return null;
        }

        $base = (float) $values[$key];
        $operand = (float) $operand;

        return match ($op) {
            '*' => $base * $operand,
            '+' => $base + $operand,
            '-' => $base - $operand,
        };
    }

    /** نزدیک‌ترین سایز استاندارد به اندازه‌های داده‌شده. */
    public static function guessSize(array $values): string
    {
        $bust = (float) ($values['bust'] ?? 0);

        if ($bust <= 0) {
            return '40';
        }

        $best = '40';
        $bestDiff = PHP_FLOAT_MAX;

        foreach (static::SIZE_TABLE as $size => $row) {
            $diff = abs($row['bust'] - $bust);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = (string) $size;
            }
        }

        return $best;
    }

    /** اندازه‌های پایه یک سایز استاندارد، کامل‌شده. */
    public static function fromSize(string $size): array
    {
        return static::complete(static::SIZE_TABLE[$size] ?? static::SIZE_TABLE['40']);
    }
}
