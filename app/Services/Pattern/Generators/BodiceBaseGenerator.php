<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه مشترک همه بلوک‌های بالاتنه کاتالوگ.
 *
 * تنها کارهای تکراری را برمی‌دارد: گروه مدل، پارامترهای مشترک و «مهر زدنِ دور
 * هدف» روی پنل‌های پوسته. دور هدف همان «دور بدن + آزادی» است و روی هر پنل پوسته
 * در meta.target_girth می‌نشیند تا آزمون بتواند بدون دانستن جزئیات مدل، جمع
 * meta.girth × meta.girth_factor را با آن بسنجد.
 *
 * قرارداد نقش پنل‌ها در meta.girth_role:
 *   shell        پوسته بالاتنه (در جمع دور سینه و کمر می‌آید)
 *   skirt        پایین‌تنه رو
 *   lining       آستر بالاتنه، lining_skirt آستر پایین‌تنه
 *   sleeve       آستین، trim یقه و سجاف و نوار و جیب
 */
abstract class BodiceBaseGenerator extends BaseGenerator
{
    use BodiceCatalogSupport;
    use BodiceStyleSupport;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'bodice';
    }

    /**
     * پارامتر بلندی تنه از خط کمر به پایین.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function bodyLengthParam(
        float $default = 0,
        float $min = 0,
        float $max = 40,
        string $label = 'بلندی از خط کمر',
        string $hint = 'صفر یعنی بلوک دقیقاً روی خط کمر تمام می‌شود.',
    ): array {
        return [
            'body_length' => [
                'label' => $label, 'min' => $min, 'max' => $max, 'step' => 1,
                'default' => $default, 'unit' => 'سانتی‌متر', 'hint' => $hint,
            ],
        ];
    }

    /**
     * پارامتر «فرم لباس» به زبان خیاط.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function fitParam(string $default = 'regular', string $label = 'فرم لباس'): array
    {
        return [
            'fit' => [
                'label' => $label, 'type' => 'select', 'default' => $default,
                'options' => ['fitted' => 'جذب', 'regular' => 'معمولی', 'loose' => 'گشاد'],
                'hint' => 'فرم گشادتر یعنی چند سانتی‌متر آزادی بیشتر روی سینه، کمر و باسن.',
            ],
        ];
    }

    /**
     * اضافه‌شدن به «یک‌چهارم» بدن بر پایه فرم انتخابی.
     *
     * خروجی روی دور کامل چهار برابر می‌شود؛ همین عدد در دور هدف هم حساب می‌شود
     * تا اندازه‌گیریِ آزمون با آنچه کاربر انتخاب کرده جور دربیاید.
     *
     * @param  array<string, float>  $map
     */
    protected function fitGrow(array $params, array $map = ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]): float
    {
        $fit = (string) $this->param($params, 'fit', 'regular');

        return (float) ($map[$fit] ?? ($map['regular'] ?? 0.0));
    }

    /**
     * شکل درز پهلو بر پایه فرم انتخابی.
     *
     * @param  array<string, string>  $map
     */
    protected function fitShape(array $params, array $map = ['fitted' => 'fitted', 'regular' => 'fitted', 'loose' => 'straight']): string
    {
        $fit = (string) $this->param($params, 'fit', 'regular');

        return (string) ($map[$fit] ?? ($map['regular'] ?? 'fitted'));
    }

    /**
     * ثبت دور هدف روی پنل‌های پوسته.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function stampTargets(array $pieces, array $g, float $grow = 0.0, array $roles = ['shell']): array
    {
        $target = [
            'bust' => round($g['bust'] + (4 * $grow), 2),
            'waist' => round($g['waist'] + (4 * $grow), 2),
            'hip' => round($g['hip'] + (4 * $grow), 2),
        ];

        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['girth_role'] ?? '', $roles, true)) {
                continue;
            }

            $pieces[$index]['meta']['target_girth'] = $target;
        }

        return $pieces;
    }

    /**
     * شماره‌گذاری نهایی قطعه‌ها به‌همراه مهر دور هدف.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function finishBlock(array $pieces, array $g, float $grow = 0.0, array $roles = ['shell']): array
    {
        return $this->finish($this->stampTargets(array_values(array_filter($pieces)), $g, $grow, $roles));
    }

    /** عدد فارسی برای یادداشت‌ها. */
    protected function fa(float|int|string $value): string
    {
        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫',
        ]);
    }
}
