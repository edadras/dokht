<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;

/**
 * بردشورت (مایو بلند موج‌سواری).
 *
 * بلندتر از ترانکس، تا نزدیک زانو، و برای نشستن روی تختهٔ موج ساخته شده. سه
 * تفاوت با ترانکس دارد که همه‌شان از همان کاربرد می‌آیند:
 *
 *   - چاک پهلو: پا باید آزادانه باز شود، وگرنه در پارو زدن مزاحم است.
 *   - بند بیرون از کمر و بستِ چسبی جلو: کش به‌تنهایی زیر ضربهٔ موج کافی نیست.
 *   - پارچهٔ کم‌کشش‌تر: بردشورت معمولاً از پارچهٔ بافتهٔ سبک است، نه کشباف؛
 *      برای همین ضریب کشسانی این مدل نزدیک یک است و آزادی واقعی دارد.
 */
class SwimBoardshortsGenerator extends SwimBaseGenerator
{
    /** بردشورت از پارچهٔ بافتهٔ سبک است و گشاد؛ آزادی‌اش مثبت است، نه منفی. */
    protected bool $negativeEase = false;

    public static function key(): string
    {
        return 'swim_boardshorts';
    }

    public function label(): string
    {
        return 'بردشورت (موج‌سواری)';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'leg_length' => [
                'label' => 'قد پاچه از خط فاق', 'min' => 20, 'max' => 45, 'step' => 1,
                'default' => 32, 'unit' => 'سانتی‌متر',
            ],
            'waist_ease' => [
                'label' => 'آزادی کمر', 'min' => 2, 'max' => 16, 'step' => 1,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'side_slit' => [
                'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 18, 'step' => 1,
                'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'closure' => [
                'label' => 'بست جلو', 'type' => 'select', 'default' => 'velcro',
                'options' => ['velcro' => 'چسبی', 'lace' => 'بند از حلقه', 'both' => 'هر دو'],
            ],
        ], stretch: 0.98);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $waistEase = (float) $this->param($params, 'waist_ease', 8);
        $generator = GeneratorRegistry::make('shorts_bermuda');

        $built = $generator->generate($measurements, array_merge($ease, [
            'waist' => $waistEase,
            'hip' => $waistEase + 5,
        ]), array_merge($generator->defaultParams(), [
            'leg_length' => max(20.0, min(45.0, (float) $this->param($params, 'leg_length', 32))),
            'waistband' => false,
        ]));

        $pieces = [];
        $slit = (float) $this->param($params, 'side_slit', 10);

        foreach ($built as $piece) {
            if (($piece['meta']['part'] ?? '') === 'waistband') {
                continue;
            }

            $piece['code'] = 'board-'.($piece['code'] ?? 'leg');
            $piece['meta']['swimwear'] = true;

            if ($slit > 0.5) {
                $piece['meta']['vent'] = ['edge' => 'side', 'height' => round($slit, 1)];
                $piece['meta']['notes'][] = 'چاک پهلو به بلندی '.$this->fa($slit)
                    .' سانتی‌متر؛ بدون آن پا در پارو زدن آزاد نیست.';
            }

            $pieces[] = $piece;
        }

        $waist = ((float) ($measurements['waist'] ?? 82)) + $waistEase;
        $closure = (string) $this->param($params, 'closure', 'velcro');

        $pieces[] = $this->bandPiece('board-waistband', 'کمربند', $waist / 2, 5, [
            'cut' => 2, 'part' => 'waistband',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notions' => array_values(array_filter([
                    in_array($closure, ['velcro', 'both'], true)
                        ? ['type' => 'other', 'label' => 'نوار چسبی جلو', 'length' => 12]
                        : null,
                    in_array($closure, ['lace', 'both'], true)
                        ? ['type' => 'eyelet', 'label' => 'حلقهٔ بند کمر', 'count' => 4]
                        : null,
                ])),
                'notes' => ['کش به‌تنهایی زیر ضربهٔ موج کافی نیست؛ بست جلو کار اصلی را می‌کند.'],
            ],
        ]);

        $pieces[] = $this->tie($waist + 60, 1.2, 'board-cord', 'بند کمر', 1);

        $notes = [
            'بردشورت معمولاً از پارچهٔ بافتهٔ سبک است، نه کشباف؛ برای همین این الگو آزادی واقعی دارد و آزادی منفی ندارد.',
            'درزها را دولا (فلت‌فِل) بدوزید؛ درز ساده روی تخته ساییده می‌شود.',
        ];

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
