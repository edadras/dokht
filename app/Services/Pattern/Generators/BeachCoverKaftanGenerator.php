<?php

namespace App\Services\Pattern\Generators;

/**
 * کافتان ساحلی.
 *
 * روپوشی که روی مایو پوشیده می‌شود، نه یک لباس کامل. کافتانِ بلندِ کاتالوگ تا
 * میان ساق می‌آید و جلوش فقط یک چاک کوتاه دارد؛ این یکی عمداً برعکس است:
 *
 *   کوتاه — تا میان ران، چون قرار است روی مایو و لب ساحل پوشیده شود.
 *   بازِ گشاد — لبه پایین در هر پهلو خیلی بازتر می‌شود تا با باد بخورد و به تن
 *   خیس نچسبد.
 *   چاک یقه بلند — از گودی یقه تا میان سینه، با یک بند نازک که آن را جمع می‌کند.
 *   چاک پهلوی بلند — تا نزدیک خط باسن، تا هم راه رفتن راحت باشد و هم لباس روی
 *   مایوی خیس گیر نکند.
 *
 * تنه و آستین یک‌سره بریده می‌شوند تا کم‌ترین درز و کم‌ترین دورریز را بدهد؛ روی
 * پارچه نازک و نقش‌دار بهترین حالت را دارد.
 */
class BeachCoverKaftanGenerator extends BeachBaseGenerator
{
    public static function key(): string
    {
        return 'beach_cover_kaftan';
    }

    public function label(): string
    {
        return 'کافتان ساحلی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 2.5,
                'front_neck_depth_extra' => 3,
                'back_neck_depth' => 2.5,
                'armhole_depth_extra' => 4,
            ]),
            $this->garmentLengthParam(26, 8, 60, 'بلندی از خط کمر'),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 5, 'max' => 20, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 6, 'max' => 40, 'step' => 1,
                    'default' => 20, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 14, 'max' => 36, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 4, 'max' => 34, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
                'neck_slit' => [
                    'label' => 'بلندی چاک یقه', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
                'neck_tie' => [
                    'label' => 'بند جمع‌کننده چاک یقه', 'type' => 'toggle', 'default' => true,
                ],
                'tassels' => [
                    'label' => 'منگوله لبه پایین', 'type' => 'toggle', 'default' => false,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 5);
        $length = (float) $this->param($params, 'length', 26);
        $slit = (float) $this->param($params, 'neck_slit', 24);
        $vent = (float) $this->param($params, 'side_vent', 18);
        $flare = (float) $this->param($params, 'hem_flare', 16);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => (float) $this->param($params, 'underarm_drop', 11),
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 20),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 24),
            'sleeve_slope' => 5.0,
            'hem_flare' => $flare,
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'beach-kaftan-front',
            'name' => 'تنه و آستین جلو',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'beach-kaftan-back',
            'name' => 'تنه و آستین پشت',
        ]));

        if ($slit > 2) {
            $front['markers'][] = $this->marker(
                'slit',
                'چاک یقه',
                0,
                $g['front_neck_depth'],
                0,
                $g['front_neck_depth'] + $slit,
            );
            $front['meta']['neck_slit'] = round($slit, 2);
        }

        foreach ([&$front, &$back] as &$panel) {
            $panel['meta']['vent'] = round($vent, 2);
            $panel['meta']['notes'] = array_merge($panel['meta']['notes'] ?? [], [
                'چاک پهلو به بلندی '.$this->fa(round($vent)).' سانتی‌متر از لبه پایین باز می‌ماند.',
                'لبه پایین در هر پهلو '.$this->fa(round($flare)).' سانتی‌متر بازتر از خط سینه است.',
            ]);
        }

        unset($panel);

        $halfNeck = $this->neckOf([$front, $back]);
        $pieces = [$front, $back];

        $pieces[] = $this->bandPiece(
            'beach-kaftan-neck-binding',
            'نوار اریب یقه و چاک',
            (2 * $halfNeck) + (2 * $slit) + 8,
            4,
            [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'bias' => true,
                    'girth_role' => 'trim',
                    'target_neck' => round(2 * $halfNeck, 2),
                    'notes' => ['نوار اریب که دور یقه و دو لبه چاک را می‌پوشاند؛ روی پارچه نازک از سجاف بهتر می‌خوابد.'],
                ],
            ],
        );

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'beach-kaftan-', 'width' => 6]);

        if ($this->flag($params, 'neck_tie', true)) {
            $pieces[] = $this->bandPiece('beach-kaftan-neck-tie', 'بند چاک یقه', 70, 4, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['دو بند نازک روی سرِ چاک یقه دوخته می‌شود و جلو را جمع می‌کند.'],
                ],
            ]);
        }

        if ($this->flag($params, 'tassels', false)) {
            $pieces[0] = $this->addNotion(
                $pieces[0],
                ['type' => 'other', 'label' => 'منگوله لبه پایین', 'count' => 12],
                'منگوله‌ها با فاصله برابر روی لبه پایین دوخته می‌شوند.',
            );
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->beachNotes([
            'لبه پایین '.$this->fa($this->hemFromShoulder($front)).' سانتی‌متر پایین‌تر از سرشانه می‌ایستد.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
