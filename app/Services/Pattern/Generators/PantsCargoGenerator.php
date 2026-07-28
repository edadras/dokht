<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار کارگو — فقط خود پا.
 *
 * پای راسته و جادار با دور ران باز، چون جیب‌های بغل روی همین پهنا می‌نشینند. جای
 * جیب فقط در meta علامت می‌خورد؛ خود جیب کار سبک جیب است نه این مدل.
 */
class PantsCargoGenerator extends PantsBaseGenerator
{
    public static function key(): string
    {
        return 'pants_cargo';
    }

    public function label(): string
    {
        return 'شلوار کارگو';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(16, 22),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 8, 'max' => 30, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                    'hint' => 'جیب بغل روی همین پهنا می‌نشیند، پس دست‌ودل‌بازتر گرفته می‌شود.',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->bandParams(4.5),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 16),
            'hem_vs_knee' => -4.0,
            'front_waist' => 'dart',
            'waist_balance' => 0.5,
            'side_share' => 0.3,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);

        // جای پیشنهادی جیب بغل: از کمی پایین‌تر از خط فاق تا میانه ران
        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $crotch = (float) ($piece['meta']['crotch_depth'] ?? 0);
            $knee = (float) ($piece['meta']['knee_y'] ?? ($crotch + 30));

            $pieces[$index]['meta']['pocket_zone'] = [
                'label' => 'ناحیه جیب بغل',
                'from_y' => round($crotch + 6, 2),
                'to_y' => round($crotch + (($knee - $crotch) * 0.55), 2),
                'edge' => 'side',
            ];
            $pieces[$index]['meta']['pocket_ready'] = true;
        }

        return $pieces;
    }
}
