<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن دولایه (رو-دامن).
 *
 * یک دامن پوشیده در زیر و یک لایهٔ سبک روی آن؛ هر دو از خط کمر آویزان‌اند و
 * جز روی کمر به هم دوخته نمی‌شوند.
 *
 * تفاوتش با آستر همین است: آستر همان شکل دامن را دارد و کمی کوتاه‌تر است تا
 * دیده نشود؛ رو-دامن اما عمداً دیده می‌شود و می‌تواند شکل و قد و پارچهٔ دیگری
 * داشته باشد — تور روی ساتن، چاک‌دار روی راسته، کلوش روی مدادی.
 *
 * قاعده‌ای که رعایت شده: دو لایه هیچ‌وقت هم‌قد نیستند. اگر باشند، لبه‌شان روی
 * هم می‌افتد و از دور شبیه یک دامن ضخیم دیده می‌شود، نه دو لایه.
 */
class SkirtOverlayGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_overlay';
    }

    public function label(): string
    {
        return 'دامن دولایه (رو-دامن)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(60, 30, 125),
            [
                'under_style' => [
                    'label' => 'دامن زیر', 'type' => 'select', 'default' => 'straight',
                    'options' => ['straight' => 'راسته', 'pencil' => 'مدادی (تنگ دم)', 'aline' => 'خط A'],
                ],
                'overlay_style' => [
                    'label' => 'لایهٔ رو', 'type' => 'select', 'default' => 'open_front',
                    'options' => [
                        'open_front' => 'جلوباز (دو لتّه)',
                        'full' => 'بسته و کلوش',
                        'high_low' => 'جلو کوتاه، پشت بلند',
                    ],
                ],
                'overlay_length' => [
                    'label' => 'اختلاف قد لایهٔ رو', 'min' => -30, 'max' => 40, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                    'hint' => 'مثبت یعنی لایهٔ رو بلندتر است.',
                ],
                'overlay_flare' => [
                    'label' => 'گشادی لایهٔ رو', 'min' => 4, 'max' => 60, 'step' => 2,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 60);
        $under = (string) $this->param($params, 'under_style', 'straight');
        $style = (string) $this->param($params, 'overlay_style', 'open_front');
        $delta = (float) $this->param($params, 'overlay_length', 12);
        $flare = (float) $this->param($params, 'overlay_flare', 24);

        $underDelta = match ($under) {
            'pencil' => -4.0,
            'aline' => 10.0,
            default => 2.0,
        };

        $pieces = [];

        foreach ([['front', 'دامن زیر — جلو', 'skirt_front'], ['back', 'دامن زیر — پشت', 'skirt_back']] as [$side, $name, $part]) {
            $pieces[] = $this->blockPanel($mx, [
                'side' => $side,
                'length' => $length,
                'hem_delta' => $underDelta,
                'vent' => $under === 'pencil' && $side === 'back' ? 14 : 0,
                'code' => 'under-'.$side,
                'name' => $name,
                'part' => $part,
            ]);
        }

        // دو لایه هم‌قد نباشند، وگرنه از دور یک دامن دیده می‌شوند
        $overlayLength = max(20.0, $length + (abs($delta) < 3 ? 6 : $delta));

        $overlayFront = $this->blockPanel($mx, [
            'side' => 'front',
            'length' => $style === 'high_low' ? max(20.0, $overlayLength - 22) : $overlayLength,
            'hem_delta' => $flare,
            'overlap' => $style === 'open_front' ? -8 : 0,
            'code' => 'overlay-front',
            'name' => 'لایهٔ رو — جلو',
            'part' => 'skirt_overlay',
        ]);

        $overlayFront['layer'] = 'outer';
        $overlayFront['meta']['girth_role'] = 'trim';
        $overlayFront['meta']['overlay'] = true;

        if ($style === 'open_front') {
            $overlayFront['on_fold'] = false;
            $overlayFront['cut_quantity'] = 2;
            $overlayFront['mirror'] = true;
            $overlayFront['meta']['fold_edges'] = [];
            $overlayFront['meta']['notes'][] = 'لایهٔ رو جلوباز است: دو لتّه که از مرکز جلو باز می‌مانند.';
        }

        $overlayBack = $this->blockPanel($mx, [
            'side' => 'back',
            'length' => $overlayLength,
            'hem_delta' => $flare,
            'code' => 'overlay-back',
            'name' => 'لایهٔ رو — پشت',
            'part' => 'skirt_overlay',
        ]);

        $overlayBack['meta']['girth_role'] = 'trim';
        $overlayBack['meta']['overlay'] = true;

        $pieces[] = $overlayFront;
        $pieces[] = $overlayBack;
        $pieces = array_merge($pieces, $this->bandPieces($mx, $params));

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'دو لایه فقط روی خط کمر به هم می‌رسند؛ پایین‌تر به هم دوخته نمی‌شوند.',
            'لایهٔ رو '.$this->fa(round(abs($overlayLength - $length))).' سانتی‌متر با دامن زیر اختلاف قد دارد.',
            $style === 'high_low'
                ? 'جلوی لایهٔ رو کوتاه‌تر است و پشتش بلند؛ درز پهلوی دو لایه با هم دوخته نمی‌شود.'
                : 'اگر پارچهٔ رو تور یا حریر است، آستر لازم ندارد؛ دامن زیر همان کار را می‌کند.',
        ]);

        return $this->finishSkirt($pieces, $params);
    }
}
