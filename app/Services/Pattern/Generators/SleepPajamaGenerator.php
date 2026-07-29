<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیژامه (بالا و پایین).
 *
 * دو تکهٔ جدا که با هم یک دست‌اند: بالاتنهٔ جلوباز با دکمه و آستین ست‌این، و
 * شلوار کمرکشی.
 *
 * پیژامه از پارچهٔ **بافته** بریده می‌شود — فلانل، پوپلین، ویسکوز — و هیچ کششی
 * ندارد. این یک جمله همهٔ تفاوتش با لباس زیر و لباس خواب کشی است:
 *
 *   - آزادی **مثبت** می‌گیرد، نه منفی. هرچه لازم است در خواب بچرخد باید از
 *     آزادی بیاید، چون پارچه کش نمی‌آید.
 *   - حلقه آستین گودتر و آستین پهن‌تر گرفته می‌شود؛ آدم خوابیده دستش را بالای سر
 *     می‌برد و حلقهٔ تنگ همان‌جا می‌کشد.
 *   - جلو با دکمه باز می‌شود، پس اضافهٔ جای دکمه دارد و روی تای پارچه نمی‌رود.
 *   - پایینش شلوار است و همین‌جا هم شلوار نامیده می‌شود: از درفت شلوار کمرکشی
 *     کاتالوگ می‌آید، با پاچه و درز داخل پا و منحنی فاق واقعی.
 */
class SleepPajamaGenerator extends SleepwearBaseGenerator
{
    /** پارچهٔ بافته: آزادی مثبت، بی هیچ مهرِ کشسانی. */
    protected bool $negativeEase = false;

    public static function key(): string
    {
        return 'sleep_pajama';
    }

    public function label(): string
    {
        return 'پیژامه (بالا و پایین)';
    }

