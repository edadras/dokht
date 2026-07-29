<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;

/**
 * شورت مایو مردانه (ترانکس).
 *
 * پاچهٔ کوتاه، کمر کشی با بند، و آستر توری داخل.
 *
 * دو چیز که مایو مردانه را از شورت معمولی جدا می‌کند و هر دو این‌جا هست:
 *
 *   بند کمر   کش به‌تنهایی کافی نیست؛ در آب که شورت سنگین می‌شود، بند است که
 *             نگهش می‌دارد. پس هم کش هست هم بند، و دو جادکمه‌ای روی کمر.
 *   آستر توری آستر توری داخل، جای شورت زیر را می‌گیرد و در آب سریع خشک می‌شود.
 *
 * خودِ پاچه از همان درفت آزمودهٔ شورت کاتالوگ می‌آید؛ چیزی از نو ساخته نمی‌شود.
 */
class SwimTrunksGenerator extends SwimBaseGenerator
{
    /** ترانکس با آزادی مثبت بریده می‌شود (کمر باید از باسن رد شود)، پس مهرِ «کوچک‌تر از بدن» نمی‌گیرد. */
    protected bool $negativeEase = false;

    public static function key(): string
    {
        return 'swim_trunks';
    }

    public function label(): string
    {
        return 'شورت مایو مردانه (ترانکس)';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'leg_length' => [
                'label' => 'قد پاچه از خط فاق', 'min' => 8, 'max' => 35, 'step' => 1,
                'default' => 18, 'unit' => 'سانتی‌متر',
            ],
            'waist_ease' => [
                'label' => 'آزادی کمر', 'min' => 0, 'max' => 14, 'step' => 1,
                'default' => 6, 'unit' => 'سانتی‌متر',
                'hint' => 'کمر با کش جمع می‌شود، پس آزادی لازم دارد تا از باسن رد شود.',
            ],
            'elastic_width' => [
                'label' => 'پهنای کش کمر', 'min' => 2, 'max' => 6, 'step' => 0.5,
                'default' => 3.5, 'unit' => 'سانتی‌متر',
            ],
            'mesh_lining' => [
                'label' => 'آستر توری داخل', 'type' => 'toggle', 'default' => true,
            ],
            'back_pocket' => [
                'label' => 'جیب پشت با زیپ', 'type' => 'toggle', 'default' => false,
            ],
        ], stretch: 0.95);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretch($params);
        $waistEase = (float) $this->param($params, 'waist_ease', 6);

        $pieces = $this->outerShorts($measurements, $ease, $params, $waistEase);

        $waist = (((float) ($measurements['waist'] ?? 82)) + $waistEase) * $stretch;
        $elastic = (float) $this->param($params, 'elastic_width', 3.5);

        $pieces[] = $this->strapPiece($waist + 70, 1.2, [
            'code' => 'trunks-drawcord',
            'name' => 'بند کمر',
            'cut' => 1,
            'meta' => ['notes' => ['از دو جادکمه‌ای جلوی کمر بیرون می‌آید و گره می‌خورد.']],
        ]);

        $pieces[0]['meta']['notions'][] = [
            'type' => 'elastic',
            'label' => 'کش کمر '.$this->fa($elastic).' سانتی‌متری',
            'length' => round($waist * 0.92, 1),
        ];

        $pieces[0]['meta']['notions'][] = [
            'type' => 'eyelet',
            'label' => 'جادکمه‌ای بند کمر',
            'count' => 2,
        ];

        if ($this->flag($params, 'mesh_lining', true)) {
            foreach ($this->meshLining($measurements, $ease, $params) as $liner) {
                $pieces[] = $liner;
            }
        }

        if ($this->flag($params, 'back_pocket', false)) {
            $pocket = $this->bandPiece('trunks-pocket', 'جیب پشت', 15, 16, [
                'cut' => 1, 'part' => 'pocket',
                'meta' => [
                    'girth_role' => 'trim',
                    'notions' => [['type' => 'zip', 'label' => 'زیپ جیب پشت', 'length' => 13]],
                    'notes' => ['زیپ ضدزنگ بگذارید؛ زیپ فلزی معمولی در آب شور خراب می‌شود.'],
                ],
            ]);
            $pieces[] = $pocket;
        }

        $notes = array_merge($this->swimNotes($params), [
            'کش به‌تنهایی کافی نیست: در آب شورت سنگین می‌شود و بند کمر است که نگهش می‌دارد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $text) => ['type' => 'info', 'text' => $text],
            $notes,
        )));
    }

    /**
     * پاچهٔ بیرونی، از روی همان درفت آزمودهٔ شورت کاتالوگ.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function outerShorts(array $measurements, array $ease, array $params, float $waistEase): array
    {
        $generator = GeneratorRegistry::make('shorts_short');
        $defaults = $generator->defaultParams();

        $built = $generator->generate($measurements, array_merge($ease, [
            'waist' => $waistEase,
            'hip' => $waistEase + 4,
        ]), array_merge($defaults, array_filter([
            'leg_length' => (float) $this->param($params, 'leg_length', 18),
            'waistband' => false,
        ], fn ($value) => $value !== null)));

        $out = [];

        foreach ($built as $piece) {
            if (($piece['meta']['part'] ?? '') === 'waistband') {
                continue;
            }

            $piece['code'] = 'trunks-'.($piece['code'] ?? 'leg');
            $piece['meta']['swimwear'] = true;
            $out[] = $piece;
        }

        return $out;
    }

    /**
     * آستر توری: همان پاچه، کوتاه‌تر و از توری.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function meshLining(array $measurements, array $ease, array $params): array
    {
        $generator = GeneratorRegistry::make('shorts_cycling');
        $rise = (float) ($measurements['rise'] ?? 26);

        $built = $generator->generate($measurements, $ease, array_merge($generator->defaultParams(), [
            'waistband' => false,
            'leg_length' => max(8.0, min(35.0, ((float) $this->param($params, 'leg_length', 18)) - 4)),
        ]));

        $out = [];

        foreach ($built as $piece) {
            if (($piece['meta']['part'] ?? '') === 'waistband') {
                continue;
            }

            $piece['code'] = 'trunks-mesh-'.($piece['code'] ?? 'leg');
            $piece['name'] = 'آستر توری — '.($piece['name'] ?? '');
            $piece['layer'] = 'lining';
            $piece['meta']['girth_role'] = 'lining';
            $piece['meta']['part'] = 'lining';
            $piece['meta']['notes'][] = 'از توری مایو بریده می‌شود؛ جای شورت زیر را می‌گیرد و در آب زود خشک می‌شود.';
            $out[] = $piece;
        }

        return $out;
    }
}
