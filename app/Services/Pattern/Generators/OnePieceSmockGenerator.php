<?php

namespace App\Services\Pattern\Generators;

/**
 * روپوش کار.
 *
 * تنها عضو این خانواده که پاچه ندارد: یک روپوش جلوباز و بلند تا زانو که روی
 * لباس پوشیده می‌شود تا آن را تمیز نگه دارد — روپوش آزمایشگاه، روپوش نقاشی،
 * روپوش کارگاه خیاطی.
 *
 * چون فاق ندارد، «رایز کل» هم ندارد و آزادی نشستن از جای دیگری می‌آید: چاک
 * پهلو. روپوشِ بلندِ بی‌چاک هنگام نشستن روی ران کشیده می‌شود و درزش می‌شکافد،
 * پس چاک این‌جا اختیاری نیست بلکه پیش‌فرض باز است.
 *
 * جلو با دکمه بسته می‌شود و چون درز کمر ندارد، اضافه جای دکمه بی‌خطر روی خود
 * تنه می‌نشیند (برخلاف بقیه این خانواده که خط کمر دارند).
 */
class OnePieceSmockGenerator extends OnePieceBaseGenerator
{
    public static function key(): string
    {
        return 'one_smock';
    }

    public function label(): string
    {
        return 'روپوش کار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->onePieceSchema([
                'shoulder_slope' => 3.5,
                'armhole_depth_extra' => 4,
                'neck_width_extra' => 1.5,
                'back_neck_depth' => 3,
            ]),
            $this->fitParam('loose'),
            $this->garmentLengthParam(52, 25, 95, 'بلندی از خط کمر (تا زانو)'),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین بلند',
                'none' => 'بی‌آستین (نوار حلقه)',
            ]),
            $this->openingParam('button', 3, [
                'button' => 'جلوباز با دکمه',
                'zip' => 'جلوباز با زیپ',
            ]),
            $this->collarParam('stand', [
                'stand' => 'یقه ایستاده',
                'turn' => 'یقه برگردان',
                'none' => 'بدون یقه (سجاف تمیزدوزی)',
            ], 5),
            [
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین', 'min' => 0, 'max' => 16, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'روپوش گشاد است؛ باز شدن زیاد روی بدنی که سینه‌اش از باسنش بزرگ‌تر است،'
                        .' لبه پایین را بی‌جهت پهن می‌کند.',
                ],
                'vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 22, 'unit' => 'سانتی‌متر',
                    'hint' => 'روپوش بلندِ بی‌چاک هنگام نشستن روی ران کشیده می‌شود.',
                ],
                'back_belt' => [
                    'label' => 'نیم‌کمربند پشت', 'type' => 'toggle', 'default' => false,
                ],
            ],
            $this->pocketParam(true, 17, 19),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 2.5, 'loose' => 3.5]);
        $length = (float) $this->param($params, 'length', 52);
        $vent = (float) $this->param($params, 'vent', 22);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'smock-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'front_name' => 'تنه جلو (جلوباز)',
            'back_name' => 'تنه پشت',
            'panel' => ['waist_dart' => false, 'bust_dart' => false],
            'facing' => true,
            'facing_width' => 9,
        ]);

        if ($vent > 1.0) {
            $pieces[0] = $this->markSideVent($pieces[0], $vent);
            $pieces[1] = $this->markSideVent($pieces[1], $vent);
        }

        if ($this->flag($params, 'back_belt', false)) {
            $pieces[] = $this->bandPiece(
                'smock-back-belt',
                'نیم‌کمربند پشت',
                ($g['quarter_waist'] + $grow) * 2,
                7,
                [
                    'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                    'meta' => ['notes' => ['روی خط کمرِ پشت دوخته می‌شود و روپوش گشاد را جمع می‌کند.']],
                ],
            );
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'smock-']));

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'روپوش پاچه ندارد، پس رایز کل هم ندارد؛ آزادی نشستن آن از چاک پهلو می‌آید.',
        ]);

        return $this->finishBlock($pieces, $g, $grow);
    }
}
