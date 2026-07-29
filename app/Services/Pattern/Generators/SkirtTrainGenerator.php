<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن دنباله‌دار.
 *
 * جلو به قد معمولی و پشت بلندتر؛ همان بلندی اضافه روی زمین کشیده می‌شود.
 *
 * دو چیز که دنباله را از «دامن بلندِ نامتقارن» جدا می‌کند:
 *
 *   ۱. اضافهٔ بلندی فقط روی مرکز پشت است و به‌تدریج به درز پهلو می‌رسد؛ اگر
 *      یک‌باره اضافه شود، دم دامن گوشه پیدا می‌کند.
 *   ۲. دنباله وزن دارد و آن وزن از خط کمر آویزان است. برای همین پشتِ کمر باید
 *      محکم بسته شود و در دنبالهٔ بلند، حلقهٔ جمع‌کننده لازم است تا موقع رقص
 *      دنباله بالا برود.
 *
 * قد دنباله از انتهای دامن اندازه می‌شود، نه از کمر؛ همان عددی که خیاط با متر
 * روی زمین می‌گیرد.
 */
class SkirtTrainGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_train';
    }

    public function label(): string
    {
        return 'دامن دنباله‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(105, 70, 140),
            [
                'train' => [
                    'label' => 'بلندی دنباله (از دم دامن)', 'min' => 15, 'max' => 300, 'step' => 5,
                    'default' => 60, 'unit' => 'سانتی‌متر',
                    'hint' => 'تا ۹۰ سانتی‌متر دنبالهٔ کوتاه، بالای ۲۰۰ دنبالهٔ کلیسایی.',
                ],
                'hem_flare' => [
                    'label' => 'گشادی دم', 'min' => 4, 'max' => 60, 'step' => 2,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
                'bustle_loop' => [
                    'label' => 'حلقهٔ جمع‌کردن دنباله', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->waistParams(0.6, 4, true, 'back'),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 105);
        $train = max(10.0, (float) $this->param($params, 'train', 60));
        $flare = (float) $this->param($params, 'hem_flare', 18);

        $front = $this->blockPanel($mx, [
            'side' => 'front',
            'length' => $length,
            'hem_delta' => $flare,
            'code' => 'train-front',
            'name' => 'دامن جلو',
        ]);

        // پشت با همان قدِ جلو درفت می‌شود تا دو درز پهلو هم‌اندازه بمانند، و
        // دنباله بعد از آن فقط روی مرکز پشت اضافه می‌شود
        $back = $this->blockPanel($mx, [
            'side' => 'back',
            'length' => $length,
            'hem_delta' => $flare,
            'code' => 'train-back',
            'name' => 'دامن پشت با دنباله',
        ]);

        $back = $this->addTrain($back, $train);

        $pieces = array_merge([$front, $back], $this->bandPieces($mx, $params));

        $notes = [
            'دنباله '.$this->fa($train).' سانتی‌متر از دم دامن بلندتر است و اضافه‌اش روی مرکز پشت جمع شده؛'
                .' لبهٔ دم از مرکز به پهلو بالا می‌آید تا گوشه پیدا نکند.',
            'وزن دنباله از خط کمر آویزان است؛ کمربند را لایی محکم بزنید و بستِ پشت را دوتایی کنید.',
        ];

        if ($this->flag($params, 'bustle_loop', true)) {
            $notes[] = 'یک حلقه در دم دنباله و یک دکمه زیر کمر پشت: با انداختن حلقه روی دکمه، دنباله جمع می‌شود.';

            $back['meta']['notions'] = [
                ['type' => 'button', 'label' => 'دکمهٔ جمع‌کردن دنباله', 'count' => 1],
                ['type' => 'cord', 'label' => 'حلقهٔ جمع‌کردن دنباله', 'length' => 12],
            ];

            $pieces[1] = $back;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $this->finishSkirt($pieces, $params);
    }

    /**
     * پایین بردن مرکز پشت به اندازهٔ دنباله.
     *
     * فقط نقطهٔ مرکز پشت پایین می‌رود؛ نقطهٔ روی درز پهلو دست‌نخورده می‌ماند، پس
     * درز پهلوی جلو و پشت هم‌اندازه می‌مانند و همهٔ اضافه روی لبهٔ دم پخش می‌شود.
     * نقطهٔ کنترل همان لبه هم با آن پایین می‌رود تا دم گوشه پیدا نکند.
     */
    protected function addTrain(array $piece, float $train): array
    {
        $outline = array_values($piece['outline']);
        $tags = array_values($piece['meta']['edges'] ?? []);
        $count = count($outline);
        $centre = null;
        $centreX = INF;

        // نقطهٔ مرکز پشت: کم‌ترین x روی دو سرِ لبه‌های دم
        for ($i = 0; $i < $count; $i++) {
            if (($tags[$i] ?? '') !== 'hem') {
                continue;
            }

            foreach ([$i, ($i + 1) % $count] as $index) {
                if ((float) $outline[$index]['x'] < $centreX) {
                    $centreX = (float) $outline[$index]['x'];
                    $centre = $index;
                }
            }
        }

        if ($centre === null) {
            return $piece;
        }

        $outline[$centre]['y'] = round((float) $outline[$centre]['y'] + $train, 2);

        if (isset($outline[$centre]['cy'])) {
            // کنترل کمتر از خودِ نقطه پایین می‌رود تا شیب دنباله از پهلو نرم شروع شود
            $outline[$centre]['cy'] = round((float) $outline[$centre]['cy'] + ($train * 0.55), 2);
        }

        $piece['outline'] = $outline;
        $piece['meta']['train'] = round($train, 2);

        return $piece;
    }
}
