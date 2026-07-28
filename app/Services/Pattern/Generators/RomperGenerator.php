<?php

namespace App\Services\Pattern\Generators;

/**
 * رامپر (سرهمی کوتاه).
 *
 * همان درفت سرهمی است با پای کوتاه: قد پا از روی «قد داخل پا» گرفته نمی‌شود
 * بلکه کاربر مستقیم بلندی پاچه را می‌گوید. آستین کوتاه یا حلقه‌ای است و کمر
 * می‌تواند با کش جمع شود.
 */
class RomperGenerator extends JumpsuitGenerator
{
    public static function key(): string
    {
        return 'romper';
    }

    public function label(): string
    {
        return 'رامپر (سرهمی کوتاه)';
    }

    public function paramsSchema(): array
    {
        $schema = parent::paramsSchema();
        unset($schema['length_extra']);

        return array_merge($schema, [
            'leg_length' => [
                'label' => 'بلندی پاچه از فاق', 'min' => 4, 'max' => 40, 'step' => 1,
                'default' => 12, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دم پاچه', 'min' => 4, 'max' => 40, 'step' => 1,
                'default' => 20, 'unit' => 'سانتی‌متر',
            ],
            'elastic_waist' => [
                'label' => 'کش کمر', 'type' => 'toggle', 'default' => true,
            ],
        ]);
    }

    protected function legLengthExtra(array $m, array $params): float
    {
        return (float) $this->param($params, 'leg_length', 12) - $this->m($m, 'inseam', 75);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['girth_role'] ?? '') !== 'bottom') {
                continue;
            }

            $pieces[$index]['name'] = str_replace('پای ', 'پاچه ', $piece['name']);
        }

        if ($this->flag($params, 'elastic_waist', true)) {
            $pieces[] = $this->bandPiece('romper-waist-elastic', 'نوار کش کمر', $this->m($measurements, 'waist', 74) * 0.85, 3, [
                'cut' => 1, 'part' => 'waistband',
                'meta' => [
                    'stretch_ratio' => 0.85,
                    'notes' => ['کش کمر پانزده درصد کوتاه‌تر از دور کمر بریده و کشیده دوخته می‌شود.'],
                ],
            ]);
        }

        return $this->finish($pieces);
    }
}
