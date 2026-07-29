<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوارک خواب.
 *
 * شلوارک کوتاهِ جرسی با کمر کشی و بند؛ همان که با یک تی‌شرت می‌پوشند.
 *
 * پاچه، درز داخل پا و منحنی فاقش واقعاً شلوارک است، پس از همان درفت آزمودهٔ
 * شلوارک کاتالوگ می‌آید و اسم قطعه‌هایش هم عوض نمی‌شود. آنچه این‌جا اضافه
 * می‌شود، همان چیزی است که «خواب» بودنش را می‌سازد:
 *
 *   - **پارچهٔ کشی و آزادی منفی روی باسن.** شلوارک خواب باید سرِ جایش بماند؛
 *     شلوارکِ گشاد در خواب بالا می‌رود و لوله می‌شود.
 *   - **ران و دم پا آزاد.** برخلاف شلوارک ورزشی، پاچه نباید به ران بچسبد؛ آزادی
 *     ران و دم پا مثبت گرفته شده تا در خواب نکشد.
 *   - **کمر هم کش هم بند.** کشِ تنها در خواب می‌چرخد و بندِ تنها باز می‌شود.
 */
class SleepShortsGenerator extends SleepwearBaseGenerator
{
    public static function key(): string
    {
        return 'sleep_shorts';
    }

    public function label(): string
    {
        return 'شلوارک خواب';
    }

    public function paramsSchema(): array
    {
        return $this->knitSchema([
            'leg_length' => [
                'label' => 'قد پا از خط فاق', 'min' => 6, 'max' => 30, 'step' => 1,
                'default' => 13, 'unit' => 'سانتی‌متر',
            ],
            'thigh_ease' => [
                'label' => 'آزادی دور ران', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
                'hint' => 'برخلاف شلوارک ورزشی، پاچهٔ شلوارک خواب نباید به ران بچسبد.',
            ],
            'hip_ease' => [
                'label' => 'آزادی دور باسن', 'min' => -4, 'max' => 10, 'step' => 0.5,
                'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'waistband_height' => [
                'label' => 'بلندی نوار کش کمر', 'min' => 2, 'max' => 8, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'band_ratio' => [
                'label' => 'کوتاهی نوار کمر', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                'default' => 0.86,
            ],
            'drawcord' => [
                'label' => 'بند کمر', 'type' => 'toggle', 'default' => true,
            ],
        ], stretch: 0.94);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretchOf($params);

        $pieces = $this->bottomFrom('shorts_cycling', $measurements, array_merge($ease, [
            'hip' => (float) $this->param($params, 'hip_ease', 2),
            'waist' => 1.0,
        ]), $params, [
            'prefix' => 'sleep-shorts',
            'params' => [
                'leg_length' => (float) $this->param($params, 'leg_length', 13),
                'thigh_ease' => (float) $this->param($params, 'thigh_ease', 6),
                'hem_ease' => (float) $this->param($params, 'thigh_ease', 6),
                'stretch' => $stretch,
                'waistband_height' => (float) $this->param($params, 'waistband_height', 4),
            ],
            'notes' => [
                'پاچه از درفت شلوارک کاتالوگ می‌آید: درز داخل پا، منحنی فاق و دم پاچهٔ واقعی دارد.',
                'درز داخل پا را تخت (فلت‌لاک) بدوزید؛ درز برجسته زیر ران در خواب رد می‌اندازد.',
            ],
        ]);

        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'band_ratio', 0.86)));
        $height = (float) $this->param($params, 'waistband_height', 4);
        $waist = $this->m($measurements, 'waist', 74) * $stretch;

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') !== 'waistband') {
                continue;
            }

            $pieces[$index]['meta']['notions'][] = [
                'type' => 'elastic',
                'label' => 'کش کمر '.$this->fa($height).' سانتی‌متری',
                'length' => round($waist * $ratio, 1),
                'edge_length' => round($waist, 1),
            ];

            $pieces[$index]['meta']['notes'][] = 'کش '.$this->fa(round((1 - $ratio) * 100))
                .' درصد کوتاه‌تر از دور کمر بریده می‌شود و روی نوار کشیده می‌شود.';
        }

        if ($this->flag($params, 'drawcord', true)) {
            $pieces[] = $this->strapPiece($waist + 60, 1.2, [
                'code' => 'sleep-shorts-drawcord',
                'name' => 'بند کمر',
                'cut' => 1,
                'meta' => [
                    'notions' => [[
                        'type' => 'eyelet',
                        'label' => 'جادکمه‌ای بند کمر',
                        'count' => 2,
                    ]],
                    'notes' => [
                        'از دو جادکمه‌ای جلوی کمر بیرون می‌آید و گره می‌خورد.',
                        'کشِ تنها در خواب می‌چرخد و بندِ تنها باز می‌شود؛ هر دو با هم لازم‌اند.',
                    ],
                ],
            ]);
        }

        return $this->finishSleepwear($pieces, $this->sleepNotes($params, [
            'باسن با آزادی کم بریده شده تا شلوارک سرِ جایش بماند، ولی ران و دم پا آزادی مثبت دارند؛'
                .' شلوارک خواب نباید مثل شلوارک ورزشی به پا بچسبد.',
            'دم پاچه را با دوخت دوسوزنه برگردانید تا در شست‌وشوی مکرر باز نشود.',
        ]));
    }
}
