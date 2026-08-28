<?php

namespace App\Services\Pattern\Generators;

/**
 * پایهٔ بالاتنهٔ مردانه.
 *
 * «مردانه» برچسب نیست، چند عددِ متفاوت است و همین‌ها الگو را عوض می‌کنند:
 *
 *   ۱. ساسونِ سینه ندارد. بالاتنهٔ مردانه پیش‌آمدگیِ سینه را با ساسون جا
 *      نمی‌دهد؛ همان آزادیِ کلی کافی است.
 *   ۲. کمرگیری بسیار کم است. اختلافِ سینه تا کمر در بدنِ مردانه کمتر است و
 *      لباسِ کمرگیرِ تند روی آن «تنگ» دیده می‌شود، نه «جذب».
 *   ۳. سرشانه پهن‌تر و شیبش کمتر است.
 *   ۴. حلقهٔ آستین گودتر و یقه فراخ‌تر است — گردنِ مردانه پهن‌تر است.
 *   ۵. دکمه سمتِ دیگر می‌افتد: در لباسِ مردانه لبهٔ راست روی چپ.
 *
 * هر مدلِ مردانه تنها همان چند عددِ خودش را عوض می‌کند؛ این پنج تصمیم از این‌جا
 * می‌آید.
 */
abstract class MensShirtBaseGenerator extends ShirtBaseGenerator
{
    /**
     * شناسنامهٔ مدل.
     *
     * @return array<string, mixed>
     */
    abstract protected function mens(): array;

    public static function group(): string
    {
        return 'shirt';
    }

    public function paramsSchema(): array
    {
        $own = $this->mens();

        return $this->shirtSchema(
            array_merge([
                'fit' => $own['fit'] ?? 'regular',
                'sleeve_length' => (float) ($own['sleeve'] ?? 62),
                'body_length' => (float) ($own['body_length'] ?? 22),
                'armhole_depth_extra' => (float) ($own['armhole'] ?? 4),
                // سرشانهٔ مردانه پهن‌تر و کم‌شیب‌تر
                'shoulder_slope' => (float) ($own['slope'] ?? 4.0),
                'neck_width_extra' => (float) ($own['neck_width'] ?? 1.5),
            ], $own['defaults'] ?? []),
            array_merge(
                [
                    'collar' => [
                        'label' => 'یقه', 'type' => 'select',
                        'default' => $own['collar'] ?? 'shirt',
                        'options' => [
                            'none' => 'بدون یقه (نوار اریب)',
                            'shirt' => 'یقه پیراهنی (برگردان)',
                            'stand' => 'یقه ایستاده (مائو)',
                            'camp' => 'یقه هاوایی (باز)',
                            'button_down' => 'یقه دکمه‌دار (باتن‌دان)',
                        ],
                    ],
                    'collar_height' => [
                        'label' => 'بلندی یقه', 'min' => 3, 'max' => 11, 'step' => 0.5,
                        'default' => (float) ($own['collar_height'] ?? 7.5), 'unit' => 'سانتی‌متر',
                    ],
                    'cuff_style' => [
                        'label' => 'مچ آستین', 'type' => 'select',
                        'default' => $own['cuff'] ?? 'button',
                        'options' => [
                            'none' => 'بدون مچ‌بند (لبه تودوزی)',
                            'button' => 'مچ‌بند دکمه‌دار',
                            'french' => 'مچ دوبل (فرانسوی، دکمه سردست)',
                            'rib' => 'مچ کشباف',
                        ],
                    ],
                    'garment_use' => [
                        'label' => 'کاربرد', 'type' => 'select',
                        'default' => $own['use'] ?? 'daily',
                        'options' => [
                            'daily' => 'روزمره',
                            'office' => 'اداری و رسمی',
                            'sport' => 'اسپرت',
                            'party' => 'مجلسی',
                            'uniform' => 'یونیفرم',
                        ],
                    ],
                    'back_pleat' => [
                        'label' => 'پیلی مرکز پشت', 'type' => 'select',
                        'default' => $own['back_pleat'] ?? 'box',
                        'options' => [
                            'none' => 'ندارد',
                            'box' => 'یک پیلی جعبه‌ای وسط',
                            'side' => 'دو پیلی کنار یوک',
                        ],
                        'hint' => 'پیلیِ پشت به شانه جا می‌دهد؛ بی آن، پیراهنِ اداری روی کتف کشیده می‌شود.',
                    ],
                ],
                $this->pocketParam((bool) ($own['pocket'] ?? true)),
                $own['extra'] ?? [],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $own = $this->mens();
        $params = $this->withDropShoulder($params);
        $ease = $this->shirtEase($ease, $params);
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $stand = (float) $this->param($params, 'button_stand', 2.0);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => $own['prefix'],
        ]);

        $pieces = array_merge([$front, $back], $extras);

        foreach ($this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'sleeve_name' => 'آستین '.$own['title'],
        ]) as $sleeve) {
            $pieces[] = $sleeve;
        }

        foreach ($this->mensCollar($g, $params, $own) as $collar) {
            $pieces[] = $collar;
        }

        $pieces[0]['meta']['mens'] = [
            'base' => static::key(),
            'collar' => (string) $this->param($params, 'collar', $own['collar'] ?? 'shirt'),
            'cuff' => (string) $this->param($params, 'cuff_style', $own['cuff'] ?? 'button'),
            'use' => (string) $this->param($params, 'garment_use', $own['use'] ?? 'daily'),
            'back_pleat' => (string) $this->param($params, 'back_pleat', $own['back_pleat'] ?? 'box'),
        ];

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            [
                'لباسِ مردانه ساسونِ سینه ندارد؛ جا با آزادیِ کلی داده می‌شود.',
                'لبهٔ راستِ جلو روی چپ می‌افتد — برعکسِ لباسِ زنانه.',
            ],
            $own['notes'] ?? [],
        );

        return $this->finish($pieces);
    }

    /**
     * قطعهٔ یقه بر پایهٔ محورِ «یقه».
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mensCollar(array $g, array $params, array $own): array
    {
        $style = (string) $this->param($params, 'collar', $own['collar'] ?? 'shirt');
        $height = (float) $this->param($params, 'collar_height', $own['collar_height'] ?? 7.5);
        $half = ($g['front_neck_length'] ?? 0) + ($g['back_neck_length'] ?? 0);

        if ($half < 1) {
            $half = max(9.0, ($g['neck_width'] ?? 9) * 2);
        }

        return match ($style) {
            'shirt', 'button_down' => [$this->shirtCollar($half, $height, $own['prefix'].'-collar', 'یقه')],
            'stand' => [$this->bandCollar($half, min($height, 5.0))],
            'camp' => [$this->campCollar($half, max(6.0, $height), 3.0)],
            default => [],
        };
    }
}
