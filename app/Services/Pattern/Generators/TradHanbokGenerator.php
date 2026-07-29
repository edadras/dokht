<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس الهام‌گرفته از هانبوک.
 *
 * هانبوک دو تکه است و نسبت همین دو تکه است که شکلش را می‌سازد:
 *
 *   چوگوری — بالاتنه‌ای بسیار کوتاه که درست زیر سینه تمام می‌شود، آستینش با تنه
 *   یک‌سره بریده می‌شود و لبه پایین آستین منحنی ملایمی دارد (بَرِه). جلوی آن با
 *   یک نوار یقه پهن (گیت) بسته می‌شود و با دو بند پهن (گورِم) گره می‌خورد، نه با
 *   دکمه.
 *
 *   چیما — دامن بلند و بسیار چین‌دار که از «زیر سینه» شروع می‌شود، نه از کمر.
 *   بالای آن یک نوار پهن سینه‌بند (مالگی) است که دور قفسه سینه بسته می‌شود و دو
 *   بند از روی شانه نگهش می‌دارد. همین بالا بودن خط کمر است که سایه هانبوک را
 *   می‌سازد؛ اگر دامن از کمر شروع شود دیگر هانبوک نیست.
 *
 * چوگوری در این الگو با تنه یک‌سره بریده می‌شود (کم‌درزترین و کم‌دورریزترین
 * حالت). اگر پارچه باریک است، آستین را از خط سرشانه جدا کنید و با درز به تنه
 * بدوزید؛ شکل بیرونی عوض نمی‌شود.
 */
class TradHanbokGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_hanbok';
    }

    public function label(): string
    {
        return 'لباس الهام‌گرفته از هانبوک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 3,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 5,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 2,
            ]),
            [
                'jeogori_length' => [
                    'label' => 'بلندی چوگوری از خط کمر', 'min' => -22, 'max' => -2, 'step' => 1,
                    'default' => -14, 'unit' => 'سانتی‌متر',
                    'hint' => 'عدد منفی است چون چوگوری بالای خط کمر تمام می‌شود؛ منفی ۱۴ یعنی درست زیر سینه.',
                ],
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 1, 'max' => 8, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'overlap' => [
                    'label' => 'رویهم‌آمدن جلوی چوگوری', 'min' => 6, 'max' => 24, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                    'hint' => 'نیمه راست روی نیمه چپ می‌افتد و با بند گره می‌خورد.',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 12, 'max' => 50, 'step' => 1,
                    'default' => 32, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 12, 'max' => 34, 'step' => 1,
                    'default' => 20, 'unit' => 'سانتی‌متر',
                ],
                'collar_width' => [
                    'label' => 'پهنای نوار یقه (گیت)', 'min' => 3, 'max' => 9, 'step' => 0.5,
                    'default' => 5.5, 'unit' => 'سانتی‌متر',
                ],
                'tie_width' => [
                    'label' => 'پهنای بند گره (گورِم)', 'min' => 3, 'max' => 12, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'chima_length' => [
                    'label' => 'بلندی چیما از زیر سینه', 'min' => 60, 'max' => 135, 'step' => 1,
                    'default' => 105, 'unit' => 'سانتی‌متر',
                ],
                'chima_fullness' => [
                    'label' => 'نسبت پُری چین چیما', 'min' => 1.6, 'max' => 3.2, 'step' => 0.1,
                    'default' => 2.6,
                    'hint' => 'چیمای کم‌چین، هانبوک را لاغر و بی‌فرم نشان می‌دهد.',
                ],
                'bodice_band' => [
                    'label' => 'بلندی سینه‌بند چیما (مالگی)', 'min' => 8, 'max' => 26, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 2.5);
        $overlap = (float) $this->param($params, 'overlap', 12);
        $drop = 2.0;

        // چوگوری زیر سینه تمام می‌شود، ولی روی بدنی با تنه کوتاه نباید بالاتر از
        // خط زیر بغل بیفتد؛ وگرنه درز پهلو طول منفی می‌گیرد و قطعه بریده نمی‌شود.
        $floor = ($g['bust_y'] + $drop + 5) - $g['side_waist_y'];
        $length = max((float) $this->param($params, 'jeogori_length', -14), $floor);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => $drop,
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 32),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 20),
            'sleeve_slope' => 3.0,
            'hem_flare' => 0.0,
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $overlap,
            'on_fold' => false,
            'cut' => 2,
            'code' => 'hanbok-jeogori-front',
            'name' => 'چوگوری — جلو (با رویهم‌آمدن)',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'hanbok-jeogori-back',
            'name' => 'چوگوری — پشت',
        ]));

        $front['meta']['wrap_overlap'] = round($overlap, 2);
        $front['meta']['notes'] = array_merge($front['meta']['notes'] ?? [], [
            'نیمه راست روی نیمه چپ می‌افتد؛ رویهم‌آمدن '.$this->fa(round($overlap))
                .' سانتی‌متر است و با بند گره می‌خورد، نه با دکمه.',
        ]);

        $halfNeck = $this->neckOf([$front, $back]);
        $collarWidth = (float) $this->param($params, 'collar_width', 5.5);
        $tieWidth = (float) $this->param($params, 'tie_width', 7);

        $pieces = [$front, $back];

        $pieces[] = $this->bandPiece(
            'hanbok-git',
            'نوار یقه (گیت)',
            (2 * $halfNeck) + (2 * $overlap) + 6,
            $collarWidth * 2,
            [
                'cut' => 1, 'part' => 'collar', 'fold_line' => true,
                'meta' => [
                    'interfacing' => true,
                    'girth_role' => 'trim',
                    'target_neck' => round(2 * $halfNeck, 2),
                    'notes' => [
                        'از سرِ رویهم‌آمدنِ یک طرف، دور یقه و تا سرِ رویهم‌آمدن طرف دیگر می‌رود.',
                        'با لایی سفت بریده می‌شود؛ گیتِ نرم روی گردن می‌خوابد و هانبوک را بی‌فرم می‌کند.',
                    ],
                ],
            ],
        );

        $pieces[] = $this->bandPiece(
            'hanbok-dongjeong',
            'نوار سفید روی یقه (دونگ‌جونگ)',
            (2 * $halfNeck) + (2 * $overlap) + 6,
            3,
            [
                'cut' => 1, 'part' => 'collar',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['نوار باریک سفید که روی گیت کوک می‌شود و برای شستن باز می‌گردد.'],
                ],
            ],
        );

        foreach ([['long', 'بند گره بلند', 100.0], ['short', 'بند گره کوتاه', 70.0]] as [$code, $name, $len]) {
            $pieces[] = $this->bandPiece('hanbok-goreum-'.$code, $name.' (گورِم)', $len, $tieWidth * 2, [
                'cut' => 1, 'part' => 'belt', 'fold_line' => true,
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['بند پهن هانبوک؛ گره تکی روی سینه راست بسته می‌شود.'],
                ],
            ]);
        }

        foreach ($this->chimaPieces($measurements, $g, $params) as $piece) {
            $pieces[] = $piece;
        }

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($front),
            'hem_at' => 'درست زیر سینه',
            'sleeve' => $this->fa(round((float) $this->param($params, 'sleeve_length', 32)))
                .' سانتی‌متر پس از زیر بغل، یعنی حدود آرنج تا مچ',
            'neck' => 'باز و هفتی، با نوار یقه پهن',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'آستین چوگوری با تنه یک‌سره بریده شده؛ اگر پارچه باریک است از خط سرشانه جدایش کنید.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }

    /**
     * چیما: سینه‌بند، دو پنل چین‌دار و دو بند شانه.
     *
     * خط بالای دامن روی زیر سینه می‌نشیند (نه روی کمر) و پارچه‌اش چند برابر دور
     * زیر سینه است؛ همین چین است که چیما را پُر می‌کند.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function chimaPieces(array $m, array $g, array $params): array
    {
        $underBust = $this->m($m, 'under_bust', $this->m($m, 'bust', 92) - 14);
        $ratio = max(1.4, (float) $this->param($params, 'chima_fullness', 2.6));
        $length = max(40.0, (float) $this->param($params, 'chima_length', 105));
        $band = (float) $this->param($params, 'bodice_band', 16);

        $quarter = ($underBust / 4) + 1;
        $gather = round($quarter * ($ratio - 1), 2);
        $pieces = [];

        foreach ([['front', 'جلو'], ['back', 'پشت']] as [$side, $name]) {
            $panel = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'straight',
                'top_width' => $quarter,
                'top_y' => $g['under_bust_y'],
                'length' => $length,
                'gather' => $gather,
                'flare' => 4,
                'top_tag' => 'waist',
                'code' => 'hanbok-chima-'.$side,
                'name' => 'چیما — '.$name,
                'meta' => [
                    'gather_ratio' => round($ratio, 2),
                    'notes' => [
                        'لبه بالای این پنل '.$this->fa(round($ratio, 1))
                            .' برابر لبه سینه‌بند است و با چین ریز روی آن جمع می‌شود.',
                        'خط بالای دامن روی زیر سینه می‌نشیند، نه روی کمر.',
                    ],
                ],
            ]);

            $pieces[] = $this->recordGathers($panel, $gather, 'چین چیما روی سینه‌بند');
        }

        $pieces[] = $this->bandPiece('hanbok-malgi', 'سینه‌بند چیما (مالگی)', ($underBust / 2) + 8, $band * 2, [
            'cut' => 2, 'part' => 'waistband', 'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'target_girth' => round($underBust, 2),
                'notes' => [
                    'دور قفسه سینه بسته می‌شود و دامن با چین روی آن می‌نشیند.',
                    'دو تکه که در پهلو به هم می‌رسند؛ هشت سانتی‌متر رویهم‌آمدن برای بستن دارد.',
                ],
            ],
        ]);

        $pieces[] = $this->bandPiece('hanbok-chima-strap', 'بند شانه چیما', 60, 8, [
            'cut' => 2, 'part' => 'strap', 'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'notes' => ['از جلو روی شانه به پشت می‌رود و سینه‌بند را بالا نگه می‌دارد؛ در پرو کوتاه می‌شود.'],
            ],
        ]);

        return $pieces;
    }
}
