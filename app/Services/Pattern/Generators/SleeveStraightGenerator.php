<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین ساده راسته.
 *
 * آستین پایه یک‌تکه: سرآستین روی حلقه پیاده می‌شود و بدنه از خط بازو تا مچ کمی
 * باریک می‌شود. بلندی با یک پارامتر عوض می‌شود (کپ، کوتاه، تا آرنج، سه‌ربع، بلند)
 * چون شکل آستین در همه این بلندی‌ها یکی است.
 */
class SleeveStraightGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_straight';
    }

    public function label(): string
    {
        return 'آستین ساده راسته';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.5, 100) + [
            'side_bulge' => [
                'label' => 'برآمدگی درز پهلو', 'min' => 0, 'max' => 3, 'step' => 0.1, 'default' => 0.6,
                'unit' => 'سانتی‌متر', 'hint' => 'کمی برآمدگی در ناحیه بازو تا آستین به دست نچسبد.',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        return [
            'code' => 'sleeve-straight',
            'name' => 'آستین ساده راسته',
            'family' => 'set_in',
            'ease_band' => [1.5, 4.0],
            'side_bulge' => (float) $this->param($params, 'side_bulge', 0.6),
        ];
    }
}
