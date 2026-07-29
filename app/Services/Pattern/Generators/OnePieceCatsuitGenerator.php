<?php

namespace App\Services\Pattern\Generators;

/**
 * کت‌سوت (یک‌تکه چسبان).
 *
 * تنها عضو این خانواده که از پارچه کشی بریده می‌شود و تنها عضوی که آزادی‌اش
 * منفی است: الگو عمداً کوچک‌تر از بدن درفت می‌شود و پارچه با کش آمدن روی تن
 * می‌نشیند. اگر اندازه بدن بریده شود، کت‌سوت روی تن می‌افتد و چین می‌خورد.
 *
 * دو نکته که کت‌سوت را از بقیه جدا می‌کند:
 *
 *   ۱. «ضریب کشسانی» یک پارامتر اصلی است، نه یک تنظیم پیشرفته؛ همان‌طور که در
 *      مایو هست. هر پنل پوسته با meta.stretch_ratio اعلام می‌کند با چه نسبتی
 *      کوچک بریده شده تا هیچ بررسی‌ای آن را با لباس بافته اشتباه نگیرد.
 *   ۲. رایز در پارچه کشی هم آزادی می‌خواهد. کشش پارچه در راستای دور بدن مصرف
 *      شده است و برای بلند شدن قد تن چیزی نمی‌ماند؛ پس کت‌سوت هم مثل بویلرسوت
 *      آزادی قد بالاتنه و قد فاق دارد، فقط کمتر.
 *
 * بست از زیپ مخفی مرکز پشت است، پس پشت در دو تکه بریده می‌شود.
 */
class OnePieceCatsuitGenerator extends OnePieceBaseGenerator
{
    public static function key(): string
    {
        return 'one_catsuit';
    }

    public function label(): string
    {
        return 'کت‌سوت (یک‌تکه چسبان)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->onePieceSchema([
                'shoulder_slope' => 4.5,
                'neck_width_extra' => 0.5,
                'front_neck_depth_extra' => 1.5,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 0.5,
            ]),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین بلند',
                'none' => 'بی‌آستین (نوار حلقه)',
            ]),
            $this->riseSchema(1.5, 1.5),
            [
                // پاچه کت‌سوت هم مثل تنه‌اش تنگ‌تر از بدن است، پس این دو آزادی
                // برخلاف بقیه خانواده می‌توانند منفی باشند
                'length_extra' => [
                    'label' => 'تغییر قد پا', 'min' => -40, 'max' => 12, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => -8, 'max' => 10, 'step' => 0.5,
                    'default' => -2, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دور مچ پا', 'min' => -6, 'max' => 10, 'step' => 0.5,
                    'default' => -1, 'unit' => 'سانتی‌متر',
                ],
            ],
            [
                'stretch' => [
                    'label' => 'ضریب کشسانی پارچه', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                    'default' => 0.9, 'unit' => 'نسبت',
                    'hint' => 'الگو به این نسبت از دور بدن کوچک‌تر بریده می‌شود؛ ۰٫۹ برای جرسی کشی معمولی'
                        .' و ۰٫۸ برای پارچه پرکشش ورزشی.',
                ],
                'neck_band' => [
                    'label' => 'نوار یقه کشباف', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_band' => [
                    'label' => 'مچ‌بند کشباف', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    /** نسبت کوچک‌شدن الگو نسبت به بدن. */
    protected function stretch(array $params): float
    {
        return min(1.0, max(0.7, (float) $this->param($params, 'stretch', 0.9)));
    }

    /**
     * آزادی منفی روی هر سه دور، دقیقاً به اندازه کششی که از پارچه انتظار داریم.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function knitEase(array $ease, array $m, float $stretch): array
    {
        $shrink = fn (string $key, float $fallback) => -$this->m($m, $key, $fallback) * (1 - $stretch);

        return array_merge($ease, [
            'bust' => $shrink('bust', 92),
            'waist' => $shrink('waist', 74),
            'hip' => $shrink('hip', 98),
            'bicep' => 0.0,
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withRise($params);
        $stretch = $this->stretch($params);
        $ease = $this->knitEase($ease, $measurements, $stretch);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $pieces = $this->onePieceBody($measurements, $ease, $params, $g, [
            'prefix' => 'catsuit-',
            'grow' => 0.0,
            'panel' => ['meta' => ['knit' => true]],
            'back' => [
                'on_fold' => false,
                'cut' => 2,
                'mirror' => true,
                'name' => 'بالاتنه پشت (زیپ مرکزی)',
                'meta' => ['knit' => true, 'back_zip' => true],
            ],
            'leg_front_name' => 'پاچه چسبان جلو',
            'leg_back_name' => 'پاچه چسبان پشت',
        ]);

        $front = $pieces[0];
        $back = $pieces[1];

        $back = $this->markBackZip($back, $g, null, (float) $g['hip_drop']);
        $pieces[1] = $back;

        if ($this->flag($params, 'neck_band', true)) {
            $pieces[] = $this->neckBandPiece($this->neckOf([$front, $back]), [
                'prefix' => 'catsuit-',
                'ratio' => 0.88,
                'height' => 3,
            ]);
        }

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), ['prefix' => 'catsuit-']);
        } elseif ($this->flag($params, 'cuff_band', true)) {
            $pieces[] = $this->ribBandPiece(
                'catsuit-cuff-rib',
                'مچ‌بند کشباف',
                $this->m($measurements, 'wrist', 16.5) + 3,
                ['height' => 4, 'ratio' => 0.85, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff'],
            );
        }

        $pieces = $this->stampStretch($pieces, $stretch);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'الگو '.$this->fa(round((1 - $stretch) * 100)).' درصد کوچک‌تر از دور بدن بریده شده؛'
                .' روی پارچه بافته اصلاً پوشیده نمی‌شود.',
            'با نخ کشی و سوزن جرسی بدوزید؛ درز معمولی زیر کشش پاره می‌شود.',
        ]);

        return $this->finishBlock($pieces, $g, 0.0);
    }

    /**
     * مهر «این الگو با آزادی منفی بریده شده» روی پوسته و پاچه.
     *
     * کلیدش عمداً از meta.stretch نوار کشباف جداست: آن یکی یعنی «نوار این‌قدر
     * کوتاه‌تر از لبه بریده شده» و این یکی یعنی «کل لباس این‌قدر کوچک‌تر از بدن».
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function stampStretch(array $pieces, float $stretch): array
    {
        if ($stretch >= 0.999) {
            return $pieces;
        }

        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['girth_role'] ?? '', ['shell', 'bottom'], true)) {
                continue;
            }

            $pieces[$index]['meta']['stretch_ratio'] = round($stretch, 3);
        }

        return $pieces;
    }
}
