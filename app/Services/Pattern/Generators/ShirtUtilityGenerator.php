<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن یوتیلیتی (کار / نظامی).
 *
 * پیراهنی که قرار است چیز حمل کند: دو جیب بزرگ درپوش‌دار روی سینه و دو سردوشی
 * روی شانه.
 *
 * جیبِ پُر روی پیراهن یک مشکل دارد که روی کت ندارد: پیراهن آستر و لایی ندارد،
 * پس وزن جیب مستقیم روی یک لایه پارچه می‌افتد و آن را می‌کشد. دو کار جلویش را
 * می‌گیرد و هر دو این‌جا هست: پشت جیب لایی می‌خورد، و جیب بالای خط سینه دوخته
 * می‌شود نه پایین‌تر، چون آن‌جا پارچه به تن تکیه دارد.
 */
class ShirtUtilityGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_utility';
    }

    public function label(): string
    {
        return 'پیراهن یوتیلیتی (کار)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['fit' => 'loose', 'sleeve_length' => 58, 'body_length' => 26],
            [
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'pocket_width' => [
                    'label' => 'پهنای جیب', 'min' => 10, 'max' => 20, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'pocket_height' => [
                    'label' => 'بلندی جیب', 'min' => 10, 'max' => 22, 'step' => 0.5,
                    'default' => 15, 'unit' => 'سانتی‌متر',
                ],
                'epaulettes' => [
                    'label' => 'سردوشی روی شانه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);
        $stand = (float) $this->param($params, 'button_stand', 2);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'utility',
            'yoke' => 10.0,
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, array_merge([$front, $back], $extras));

        $neckHalf = ($front['meta']['neck_length'] ?? 12)
            + ($back['meta']['neck_length'] ?? 9)
            + ($extras[0]['meta']['neck_length'] ?? 0);

        $width = (float) $this->param($params, 'pocket_width', 14);
        $height = (float) $this->param($params, 'pocket_height', 15);

        $pocket = $this->patchPocket($width, $height + 3, [
            'name' => 'جیب سینه (جفت)', 'cut' => 2,
            'notes' => [
                'پشت جیب لایی می‌خورد؛ پیراهن آستر ندارد و وزن جیب روی یک لایه پارچه می‌افتد.',
                'جیب بالای خط سینه دوخته می‌شود، آن‌جا که پارچه به تن تکیه دارد.',
            ],
        ]);
        $pocket['meta']['interfacing'] = true;

        $flap = $this->patchPocket($width + 1, 6.5, [
            'code' => 'utility-flap', 'name' => 'درپوش جیب', 'cut' => 4,
        ]);
        $flap['meta']['interfacing'] = true;
        $flap['meta']['notions'] = [['type' => 'button', 'label' => 'دکمهٔ درپوش جیب', 'count' => 2]];

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->shirtCollar($neckHalf + $stand, (float) $this->param($params, 'collar_height', 8)),
            $pocket,
            $flap,
        ]);

        if ($this->flag($params, 'epaulettes', true)) {
            $strap = $this->patchPocket(5, $g['shoulder_half'] * 0.8, [
                'code' => 'epaulette', 'name' => 'سردوشی', 'cut' => 4,
                'notes' => ['دو لایه برای هر سردوشی؛ سرِ نزدیک یقه با دکمه بسته می‌شود.'],
            ]);
            $strap['meta']['notions'] = [['type' => 'button', 'label' => 'دکمهٔ سردوشی', 'count' => 2]];
            $pieces[] = $strap;
        }

        $pieces[] = $this->placket($stand, Geometry::height($front['outline']));

        return $this->finish($pieces);
    }
}
