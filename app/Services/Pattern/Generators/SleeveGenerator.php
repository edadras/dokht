<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین یک‌تکه.
 *
 * سرآستین (کپ) از طول حلقه آستین بالاتنه ساخته می‌شود: ارتفاع کپ چند بار تنظیم
 * می‌شود تا طول منحنی سرآستین به «طول حلقه + آزادی کپ» برسد؛ همان کاری که خیاط با
 * پیاده‌کردن حلقه روی آستین انجام می‌دهد.
 */
class SleeveGenerator extends BaseGenerator
{
    use Concerns\BuildsSleeve;

    public function label(): string
    {
        return 'آستین ساده';
    }

    public function paramsSchema(): array
    {
        return [
            'armhole_length' => [
                'label' => 'طول حلقه آستین بالاتنه', 'min' => 0, 'max' => 90, 'step' => 0.5, 'default' => 0,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی از اندازه دور حلقه آستین حساب شود.',
            ],
            'cap_ease' => [
                'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 6, 'step' => 0.25, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'length_extra' => [
                'label' => 'تغییر بلندی آستین', 'min' => -45, 'max' => 10, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دم آستین', 'min' => 0, 'max' => 20, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر',
            ],
            'cuff' => [
                'label' => 'مچ‌بند داشته باشد', 'type' => 'toggle', 'default' => false,
            ],
            'cuff_height' => [
                'label' => 'بلندی مچ‌بند', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        return $this->finish($this->sleevePieces($measurements, $ease, $params));
    }
}
