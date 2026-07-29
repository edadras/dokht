<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * گرمکن ورزشی (تاپ گرمکن).
 *
 * برخلاف بقیهٔ این دسته، گرمکن لایهٔ *رویی* است: از پارچهٔ بافته یا کشیِ سنگین
 * (تریکو دولا، ترنینگ) بریده می‌شود و آزادی‌اش مثبت است، چون روی رکابی و سوتین
 * ورزشی پوشیده می‌شود. اگر گرمکن هم مثل تایت با آزادی منفی بریده شود، اصلاً روی
 * لایهٔ زیر جا نمی‌شود.
 *
 * پس این مدل هیچ ضریب کشسانی اعلام نمی‌کند و به‌جای آن سه چیز دارد:
 *
 *   • زیپ سراسری جداشونده روی مرکز جلو، با طول واقعیِ اندازه‌گرفته‌شده در صورت مواد.
 *   • یقهٔ ایستادهٔ بلند که چانه را می‌پوشاند و ته زیپ روی آن می‌ایستد.
 *   • نوار کشباف دم و مچ، تا باد از پایین و از سرِ آستین تو نرود؛ لبه‌ها عمداً
 *     پهن‌تر از نوارشان بریده می‌شوند و اختلاف با کشیدن نوار جمع می‌شود.
 */
class ActiveTrackJacketGenerator extends ActiveBaseGenerator
{
    public static function key(): string
    {
        return 'active_track_jacket';
    }

    public function label(): string
    {
        return 'گرمکن ورزشی';
    }

    /** گرمکن بافته یا کشیِ سنگین است و آزادی مثبت دارد. */
    protected bool $negativeEase = false;

