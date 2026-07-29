<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\Concerns\BuildsSleeve;

/**
 * بورکینی (مایو پوشیده).
 *
 * سه قطعه: تونیک بلند آستین‌دار، شلوار، و کلاه.
 *
 * سخت‌ترین چیز در مایو پوشیده، پوشش نیست — شناوری است. پارچهٔ زیاد در آب هوا
 * می‌گیرد و باد می‌کند، و همان باد شناور کردن و حرکت را سخت می‌کند. سه تصمیم
 * در این الگو جلوی آن را می‌گیرد:
 *
 *   ۱. تونیک از پهلو چاک دارد تا هوای زیرش راه فرار داشته باشد.
 *   ۲. آستین و پاچه در مچ کش می‌خورند، پس هوا از دو سر بیرون می‌رود.
 *   ۳. آزادی تونیک محدود است؛ بورکینیِ خیلی گشاد در آب مثل بادکنک می‌شود.
 *
 * کلاه هم لبهٔ صورت کش دارد، وگرنه در آب عقب می‌رود.
 */
class SwimBurkiniGenerator extends SwimBaseGenerator
{
    // تنها مایوی این خانواده که آستین دوخته‌شده دارد
    use BuildsSleeve;

    /**
     * بورکینی برخلاف بقیهٔ این خانواده با آزادی مثبت بریده می‌شود: پارچه‌اش کشی
     * است ولی لباس گشاد است. پس مهرِ «کوچک‌تر از بدن» روی قطعه‌هایش نمی‌نشیند،
     * وگرنه ادعایی می‌کند که درست نیست.
     */
    protected bool $negativeEase = false;

    public static function key(): string
    {
        return 'swim_burkini';
    }

    public function label(): string
    {
        return 'بورکینی (مایو پوشیده)';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'tunic_length' => [
                'label' => 'بلندی تونیک از خط کمر', 'min' => 20, 'max' => 60, 'step' => 1,
                'default' => 38, 'unit' => 'سانتی‌متر',
            ],
            'tunic_ease' => [
                'label' => 'آزادی تونیک', 'min' => 2, 'max' => 16, 'step' => 1,
                'default' => 8, 'unit' => 'سانتی‌متر',
                'hint' => 'بورکینیِ خیلی گشاد در آب باد می‌کند؛ آزادی زیاد ندهید.',
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین', 'min' => 20, 'max' => 62, 'step' => 1,
                'default' => 54, 'unit' => 'سانتی‌متر',
            ],
            'side_slit' => [
                'label' => 'بلندی چاک پهلوی تونیک', 'min' => 0, 'max' => 30, 'step' => 1,
                'default' => 16, 'unit' => 'سانتی‌متر',
            ],
            'leg_length' => [
                'label' => 'قد پاچه از خط فاق', 'min' => 30, 'max' => 80, 'step' => 1,
                'default' => 62, 'unit' => 'سانتی‌متر',
            ],
            'hood' => [
                'label' => 'کلاه', 'type' => 'toggle', 'default' => true,
            ],
        ], stretch: 0.92);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretch($params);
        $tunicEase = (float) $this->param($params, 'tunic_ease', 8);

        $ease = array_merge($ease, [
            'bust' => $tunicEase,
            'waist' => $tunicEase + 4,
            'hip' => $tunicEase + 4,
            'bicep' => 4,
        ]);

        $g = $this->blockMetrics($measurements, $ease, $params);

        $shared = [
            'shape' => 'straight',
            'length' => (float) $this->param($params, 'tunic_length', 38),
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'armhole_drop' => 2.0,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front', 'code' => 'burkini-front', 'name' => 'تونیک جلو',
            'neck_depth_extra' => 2,
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back', 'code' => 'burkini-back', 'name' => 'تونیک پشت',
        ]));

        $slit = (float) $this->param($params, 'side_slit', 16);

        foreach ([&$front, &$back] as &$piece) {
            if ($slit > 0.5) {
                $piece['meta']['vent'] = ['edge' => 'side', 'height' => round($slit, 1)];
                $piece['meta']['notes'][] = 'چاک پهلو به بلندی '.$this->fa($slit)
                    .' سانتی‌متر؛ راه فرار هوای زیر تونیک است، وگرنه در آب باد می‌کند.';
            }
        }

        unset($piece);

        $sleeves = $this->sleevePieces($measurements, $ease, array_merge($params, [
            'cap_ease' => 1.0,
        ]), [
            'armhole_length' => ($front['meta']['armhole_length'] ?? 22) + ($back['meta']['armhole_length'] ?? 22),
            'length' => (float) $this->param($params, 'sleeve_length', 54),
            'no_cuff' => true,
            'sleeve_name' => 'آستین بورکینی',
        ]);

        $pieces = array_merge([$front, $back], $sleeves);

        foreach ($this->burkiniPants($measurements, $ease, $params) as $leg) {
            $pieces[] = $leg;
        }

        if ($this->flag($params, 'hood', true)) {
            $head = ((float) ($measurements['neck'] ?? 37)) * 1.5;

            $pieces[] = $this->bandPiece('burkini-hood', 'کلاه', $head * 0.62, 34, [
                'cut' => 2, 'part' => 'collar',
                'meta' => [
                    'girth_role' => 'trim',
                    'notions' => [[
                        'type' => 'elastic',
                        'label' => 'کش لبهٔ صورت',
                        'length' => round($head * 0.55, 1),
                    ]],
                    'notes' => [
                        'دو تکه که در مرکز به هم دوخته می‌شوند.',
                        'لبهٔ صورت کش می‌خورد، وگرنه کلاه در آب عقب می‌رود.',
                    ],
                ],
            ]);
        }

        $notes = array_merge($this->swimNotes($params), [
            'آستین و پاچه در مچ کش می‌خورند؛ هوای زیر لباس باید از دو سر بیرون برود.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }

    /**
     * شلوار بورکینی، از روی درفت آزمودهٔ ساپورت کاتالوگ.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function burkiniPants(array $measurements, array $ease, array $params): array
    {
        $generator = GeneratorRegistry::make('leggings');

        $built = $generator->generate($measurements, array_merge($ease, [
            'waist' => 2,
            'hip' => 3,
        ]), array_merge($generator->defaultParams(), array_filter([
            'length_extra' => null,
        ], fn ($v) => $v !== null)));

        $out = [];

        foreach ($built as $piece) {
            $piece['code'] = 'burkini-'.($piece['code'] ?? 'leg');
            $piece['name'] = 'شلوار بورکینی — '.($piece['name'] ?? '');
            $piece['meta']['swimwear'] = true;
            $piece['meta']['notes'][] = 'مچ پاچه کش می‌خورد.';
            $out[] = $piece;
        }

        return $out;
    }
}
