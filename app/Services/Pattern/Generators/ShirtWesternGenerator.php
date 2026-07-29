<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن وسترن (کابویی).
 *
 * دو نشانهٔ همیشگی دارد: یوک نوک‌تیز روی پشت و جلو، و دکمهٔ فشاری به‌جای دکمهٔ
 * دوختنی.
 *
 * دکمهٔ فشاری تزیین نیست؛ کارکرد دارد. اگر آستین یا لبهٔ پیراهن جایی گیر کند،
 * دکمهٔ فشاری باز می‌شود و پارچه پاره نمی‌شود — همان دلیلی که این پیراهن را
 * لباس کار کرده. برای همین در صورت مواد به‌جای دکمه، دکمهٔ فشاری شمرده می‌شود.
 *
 * یوک نوک‌تیز هم فقط شکل نیست: درزِ نوک‌دار روی پشت، جای بیشتری برای حرکت شانه
 * می‌دهد.
 */
class ShirtWesternGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_western';
    }

    public function label(): string
    {
        return 'پیراهن وسترن (کابویی)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 58, 'body_length' => 24],
            [
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                    'default' => 1.75, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'yoke_depth' => [
                    'label' => 'بلندی یوک پشت', 'min' => 6, 'max' => 20, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                ],
                'snaps' => [
                    'label' => 'دکمه فشاری به‌جای دکمه', 'type' => 'toggle', 'default' => true,
                ],
                'pockets' => [
                    'label' => 'دو جیب درپوش‌دار سینه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);
        $stand = (float) $this->param($params, 'button_stand', 1.75);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'western',
            'yoke' => (float) $this->param($params, 'yoke_depth', 11),
            'pointed_yoke' => true,
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, array_merge([$front, $back], $extras));

        $neckHalf = ($front['meta']['neck_length'] ?? 12)
            + ($back['meta']['neck_length'] ?? 9)
            + ($extras[0]['meta']['neck_length'] ?? 0);

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->shirtCollar($neckHalf + $stand, (float) $this->param($params, 'collar_height', 8)),
        ]);

        if ($this->flag($params, 'pockets', true)) {
            $pieces[] = $this->patchPocket(12.5, 14, [
                'name' => 'جیب سینه (جفت)', 'cut' => 2,
                'notes' => ['دو جیب قرینه روی سینهٔ راست و چپ.'],
            ]);

            $flap = $this->patchPocket(13.5, 6, [
                'code' => 'western-flap', 'name' => 'درپوش جیب', 'cut' => 4,
                'notes' => ['دو لایه برای هر درپوش؛ لایی می‌خورد.'],
            ]);
            $flap['meta']['interfacing'] = true;
            $pieces[] = $flap;
        }

        $placket = $this->placket($stand, Geometry::height($front['outline']), spacing: 8.5);

        if ($this->flag($params, 'snaps', true)) {
            $count = (int) ($placket['meta']['button_count'] ?? 6);
            $extra = $this->flag($params, 'pockets', true) ? 2 : 0;

            $placket['meta']['notions'] = [[
                'type' => 'snap',
                'label' => 'دکمه فشاری',
                'count' => $count + $extra + 2,
            ]];
            $placket['meta']['notes'][] = 'دکمهٔ فشاری زیر فشار باز می‌شود و پارچه پاره نمی‌شود؛'
                .' دو عدد هم برای مچ‌بندها و '.($extra > 0 ? 'دو عدد برای درپوش جیب‌ها ' : '').'حساب شده.';
        }

        $pieces[] = $placket;

        return $this->finish($this->noteOn($pieces, [
            'یوک نوک‌تیز فقط شکل نیست؛ درز نوک‌دار روی پشت جای بیشتری برای حرکت شانه می‌دهد.',
        ]));
    }
}
