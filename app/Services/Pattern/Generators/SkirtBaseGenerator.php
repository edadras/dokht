<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه مشترک همه دامن‌های کاتالوگ.
 *
 * فقط کارهای تکراری را برمی‌دارد: گروه مدل، پارامترهای مشترک (قد، ساسون، کمربند)
 * و ساختن کمربند از روی همان دور کمری که پنل‌ها با آن درفت شده‌اند.
 */
abstract class SkirtBaseGenerator extends BaseGenerator
{
    use SkirtBlock;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'skirt';
    }

    /**
     * پارامتر قد دامن.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function lengthParam(float $default = 60, float $min = 25, float $max = 125): array
    {
        return [
            'length' => [
                'label' => 'بلندی دامن', 'min' => $min, 'max' => $max, 'step' => 1,
                'default' => $default, 'unit' => 'سانتی‌متر',
                'hint' => 'از خط کمر تا دم دامن، روی مرکز جلو.',
            ],
        ];
    }

    /**
     * پارامترهای مشترک کمر.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function waistParams(float $dartShare = 0.5, float $bandHeight = 4, bool $band = true, string $zip = 'side'): array
    {
        return array_merge($this->zipParam($zip), [
            'dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05,
                'default' => $dartShare,
                'hint' => 'باقی کاهش کمر در درز پهلو گرفته می‌شود.',
            ],
            'waistband' => [
                'label' => 'کمربند داشته باشد', 'type' => 'toggle', 'default' => $band,
            ],
            'waistband_height' => [
                'label' => 'بلندی کمربند', 'min' => 2, 'max' => 12, 'step' => 0.5,
                'default' => $bandHeight, 'unit' => 'سانتی‌متر',
            ],
        ]);
    }

    /**
     * کمربند این دامن، اگر کاربر خواسته باشد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function bandPieces(array $mx, array $params, array $o = []): array
    {
        if (! $this->flag($params, 'waistband', true)) {
            return [];
        }

        return [$this->bandPiece((float) $mx['waist_target'], array_merge([
            'height' => (float) $this->param($params, 'waistband_height', 4),
        ], $o))];
    }
}
