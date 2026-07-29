<?php

namespace App\Services\Pattern\Generators;

/**
 * ترنچ‌کت.
 *
 * پالتوی بارانیِ دوطرفه‌دکمه با نشانه‌های خودش: سرشانه‌بند، لتِ سینه که آب باران
 * را از درز دور می‌کند، کمربند پارچه‌ای با حلقه، و چاک بلند مرکز پشت. آستین
 * دوتکه است و مچ‌بند تسمه‌ای دارد.
 */
class CoatTrenchGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'coat_trench';
    }

    public function label(): string
    {
        return 'ترنچ‌کت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 5, 'neck_width_extra' => 2.5, 'front_neck_depth_extra' => 3]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(70, 30, 120),
            $this->openingParam('button', 7),
            $this->collarParam('turn', [
                'turn' => 'یقه برگردان',
                'shawl' => 'یقه شالی',
            ], 9),
            $this->sleeveParam('two_piece', 60),
            [
                'button_rows' => [
                    'label' => 'تعداد دکمه در هر رج', 'min' => 2, 'max' => 6, 'step' => 1, 'default' => 4,
                ],
                'storm_flap' => [
                    'label' => 'لتِ سینه (استورم‌فلپ)', 'type' => 'toggle', 'default' => true,
                ],
                'epaulette' => [
                    'label' => 'سرشانه‌بند', 'type' => 'toggle', 'default' => true,
                ],
                'belt_width' => [
                    'label' => 'پهنای کمربند', 'min' => 3, 'max' => 8, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 25, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'back_vent' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 55, 'step' => 1,
                    'default' => 34, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 16, 18),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.5, 'regular' => 3.0, 'loose' => 5.0]);
        $overlap = (float) $this->param($params, 'button_stand', 7);
        $length = (float) $this->param($params, 'length', 70);
        $rows = (int) $this->param($params, 'button_rows', 4);
        $beltWidth = (float) $this->param($params, 'belt_width', 5);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'trench-',
            'grow' => $grow,
            'shape' => 'fitted',
            'stand' => $overlap,
            'back_seam' => true,
            'bust_dart' => true,
            'buttons' => $rows,
            'front_name' => 'تنه جلوی ترنچ',
            'back_name' => 'تنه پشت ترنچ (درز مرکزی)',
            'facing_width' => max(10.0, $overlap + 5),
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 3), 'back_pleat' => 2.5],
        ]);

        // رج دوم دکمه روی لبه هم‌پوشانی
        $top = $g['bust_y'] + 2;
        $step = $rows > 1 ? ($g['front_waist_y'] - 2 - $top) / ($rows - 1) : 0.0;

        for ($i = 0; $i < $rows; $i++) {
            $pieces[0]['drills'][] = [
                'key' => 'button_right_'.($i + 1),
                'label' => 'دکمه رج راست '.$this->fa($i + 1),
                'x' => round($overlap * 2, 2),
                'y' => round($top + ($step * $i), 2),
            ];
        }

        $pieces[0]['meta']['double_breasted'] = true;
        $pieces[0]['meta']['buttons'] = $rows * 2;
        $pieces[0]['meta']['notions'][] = [
            'type' => 'button',
            'label' => 'دکمه دوردیفه',
            'count' => $rows * 2,
        ];

        if ($this->flag($params, 'storm_flap', true)) {
            $pieces[] = $this->bandPiece('trench-storm-flap', 'لتِ سینه', $g['quarter_bust'] + $overlap, $g['bust_y'] + 6, [
                'cut' => 1, 'part' => 'facing',
                'meta' => ['interfacing' => true, 'notes' => ['روی شانه راست دوخته می‌شود و لبه پایین آن آزاد می‌ماند.']],
            ]);
        }

        if ($this->flag($params, 'epaulette', true)) {
            $pieces[] = $this->bandPiece('trench-epaulette', 'سرشانه‌بند', $g['shoulder_half'] - 2, 5, [
                'cut' => 4, 'part' => 'belt',
                'meta' => ['interfacing' => true, 'notes' => ['دو عدد از دو لایه دوخته می‌شود؛ سرِ آن در درز سرشانه گرفته می‌آید.']],
            ]);
        }

        $pieces[] = $this->beltPiece($measurements, $params, ['prefix' => 'trench-', 'width' => $beltWidth, 'tie' => 45]);
        $pieces[] = $this->beltLoopPiece($beltWidth, ['prefix' => 'trench-', 'cut' => 2]);
        $pieces[] = $this->bandPiece('trench-sleeve-strap', 'تسمه مچ آستین', 26, 4, [
            'cut' => 2, 'part' => 'cuff',
            'meta' => ['notes' => ['دور مچ آستین بسته می‌شود و با سگک تنظیم می‌گردد.']],
        ]);

        $vent = (float) $this->param($params, 'back_vent', 34);

        if ($vent > 1) {
            $pieces[1]['meta']['back_vent'] = round($vent, 2);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'trench-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
