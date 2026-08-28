<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن ترک‌دار (کلوش ترکی).
 *
 * کاهش کمر تا باسن به جای ساسون در درزهای ترک گرفته می‌شود، پس هرچه تعداد ترک
 * بیشتر باشد دامن نرم‌تر روی بدن می‌نشیند. پهنای هر ترک در کمر = دور کمر ÷ تعداد
 * ترک، در باسن = دور باسن ÷ تعداد ترک و در دم = پهنای باسن + گشادی همان ترک.
 */
class SkirtGoredGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_gored';
    }

    public function label(): string
    {
        return 'دامن ترک‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(68, 30, 125),
            [
                'panels' => [
                    'label' => 'تعداد ترک', 'type' => 'select', 'default' => 6,
                    'options' => [4 => '۴ ترک', 6 => '۶ ترک', 8 => '۸ ترک', 12 => '۱۲ ترک'],
                ],
                'flare' => [
                    'label' => 'گشادی هر ترک در دم', 'min' => 0, 'max' => 30, 'step' => 0.5, 'default' => 6,
                    'unit' => 'سانتی‌متر',
                    'hint' => 'دم دامن = دور باسن + تعداد ترک × این عدد.',
                ],
                'flare_from' => [
                    'label' => 'شروع گشادی از باسن', 'min' => 0, 'max' => 45, 'step' => 1, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی گشادی از خط باسن شروع می‌شود.',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $panels = $this->panelCount($params);
        $length = (float) $this->param($params, 'length', 68);
        $flare = (float) $this->param($params, 'flare', 6);

        $waistW = $mx['waist_target'] / $panels;
        $hipW = $mx['hip_target'] / $panels;
        $hipY = max($mx['hip_y'], $mx['hip_y'] + (float) $this->param($params, 'flare_from', 0));

        $base = [
            'waist_w' => $waistW,
            'hip_w' => $hipW,
            'hem_w' => $hipW + $flare,
            'length' => $length,
            'hip_y' => $hipY,
        ];

        $pieces = [
            $this->gorePanel(array_merge($base, [
                'half' => true, 'code' => 'gore-front', 'name' => 'ترک مرکز جلو',
                'part' => 'skirt_front', 'side' => 'front',
                'neighbours' => $panels > 2 ? ['gore-side'] : ['gore-back'],
            ])),
            $this->gorePanel(array_merge($base, [
                'half' => true, 'code' => 'gore-back', 'name' => 'ترک مرکز پشت',
                'part' => 'skirt_back', 'side' => 'back',
                'neighbours' => $panels > 2 ? ['gore-side'] : ['gore-front'],
            ])),
        ];

        if ($panels > 2) {
            $pieces[] = $this->gorePanel(array_merge($base, [
                'half' => false,
                'cut_quantity' => $panels - 2,
                'mirror' => true,
                'code' => 'gore-side',
                'name' => 'ترک پهلو',
                'neighbours' => ['gore-front', 'gore-back', 'gore-side'],
                'meta' => [
                    'notes' => [
                        'دم دامن روی '.$this->fa($panels).' ترک: '
                            .$this->fa(round($panels * ($hipW + $flare), 1)).' سانتی‌متر.',
                    ],
                ],
            ]));
        }

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }

    /** تعداد ترک؛ فقط عددهای زوجِ فهرست پذیرفته می‌شود. */
    protected function panelCount(array $params): int
    {
        $panels = (int) $this->param($params, 'panels', 6);

        return in_array($panels, [4, 6, 8, 12], true) ? $panels : 6;
    }
}
