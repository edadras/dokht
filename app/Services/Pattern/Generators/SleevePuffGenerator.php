<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین پفی سرشانه.
 *
 * الگوی آستین ساده از سرآستین تا دم بریده و باز می‌شود (برش و باز کردن)؛ لولا
 * روی دم آستین است، پس دم آستین اندازه خودش می‌ماند و همه گشادی بالای آستین
 * می‌رود. مقدار بازشدن به درصد داده می‌شود: ۴۰ درصد یعنی سرآستین ۴۰ درصد بلندتر
 * از حلقه می‌شود و همان مقدار روی سرشانه چین می‌خورد.
 */
class SleevePuffGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_puff_cap';
    }

    public function label(): string
    {
        return 'آستین پفی سرشانه';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.0, 30) + [
            'puff' => [
                'label' => 'مقدار پف سرشانه', 'min' => 10, 'max' => 120, 'step' => 5, 'default' => 40,
                'unit' => 'درصد', 'hint' => 'درصد بازشدن الگو؛ همین مقدار چین سرآستین می‌شود.',
            ],
            'cap_lift' => [
                'label' => 'بلندتر کردن سرآستین', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 2,
                'unit' => 'سانتی‌متر', 'hint' => 'پف را ایستاده‌تر می‌کند.',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $puff = max(0.0, ((float) $this->param($params, 'puff', 40)) / 100);

        return [
            'code' => 'sleeve-puff',
            'name' => 'آستین پفی سرشانه',
            'family' => 'puff',
            'ease_band' => [3.0, 25.0],
            'cap_fullness' => $puff,
            'cap_lift' => (float) $this->param($params, 'cap_lift', 2),
            'gather_cap' => true,
            'side_bulge' => 0.4,
            'notes' => ['پف سرشانه با چین‌دادن سرآستین بین دو نشانه جلو و پشت بسته می‌شود.'],
        ];
    }
}