    public function paramsSchema(): array
    {
        return $this->activeSchema(
            ['neck_width_extra' => 1.5, 'front_neck_depth_extra' => 1, 'armhole_depth_extra' => 3],
            array_merge(
                $this->fitParam('regular'),
                [
                    'body_length' => [
                        'label' => 'بلندی از خط کمر', 'min' => 2, 'max' => 30, 'step' => 1,
                        'default' => 16, 'unit' => 'سانتی‌متر',
                        'hint' => 'نوار کشباف دم از همین بلندی برداشته می‌شود، پس بلندی تمام‌شده همین است.',
                    ],
                    'sleeve_length' => [
                        'label' => 'بلندی آستین', 'min' => 20, 'max' => 75, 'step' => 1,
                        'default' => 58, 'unit' => 'سانتی‌متر',
                    ],
                    'cap_ease' => [
                        'label' => 'آزادی سرآستین', 'min' => 0.5, 'max' => 5, 'step' => 0.25,
                        'default' => 2, 'unit' => 'سانتی‌متر',
                    ],
                    'collar_height' => [
                        'label' => 'بلندی یقه ایستاده', 'min' => 4, 'max' => 14, 'step' => 0.5,
                        'default' => 8, 'unit' => 'سانتی‌متر',
                    ],
                    'rib_height' => [
                        'label' => 'بلندی نوار کشباف دم و مچ', 'min' => 3, 'max' => 10, 'step' => 0.5,
                        'default' => 5, 'unit' => 'سانتی‌متر',
                    ],
                    'rib_stretch' => [
                        'label' => 'کوتاهی نوار کشباف', 'min' => 0.65, 'max' => 0.95, 'step' => 0.01,
                        'default' => 0.82,
                    ],
                    'zip_pockets' => [
                        'label' => 'جیب زیپ‌دار پهلو', 'type' => 'toggle', 'default' => true,
                    ],
                ],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        // گرمکن روی لایهٔ چسبان می‌نشیند، پس حتی در فرم «جذب» هم آزادی مثبت دارد
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 3.0, 'loose' => 5.0]);

        $rib = (float) $this->param($params, 'rib_height', 5);
        $stretch = (float) $this->param($params, 'rib_stretch', 0.82);
        $length = max(1.0, (float) $this->param($params, 'body_length', 16) - $rib);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'code' => 'track-jacket-front',
            'name' => 'تنه جلو',
            'meta' => ['front_opening' => 'zip'],
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'track-jacket-back',
            'name' => 'تنه پشت',
        ]));

        $front = $this->markFrontZip($front);

        $armhole = (float) ($front['meta']['armhole_length'] ?? 0) + (float) ($back['meta']['armhole_length'] ?? 0);
        $neck = ((float) ($front['meta']['neck_length'] ?? 0) + (float) ($back['meta']['neck_length'] ?? 0)) * 2;

        $sleeves = $this->sleevePieces($measurements, $ease, $params, [
            'armhole_length' => $armhole,
            'length' => max(14.0, (float) $this->param($params, 'sleeve_length', 58) - $rib),
            'sleeve_name' => 'آستین گرمکن',
            'prefix' => 'track-',
            'no_cuff' => true,
        ]);

        foreach ($sleeves as $index => $sleeve) {
            $sleeves[$index]['meta']['girth_role'] = 'sleeve';
        }

        $pieces = array_merge([$front, $back], $sleeves);

        $pieces[] = $this->standCollar($neck, (float) $this->param($params, 'collar_height', 8));

        $pieces[] = $this->edgeBinding(
            'track-jacket-hem-rib',
            'نوار کشباف دم',
            $this->edgeTotal($pieces, 'hem'),
            $rib,
            $stretch,
        );

        $cuff = (float) ($sleeves[0]['meta']['hem_width'] ?? 20);
        $pieces[] = $this->edgeBinding('track-jacket-cuff-rib', 'مچ کشباف آستین', $cuff, $rib, $stretch, 2);

        if ($this->flag($params, 'zip_pockets', true)) {
            $pieces[] = $this->pocketWelt();
            $pieces[] = $this->pocketBag();
        }

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            $this->shellNotes(),
            [
                'دم لباس و مچ آستین پیش از دوختن نوار، به اندازهٔ بلندی نوار کوتاه شده‌اند.',
                'یقهٔ ایستاده دولا بریده می‌شود؛ لبهٔ بالای زیپ زیر همان یقه پنهان می‌شود تا به چانه نخورد.',
            ],
        );

        return $this->finishBlock($pieces, $g, $grow);
    }

    /**
     * زیپ سراسری مرکز جلو.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markFrontZip(array $piece): array
    {
        [, $minY, , $maxY] = Geometry::bounds($piece['outline']);
        $zip = round($maxY - $minY, 1);

        if ($zip < 10) {
            return $piece;
        }

        $piece['markers'][] = $this->marker('zip', 'زیپ جداشونده مرکز جلو', 0, $minY, 0, $maxY);
        $piece['meta']['zip_length'] = $zip;
        $piece['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ جداشونده گرمکن',
            'count' => 1,
            'length' => $zip,
        ];

        return $piece;
    }

    /** یقهٔ ایستاده: نوار دولا به اندازهٔ دور یقه. */
    protected function standCollar(float $neck, float $height): array
    {
        $collar = $this->bandPiece('track-jacket-collar', 'یقه ایستاده', max(20.0, $neck), $height * 2, [
            'cut' => 2,
            'fold_line' => true,
            'part' => 'collar',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'target_neck' => round($neck, 2),
                'finished_height' => round($height, 2),
                'notes' => [
                    'دو تکه بریده می‌شود (رو و آستر یقه)؛ از خط تا برمی‌گردد و ته زیپ زیر آن پنهان می‌شود.',
                ],
            ],
        ]);

        $collar['meta']['edges'] = ['neck', 'side', 'default', 'side'];

        return $collar;
    }

    /** فیلتاب جیب زیپ‌دار پهلو. */
    protected function pocketWelt(): array
    {
        return $this->bandPiece('track-jacket-pocket-welt', 'فیلتاب جیب زیپ‌دار', 17, 4, [
            'cut' => 2,
            'part' => 'pocket',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notions' => [[
                    'type' => 'zip',
                    'label' => 'زیپ جیب پهلو',
                    'count' => 1,
                    'length' => 16.0,
                    'per_cut' => true,
                ]],
                'notes' => ['دهانهٔ جیب ۱۵ سانتی‌متر است و کمی اریب، هم‌راستای دست.'],
            ],
        ]);
    }

    /** کیسه جیب پهلو. */
    protected function pocketBag(): array
    {
        return $this->bandPiece('track-jacket-pocket-bag', 'کیسه جیب پهلو', 18, 32, [
            'cut' => 2,
            'part' => 'pocket',
            'meta' => [
                'girth_role' => 'trim',
                'notes' => ['از آستر یا همان پارچهٔ تنه؛ پشت فیلتاب دوخته می‌شود.'],
            ],
        ]);
    }
}