    public function paramsSchema(): array
    {
        return $this->wovenSchema([
            'top_length' => [
                'label' => 'بلندی بالاتنه از خط کمر', 'min' => 2, 'max' => 30, 'step' => 1,
                'default' => 14, 'unit' => 'سانتی‌متر',
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین از سرشانه', 'min' => 0, 'max' => 70, 'step' => 1,
                'default' => 26, 'unit' => 'سانتی‌متر',
                'hint' => 'صفر یعنی بی‌آستین؛ بیست و شش سانتی‌متر آستین کوتاه است.',
            ],
            'button_stand' => [
                'label' => 'اضافه جای دکمه', 'min' => 1.5, 'max' => 5, 'step' => 0.5,
                'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'buttons' => [
                'label' => 'تعداد دکمه', 'min' => 3, 'max' => 8, 'step' => 1, 'default' => 5,
            ],
            'collar_height' => [
                'label' => 'بلندی یقهٔ ایستاده', 'min' => 0, 'max' => 8, 'step' => 0.5,
                'default' => 3.5, 'unit' => 'سانتی‌متر',
                'hint' => 'صفر یعنی یقه با نوار اریب تمام می‌شود.',
            ],
            'chest_pocket' => [
                'label' => 'جیب روی سینه', 'type' => 'toggle', 'default' => true,
            ],
            'pants_length' => [
                'label' => 'تغییر قد شلوار', 'min' => -50, 'max' => 10, 'step' => 1,
                'default' => -4, 'unit' => 'سانتی‌متر',
                'hint' => 'نسبت به قد داخل پای اندازه‌گرفته‌شده؛ منفی یعنی کوتاه‌تر.',
            ],
        ], grow: 3.5);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = (float) $this->param($params, 'ease_extra', 3.5);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $stand = (float) $this->param($params, 'button_stand', 2.5);
        $length = (float) $this->param($params, 'top_length', 14);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            // آدم خوابیده دست را بالای سر می‌برد؛ حلقهٔ گودتر و بازتر همان‌جا
            // جلوی کشیدن پارچه را می‌گیرد
            'armhole_drop' => 3.0,
            'neck_width_extra' => 0.8,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'code' => 'pajama-top-front',
            'name' => 'بالاتنهٔ پیژامه — جلو',
            'neck_depth_extra' => 1.0,
            'meta' => ['button_stand' => round($stand, 2)],
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'pajama-top-back',
            'name' => 'بالاتنهٔ پیژامه — پشت',
        ]));

        $front = $this->markPajamaButtons($front, $g, $stand, (int) $this->param($params, 'buttons', 5), $length);

        $body = [$front, $back];
        $pieces = $body;

        foreach ($this->sleeveSet($measurements, $ease, $params, $body, [
            'length' => (float) $this->param($params, 'sleeve_length', 26),
            'prefix' => 'pajama-',
            'sleeve_name' => 'آستین پیژامه',
        ]) as $sleeve) {
            $sleeve['meta']['notes'][] = 'آستین روی حلقهٔ همین درفت پیاده شده است؛ اگر یقه یا حلقه را عوض کردید، آستین را هم دوباره بسنجید.';
            $pieces[] = $sleeve;
        }

        $collar = (float) $this->param($params, 'collar_height', 3.5);

        if ($collar > 0.4) {
            $pieces[] = $this->bandPiece(
                'pajama-collar',
                'یقهٔ ایستاده',
                $this->neckHalfOf($body) + $stand,
                $collar,
                [
                    'cut' => 2,
                    'fold_line' => true,
                    'part' => 'collar',
                    'meta' => [
                        'girth_role' => 'trim',
                        'interfacing' => true,
                        'notes' => [
                            'طولش از خودِ یقهٔ جلو و پشت اندازه گرفته شده، به‌علاوهٔ اضافهٔ جای دکمه.',
                            'یک لایه لایی چسب روی تکهٔ بیرونی بزنید؛ یقهٔ بی‌لایی روی گردن می‌خوابد.',
                        ],
                    ],
                ],
            );
        }

        if ($this->flag($params, 'chest_pocket', true)) {
            $pieces[] = $this->pajamaPocket();
        }

        foreach ($this->bottomFrom('pants_elastic_waist', $measurements, $ease, $params, [
            'prefix' => 'pajama',
            'params' => [
                'length_extra' => (float) $this->param($params, 'pants_length', -4),
                'thigh_ease' => 14,
                'knee_ease' => 14,
                'hem_ease' => 12,
                'band_stretch' => 0.86,
            ],
            'notes' => [
                'پایین پیژامه یک شلوار کمرکشی واقعی است: درز داخل پا، منحنی فاق و پاچهٔ کامل دارد.',
                'کمر هم کش دارد هم بند؛ کشِ تنها در خواب می‌چرخد و بندِ تنها باز می‌شود.',
            ],
        ]) as $piece) {
            $pieces[] = $piece;
        }

        $pieces[] = $this->drawcordPiece($measurements, $params);

        return $this->finishSleepwear($pieces, $this->sleepNotes($params, [
            'آزادی سینهٔ این پیژامه '.$this->fa(round(4 * $grow)).' سانتی‌متر بیشتر از بلوک پایه است؛'
                .' پارچهٔ بافته کش نمی‌آید و این آزادی جای همان کشش را می‌گیرد.',
            'بالاتنه و شلوار دو تکهٔ جدا هستند و به هم دوخته نمی‌شوند؛ لبهٔ پایین بالاتنه دم لباس است نه خط کمر.',
        ]));
    }

    /**
     * جای دکمه‌ها روی مرکز جلو.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, float>  $g
     * @return array<string, mixed>
     */
    protected function markPajamaButtons(array $piece, array $g, float $stand, int $count, float $length): array
    {
        [$minX, $minY, , $maxY] = Geometry::bounds($piece['outline']);

        $x = $minX + $stand;
        $from = $minY + max(1.5, (float) $g['front_neck_depth'] * 0.5);
        $to = $maxY - 4.0;

        if ($to - $from < 6.0 || $count < 2) {
            return $piece;
        }

        $step = ($to - $from) / ($count - 1);

        for ($i = 0; $i < $count; $i++) {
            $piece['drills'][] = [
                'key' => 'button_'.($i + 1),
                'label' => 'دکمه '.$this->fa($i + 1),
                'x' => round($x, 2),
                'y' => round($from + ($step * $i), 2),
            ];
        }

        $piece['meta']['notions'][] = [
            'type' => 'button',
            'label' => 'دکمهٔ جلو',
            'count' => $count,
        ];

        $piece['meta']['notes'][] = 'جای دکمه‌ها روی خط مرکز جلو است، نه روی لبهٔ اضافه؛'
            .' لبهٔ اضافه '.$this->fa($stand).' سانتی‌متر است و همان مقدار روی هم می‌آید.';

        return $piece;
    }

    /** طول نیم‌یقه (جلو + پشت) از روی خودِ پنل‌ها. */
    protected function neckHalfOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $total += (float) ($piece['meta']['neck_length'] ?? 0);
        }

        return round(max(10.0, $total), 2);
    }

    /** جیب سینه: مستطیلی با گوشهٔ پایین بریده. */
    protected function pajamaPocket(): array
    {
        $width = 11.5;
        $height = 12.5;

        return $this->piece([
            'code' => 'pajama-chest-pocket',
            'name' => 'جیب سینه',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height - 3.0),
                Geometry::point($width * 0.5, $height),
                Geometry::point(0, $height - 3.0),
            ],
            'grainline' => $this->grainline($width * 0.5, 1, $height - 4),
            'markers' => [
                $this->marker('fold', 'خط تای دهانهٔ جیب', 0, 3.0, $width),
            ],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'hem', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'دهانهٔ جیب سه سانتی‌متر برمی‌گردد و دو بار دوخته می‌شود.',
                    'گوشهٔ پایین بریده است تا اتوی جیب گوشهٔ کلفت نسازد.',
                ],
            ],
        ]);
    }

    /** بند کمر شلوار پیژامه. */
    protected function drawcordPiece(array $m, array $params): array
    {
        $waist = $this->m($m, 'waist', 74);

        return $this->strapPiece($waist + 60, 1.2, [
            'code' => 'pajama-drawcord',
            'name' => 'بند کمر شلوار',
            'cut' => 1,
            'meta' => [
                'notions' => [[
                    'type' => 'eyelet',
                    'label' => 'جادکمه‌ای بند کمر',
                    'count' => 2,
                ]],
                'notes' => [
                    'از دو جادکمه‌ای جلوی کمر بیرون می‌آید و گره می‌خورد.',
                    'شصت سانتی‌متر بلندتر از دور کمر بریده شده تا گره جا داشته باشد.',
                ],
            ],
        ]);
    }
}
