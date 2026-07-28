<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه مشترک همه شلوارها و شلوارک‌های کاتالوگ.
 *
 * فقط کارهای تکراری را برمی‌دارد: گروه مدل، پارامترهای مشترک (بلندی فاق، قد پا،
 * آزادی زانو و دم پا، کمربند) و ساختن کمربند از روی همان دور کمری که پنل‌ها با آن
 * درفت شده‌اند. شکل خود شلوار در PantsBlock است.
 */
abstract class PantsBaseGenerator extends BaseGenerator
{
    use PantsBlock;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'pants';
    }

    /** گزینه‌های ثابت این مدل که کاربر تغییرشان نمی‌دهد (شخصیت مدل). */
    abstract protected function shape(array $params, array $measurements): array;

    public function generate(array $measurements, array $ease, array $params): array
    {
        $shape = $this->shape($params, $measurements);
        $mx = $this->pantsMetrics($measurements, $ease, $params, $shape);

        return $this->finish(array_merge(
            $this->legPieces($mx, $shape),
            $this->closurePieces($mx, $params, $shape),
        ));
    }

    /**
     * کمربند یا نوار کش و مچ دم پا.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function closurePieces(array $mx, array $params, array $shape): array
    {
        $pieces = [];
        $band = (string) ($shape['band'] ?? 'waistband');

        if ($band === 'waistband' && $this->flag($params, 'waistband', true)) {
            $pieces[] = $this->pantsBand($mx, $params);
        }

        if ($band === 'elastic') {
            $pieces[] = $this->elasticBand((float) $mx['waist_target'], [
                'height' => (float) $this->param($params, 'waistband_height', 4),
                'stretch' => (float) ($shape['band_stretch'] ?? 0.85),
                'name' => 'نوار کش کمر',
                'code' => 'waist-elastic',
                'part' => 'waistband',
            ]);
        }

        if (isset($shape['hem_gather']) && ($shape['hem_band'] ?? true)) {
            $pieces[] = $this->elasticBand((float) $shape['hem_gather'], [
                'height' => (float) ($shape['hem_band_height'] ?? 4),
                'stretch' => (float) ($shape['hem_band_stretch'] ?? 0.88),
                'cut_quantity' => 2,
                'name' => 'مچ دم پا',
                'code' => 'ankle-cuff',
                'part' => 'cuff',
            ]);
        }

        return $pieces;
    }

    /**
     * پارامتر بلندی فاق: کوتاه، متوسط، بلند.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function riseParams(string $default = 'mid', float $extraDefault = 0): array
    {
        return [
            'rise' => [
                'label' => 'بلندی فاق', 'type' => 'select', 'default' => $default,
                'options' => ['low' => 'کوتاه', 'mid' => 'متوسط', 'high' => 'بلند'],
                'hint' => 'خط کمر الگو چقدر پایین‌تر از گودی کمر بنشیند.',
            ],
            'rise_extra' => [
                'label' => 'تغییر گودی فاق', 'min' => -5, 'max' => 10, 'step' => 0.5,
                'default' => $extraDefault, 'unit' => 'سانتی‌متر',
                'hint' => 'مثبت یعنی فاق گودتر؛ برای بدن‌هایی که نشستنشان جا کم می‌آورد.',
            ],
        ];
    }

    /**
     * پارامترهای قد و پهنای پا.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function legParams(float $knee = 7, float $hem = 10, float $lengthMin = -60): array
    {
        return [
            'length_extra' => [
                'label' => 'تغییر قد پا', 'min' => $lengthMin, 'max' => 12, 'step' => 1,
                'default' => 0, 'unit' => 'سانتی‌متر',
                'hint' => 'نسبت به قد داخل پای اندازه‌گرفته‌شده.',
            ],
            'knee_ease' => [
                'label' => 'آزادی دور زانو', 'min' => -6, 'max' => 45, 'step' => 1,
                'default' => $knee, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دور دم پا', 'min' => -6, 'max' => 70, 'step' => 1,
                'default' => $hem, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /**
     * پارامترهای کمربند.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function bandParams(float $height = 4, bool $on = true): array
    {
        return [
            'waistband' => [
                'label' => 'کمربند داشته باشد', 'type' => 'toggle', 'default' => $on,
            ],
            'waistband_height' => [
                'label' => 'بلندی کمربند', 'min' => 2, 'max' => 14, 'step' => 0.5,
                'default' => $height, 'unit' => 'سانتی‌متر',
            ],
        ];
    }
}
