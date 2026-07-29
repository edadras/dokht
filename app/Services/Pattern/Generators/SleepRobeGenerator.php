<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * روب دوشی.
 *
 * روبی که پس از حمام یا روی پیژامه می‌پوشند: جلوباز، بی دکمه و بی زیپ، با نوار
 * یک‌سره‌ای که از دم لباس تا پشت گردن و تا دم لباسِ سمت دیگر می‌رود، و بند کمری
 * که از دو حلقه روی درز پهلو می‌گذرد.
 *
 * روب از پارچهٔ **بافته**ٔ ضخیم بریده می‌شود — حوله‌ای، فلانل، خز — و همین چند
 * چیز را تحمیل می‌کند:
 *
 *   - **آزادی زیاد و مثبت.** روب روی لباس دیگری پوشیده می‌شود و باید رویش جا
 *     داشته باشد؛ پارچهٔ ضخیم هم خودش جا می‌خورد.
 *   - **هم‌پوشانی جلو.** جلو با هیچ بستی بسته نمی‌شود؛ فقط رویهم آمدن و بند کمر
 *     نگهش می‌دارند. پس هم‌پوشانی باید سخاوتمند باشد، وگرنه روب باز می‌ماند.
 *   - **حلقهٔ بند روی درز پهلو.** بندِ بی‌حلقه در چند بار پوشیدن گم می‌شود؛ این
 *     دو تکهٔ کوچک همان چیزی است که روبِ خانگی را کاربردی نگه می‌دارد.
 *   - **جیب کیسه‌ای بزرگ.** روب بی‌جیب، روبی است که دستمال و موبایل جایی ندارند.
 */
class SleepRobeGenerator extends SleepwearBaseGenerator
{
    /** پارچهٔ بافتهٔ ضخیم: آزادی مثبت، بی هیچ مهرِ کشسانی. */
    protected bool $negativeEase = false;

    public static function key(): string
    {
        return 'sleep_robe';
    }

    public function label(): string
    {
        return 'روب دوشی';
    }

