<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن یقه‌باز (کمپ‌کالر / هاوایی).
 *
 * یقه‌اش پایه ندارد و روی شانه می‌خوابد، پس دکمهٔ بالایی هم ندارد و یقه همیشه
 * باز است. همین یک تفاوت، دو چیز دیگر را ناگزیر می‌کند:
 *
 *   - خط یقهٔ جلو باید گودتر از پیراهن کلاسیک بریده شود، وگرنه یقهٔ باز روی
 *     گلو می‌ایستد به‌جای اینکه بخوابد.
 *   - لبهٔ جلو از یقه تا دم یک خط پیوسته است؛ پاتلت جدا ندارد و لبه برگردانده
 *     و دوخته می‌شود.
 *
 * تنهٔ گشاد و آستین کوتاه پیش‌فرض است؛ این پیراهن برای گرما ساخته شده.
 */
class ShirtCampCollarGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_camp';
    }

    public function label(): string
    {
        return 'پیراهن یقه‌باز (کمپ)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['fit' => 'loose', 'sleeve_length' => 22, 'front_neck_depth_extra' => 4, 'neck_width_extra' => 2],
            array_merge([
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                    'default' => 1.75, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 5, 'max' => 12, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'collar_point' => [
                    'label' => 'نوک یقه', 'min' => 0, 'max' => 7, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
            ], $this->pocketParam(true)),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);
        $stand = (float) $this->param($params, 'button_stand', 1.75);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'camp',
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'no_cuff' => true,
            'sleeve_name' => 'آستین کوتاه',
        ]);

        $neckHalf = ($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9);
        $bottom = Geometry::height($front['outline']);

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->campCollar(
                $neckHalf,
                (float) $this->param($params, 'collar_height', 8),
                (float) $this->param($params, 'collar_point', 3),
            ),
        ]);

        if ($this->flag($params, 'chest_pocket', true)) {
            $pieces[] = $this->patchPocket(12, 13.5, ['name' => 'جیب سینه', 'radius' => 2]);
        }

        $pieces[] = $this->placket($stand, $bottom, spacing: 9.0);

        return $this->finish($this->noteOn($pieces, [
            'یقهٔ باز روی شانه می‌خوابد و دکمهٔ بالایی ندارد؛ خط یقهٔ جلو عمداً گودتر بریده شده.',
            'لبهٔ جلو یک خط پیوسته است؛ اگر پاتلت جدا نمی‌خواهید همان لبه را دو بار برگردانید و بدوزید.',
        ]));
    }
}
