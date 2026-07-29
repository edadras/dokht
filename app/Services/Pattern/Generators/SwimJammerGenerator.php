<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;

/**
 * جامر (مایو بلند مسابقه‌ای).
 *
 * مایو مردانهٔ تنگ تا بالای زانو؛ همان که شناگرها می‌پوشند.
 *
 * برخلاف بردشورت که گشاد است، جامر باید به تن بچسبد — هر میلی‌متر پارچهٔ آزاد
 * در آب مقاومت می‌سازد. برای همین ضریب کشسانی این مدل کمترین مقدار خانواده
 * است و آزادی‌اش منفی و زیاد.
 *
 * دو نکتهٔ دیگر که در الگو هست: کمر فقط کش دارد و بند ندارد (بند در آب باز
 * می‌شود و مقاومت می‌سازد)، و درزها باید تخت دوخته شوند تا روی پوست رد
 * نیندازند.
 */
class SwimJammerGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_jammer';
    }

    public function label(): string
    {
        return 'جامر (مایو مسابقه‌ای)';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'leg_length' => [
                'label' => 'قد پاچه از خط فاق', 'min' => 18, 'max' => 42, 'step' => 1,
                'default' => 30, 'unit' => 'سانتی‌متر',
                'hint' => 'جامر مسابقه‌ای تا بالای زانو می‌آید.',
            ],
            'elastic_width' => [
                'label' => 'پهنای کش کمر', 'min' => 2, 'max' => 5, 'step' => 0.5,
                'default' => 3, 'unit' => 'سانتی‌متر',
            ],
        ], stretch: 0.72);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretch($params);
        $generator = GeneratorRegistry::make('shorts_cycling');

        $shrink = fn (string $key, float $fallback) => -((float) ($measurements[$key] ?? $fallback)) * (1 - $stretch);

        $built = $generator->generate($measurements, array_merge($ease, [
            'waist' => $shrink('waist', 82),
            'hip' => $shrink('hip', 98),
        ]), array_merge($generator->defaultParams(), [
            'leg_length' => max(18.0, min(35.0, (float) $this->param($params, 'leg_length', 30))),
            'waistband' => false,
            'stretch' => $stretch,
        ]));

        $pieces = [];

        foreach ($built as $piece) {
            if (($piece['meta']['part'] ?? '') === 'waistband') {
                continue;
            }

            $piece['code'] = 'jammer-'.($piece['code'] ?? 'leg');
            $piece['meta']['swimwear'] = true;
            $piece['meta']['notes'][] = 'درز را تخت (فلت‌لاک) بدوزید؛ درز برجسته روی پوست رد می‌اندازد.';
            $pieces[] = $piece;
        }

        $waist = ((float) ($measurements['waist'] ?? 82)) * $stretch;
        $elastic = (float) $this->param($params, 'elastic_width', 3);

        $pieces[] = $this->bandPiece('jammer-casing', 'نوار جای کش کمر', $waist / 2, $elastic + 1.5, [
            'cut' => 2, 'part' => 'waistband',
            'meta' => [
                'girth_role' => 'trim',
                'notions' => [[
                    'type' => 'elastic',
                    'label' => 'کش کمر '.$this->fa($elastic).' سانتی‌متری',
                    'length' => round($waist * 0.92, 1),
                ]],
                'notes' => ['کمر فقط کش دارد و بند ندارد؛ بند در آب باز می‌شود و مقاومت می‌سازد.'],
            ],
        ]);

        $notes = array_merge($this->swimNotes($params), [
            'جامر باید به تن بچسبد: هر میلی‌متر پارچهٔ آزاد در آب مقاومت می‌سازد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