    public function paramsSchema(): array
    {
        return $this->wovenSchema([
            'length' => [
                'label' => 'بلندی از خط کمر', 'min' => 20, 'max' => 110, 'step' => 1,
                'default' => 55, 'unit' => 'سانتی‌متر',
                'hint' => 'پنجاه و پنج سانتی‌متر روی بدن بزرگسال تا بالای زانو می‌آید.',
            ],
            'overlap' => [
                'label' => 'هم‌پوشانی جلو', 'min' => 5, 'max' => 24, 'step' => 0.5,
                'default' => 12, 'unit' => 'سانتی‌متر',
                'hint' => 'روب با هیچ بستی بسته نمی‌شود؛ هم‌پوشانیِ کم یعنی روب باز می‌ماند.',
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین از سرشانه', 'min' => 10, 'max' => 75, 'step' => 1,
                'default' => 52, 'unit' => 'سانتی‌متر',
            ],
            'band_width' => [
                'label' => 'پهنای نوار لبهٔ جلو و یقه', 'min' => 3, 'max' => 12, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
            ],
            'belt_width' => [
                'label' => 'پهنای بند کمر', 'min' => 3, 'max' => 10, 'step' => 0.5,
                'default' => 5, 'unit' => 'سانتی‌متر',
            ],
            'pockets' => [
                'label' => 'جیب کیسه‌ای', 'type' => 'toggle', 'default' => true,
            ],
        ], grow: 4);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = (float) $this->param($params, 'ease_extra', 4);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 55);
        $overlap = (float) $this->param($params, 'overlap', 12);
        $bandWidth = (float) $this->param($params, 'band_width', 6);
        $beltWidth = (float) $this->param($params, 'belt_width', 5);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'armhole_drop' => 4.0,
            'neck_width_extra' => 1.5,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $overlap,
            'on_fold' => false,
            'cut' => 2,
            'code' => 'robe-front',
            'name' => 'روب — جلو',
            // یقهٔ جلو تا نزدیک خط سینه باز می‌شود؛ نوار یک‌سره همان‌جا می‌نشیند
            'neck_depth_extra' => max(0.0, ($g['bust_y'] * 0.55) - $g['front_neck_depth']),
            'meta' => ['wrap_overlap' => round($overlap, 2)],
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'robe-back',
            'name' => 'روب — پشت',
        ]));

        $body = [$front, $back];
        $pieces = $body;

        foreach ($this->sleeveSet($measurements, $ease, $params, $body, [
            'length' => (float) $this->param($params, 'sleeve_length', 52),
            'prefix' => 'robe-',
            'sleeve_name' => 'آستین روب',
        ]) as $sleeve) {
            $sleeve['meta']['notes'][] = 'دم آستین ده سانتی‌متر برگردان دارد؛ آستین روب معمولاً بالا زده می‌شود.';
            $pieces[] = $sleeve;
        }

        // نوار یک‌سره: از دم لباسِ یک طرف، دور یقه، تا دم لباسِ طرف دیگر
        $edge = Geometry::height($front['outline']) + (float) ($back['meta']['neck_length'] ?? 8);

        $pieces[] = $this->bandPiece(
            'robe-band',
            'نوار یک‌سرهٔ لبهٔ جلو و یقه',
            max(30.0, $edge),
            $bandWidth * 2,
            [
                'cut' => 2,
                'fold_line' => true,
                'part' => 'placket',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => [
                        'دو نوار در مرکز پشت گردن به هم دوخته می‌شوند و یک نوار پیوسته می‌سازند.',
                        'طولش از بلندی لبهٔ جلو به‌علاوهٔ نیم‌یقهٔ پشت گرفته شده، نه از عددی حدسی.',
                        'روی خط تا اتو بزنید و لبهٔ جلو را در آن بگیرید؛ نوارِ لایی‌نخورده روی حوله‌ای موج می‌افتد.',
                    ],
                ],
            ],
        );

        $pieces[] = $this->beltPiece($measurements, $beltWidth);
        $pieces[] = $this->beltLoopPiece($beltWidth);

        if ($this->flag($params, 'pockets', true)) {
            $pieces[] = $this->robePocket();
        }

        return $this->finishSleepwear($pieces, $this->sleepNotes($params, [
            'هم‌پوشانی جلو '.$this->fa($overlap).' سانتی‌متر است و در اندازهٔ دور بدن حساب نشده؛'
                .' رویهم آمدن پارچه است، نه گشادی لباس.',
            'روب هیچ بستی ندارد: فقط هم‌پوشانی و بند کمر نگهش می‌دارند.',
            'بند از دو حلقه روی درز پهلو رد می‌شود؛ بندِ بی‌حلقه در چند بار پوشیدن گم می‌شود.',
        ]));
    }

    /** بند کمر روب: نواری دولا و بلند. */
    protected function beltPiece(array $m, float $width): array
    {
        $length = ($this->m($m, 'waist', 74) * 1.8) + 60;

        return $this->bandPiece('robe-belt', 'بند کمر', $length, $width * 2, [
            'cut' => 1,
            'fold_line' => true,
            'part' => 'belt',
            'meta' => [
                'girth_role' => 'trim',
                'notes' => [
                    'یک دور و نیمِ دور کمر به‌علاوهٔ شصت سانتی‌متر برای گره؛ روب را با گره می‌بندند نه با سگک.',
                    'اگر پارچه کوتاه آمد، بند را از دو تکه ببرید و درزش را پشت بگذارید.',
                ],
            ],
        ]);
    }

    /** حلقهٔ بند روی درز پهلو. */
    protected function beltLoopPiece(float $width): array
    {
        return $this->bandPiece('robe-belt-loop', 'حلقهٔ بند کمر', $width + 4, 4.0, [
            'cut' => 2,
            'part' => 'loop',
            'meta' => [
                'girth_role' => 'trim',
                'notes' => [
                    'روی درز پهلو، هم‌تراز خط کمر دوخته می‌شود.',
                    'دو سرش در خودِ درز پهلو گرفته می‌شود تا از رو دیده نشود.',
                ],
            ],
        ]);
    }

    /** جیب کیسه‌ای روب. */
    protected function robePocket(): array
    {
        $width = 17.0;
        $height = 19.0;

        return $this->piece([
            'code' => 'robe-pocket',
            'name' => 'جیب کیسه‌ای',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height - 3.5),
                Geometry::curve($width * 0.5, $height, $width * 0.86, $height),
                Geometry::point(0, $height - 3.5),
            ],
            'grainline' => $this->grainline($width * 0.5, 1, $height - 5),
            'markers' => [
                $this->marker('fold', 'خط تای دهانهٔ جیب', 0, 4.0, $width),
            ],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'hem', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'دهانهٔ جیب چهار سانتی‌متر برمی‌گردد و دو بار دوخته می‌شود؛ جیب روب زیر وزن دست کشیده می‌شود.',
                    'گوشهٔ پایین گرد است تا در اتو و شست‌وشو گوشهٔ کلفت نسازد.',
                ],
            ],
        ]);
    }
}
