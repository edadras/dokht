<?php

namespace App\Services\Pattern\Generators;

/**
 * جلباب.
 *
 * یک‌تکه، از سر تا پا، بدون هیچ بستِ جلو: از سر پوشیده می‌شود و همین «یک‌تکه
 * بودن» تعریفش است. عبا جلوباز است و کافتان تا میان ساق می‌آید؛ جلباب هیچ درز
 * بازی روی جلو ندارد و لبه پایینش روی زمین می‌ایستد.
 *
 * از سر پوشیدنِ لباسی که هیچ بست ندارد یک شرط دارد: دور یقه باید از دور سر
 * بزرگ‌تر باشد. الگو خودش این را می‌سنجد و اگر لازم شد یقه را بازتر می‌کند، و در
 * هر حال یک چاک کوتاه با بند و دکمه روی مرکز پشت می‌گذارد تا سر بدون کشیدن رد
 * شود. سرپوش پیوسته هم اختیاری است.
 */
class TradJilbabGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_jilbab';
    }

    public function label(): string
    {
        return 'جلباب';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 4,
                'back_neck_depth' => 3,
                'armhole_depth_extra' => 5,
            ]),
            $this->garmentLengthParam(100, 60, 140, 'بلندی از خط کمر تا لبه پایین'),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 4, 'max' => 22, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 20, 'max' => 60, 'step' => 1,
                    'default' => 44, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 14, 'max' => 36, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 24, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'back_slit' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 24, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                    'hint' => 'چاک کوچکی که با بند و دکمه بسته می‌شود تا سر راحت رد شود.',
                ],
                'hood' => [
                    'label' => 'سرپوش پیوسته', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 5);
        $length = (float) $this->param($params, 'length', 100);
        $slit = (float) $this->param($params, 'back_slit', 12);
        $head = round($this->m($measurements, 'neck', 37) * 1.55, 1);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => (float) $this->param($params, 'underarm_drop', 11),
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 44),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 26),
            'sleeve_slope' => 6.0,
            'hem_flare' => (float) $this->param($params, 'hem_flare', 8),
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'jilbab-front',
            'name' => 'تنه و آستین جلو',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'jilbab-back',
            'name' => 'تنه و آستین پشت',
        ]));

        $halfNeck = $this->neckOf([$front, $back]);
        $opening = round(2 * $halfNeck, 1);

        if ($slit > 2) {
            $back['markers'][] = $this->marker(
                'slit',
                'چاک مرکز پشت',
                0,
                $g['back_neck_depth'],
                0,
                $g['back_neck_depth'] + $slit,
            );
            $back['meta']['back_slit'] = round($slit, 2);
            $back = $this->addNotion(
                $back,
                ['type' => 'button', 'label' => 'دکمه و بندِ سر چاک پشت', 'count' => 1],
                'چاک مرکز پشت به بلندی '.$this->fa(round($slit))
                    .' سانتی‌متر، با یک بند و دکمه بالای آن بسته می‌شود.',
            );
        }

        $pieces = [$front, $back];

        $pieces[] = $this->bandPiece('jilbab-neck-binding', 'نوار اریب دور یقه', $opening + 6, 4, [
            'cut' => 1, 'part' => 'facing',
            'meta' => [
                'bias' => true,
                'girth_role' => 'trim',
                'target_neck' => $opening,
                'notes' => [
                    'دور یقه '.$this->fa($opening).' سانتی‌متر است؛ برای سری به دور '
                        .$this->fa($head).' سانتی‌متر باید بزرگ‌تر بماند.',
                ],
            ],
        ]);

        $pieces[] = $this->bandPiece('jilbab-back-slit-facing', 'سجاف چاک پشت', $slit + 6, 6, [
            'cut' => 1, 'part' => 'facing',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notes' => ['روی چاک پشت دوخته و به داخل برگردانده می‌شود.'],
            ],
        ]);

        if ($this->flag($params, 'hood', true)) {
            $pieces[] = $this->hoodPiece($g, $halfNeck, [
                'prefix' => 'jilbab-',
                'name' => 'سرپوش پیوسته',
                'height' => 36,
                'width' => 27,
                'face' => 5,
            ]);
        }

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($front),
            'hem_at' => 'روی زمین (تا مچ پا و پایین‌تر)',
            'sleeve' => 'مچ دست، با دم آستین '
                .$this->fa(round((float) $this->param($params, 'cuff_width', 26))).' سانتی‌متری',
            'neck' => 'بسته و بی‌بستِ جلو؛ دور یقه '.$this->fa($opening).' سانتی‌متر',
            'head' => $this->flag($params, 'hood', true)
                ? 'سرپوش پیوسته دارد، پس سر و گردن هم پوشیده است'
                : 'سرپوش ندارد؛ با مقنعه یا خمار جدا پوشیده می‌شود',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'جلو هیچ درز و بستی ندارد؛ لباس یک‌تکه از سر پوشیده می‌شود.',
        ]));

        if ($opening < $head + 4) {
            $pieces[0]['meta']['notes'][] = 'هشدار: دور یقه '.$this->fa($opening)
                .' سانتی‌متر است و برای سرِ '.$this->fa($head)
                .' سانتی‌متری تنگ می‌شود؛ «اضافه عرض یقه» را بالا ببرید یا چاک پشت را بلندتر کنید.';
        }

        return $this->finishBlock($pieces, $g, $grow);
    }
}
