<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن ماهی.
 *
 * بالاتنه پرنسسی از حلقه، تا بالای زانو کاملاً قالب و از آنجا به پایین کلوش
 * می‌شود. چون لباس روی ران تنگ می‌ماند، خط شکستِ کلوش نباید از بالای زانو
 * بالاتر بیاید وگرنه راه رفتن سخت می‌شود؛ همین را در محدوده پارامتر بسته‌ایم.
 */
class DressMermaidGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'dress_mermaid';
    }

    public function label(): string
    {
        return 'پیراهن ماهی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 0.5, 'neck_width_extra' => 0.5, 'front_neck_depth_extra' => 2, 'waist_dart_share' => 0.7]),
            [
                'skirt_length' => [
                    'label' => 'بلندی دامن از کمر', 'min' => 55, 'max' => 125, 'step' => 1,
                    'default' => 92, 'unit' => 'سانتی‌متر',
                ],
                'flare_start' => [
                    'label' => 'شروع کلوش از کمر', 'min' => 35, 'max' => 75, 'step' => 1,
                    'default' => 52, 'unit' => 'سانتی‌متر',
                    'hint' => 'معمولاً چند سانتی‌متر بالای زانو؛ پایین‌تر از آن راه رفتن سخت می‌شود.',
                ],
                'flare' => [
                    'label' => 'گشادی هر پهلو در دم دامن', 'min' => 6, 'max' => 45, 'step' => 1,
                    'default' => 20, 'unit' => 'سانتی‌متر',
                ],
                'back_zip' => [
                    'label' => 'زیپ مرکز پشت', 'type' => 'toggle', 'default' => true,
                ],
                'bust_dart' => [
                    'label' => 'باز کردن درز روی نوک سینه', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $zip = $this->flag($params, 'back_zip', true);
        $length = (float) $this->param($params, 'skirt_length', 92);
        $flareStart = min((float) $this->param($params, 'flare_start', 52), $length - 12);
        $flare = (float) $this->param($params, 'flare', 20);

        $pieces = array_merge(
            $this->princessPanels($g, [
                'side' => 'front',
                'shape' => 'waist',
                'origin' => 'armhole',
                'bust_dart' => $this->flag($params, 'bust_dart', true),
                'prefix' => 'mermaid-',
            ]),
            $this->princessPanels($g, [
                'side' => 'back',
                'shape' => 'waist',
                'origin' => 'armhole',
                'prefix' => 'mermaid-',
                'center_cut' => $zip ? 2 : 1,
                'on_fold' => ! $zip,
            ]),
        );

        if ($zip) {
            foreach ($pieces as $index => $piece) {
                if (($piece['meta']['panel'] ?? null) === 'center' && ($piece['meta']['side'] ?? null) === 'back') {
                    $pieces[$index] = $this->markBackZip($piece, $g, null, $g['hip_drop']);
                }
            }
        }

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $skirt = $this->skirtPanel($g, [
                'side' => $side,
                'type' => 'mermaid',
                'length' => $length,
                'flare' => $flare,
                'code' => 'mermaid-skirt-'.$side,
                'name' => $isFront ? 'دامن ماهی جلو' : 'دامن ماهی پشت',
                'on_fold' => $isFront || ! $zip,
                'cut' => $isFront || ! $zip ? 1 : 2,
            ]);

            $skirt['meta']['flare_start'] = round($flareStart, 2);
            $skirt['meta']['notes'] = array_merge($skirt['meta']['notes'] ?? [], [
                'کلوش دامن از '.$this->fa(round($flareStart, 1)).' سانتی‌متر پایین‌تر از کمر شروع می‌شود.',
            ]);

            $pieces[] = $skirt;
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'mermaid-', 'width' => 6]);
        $pieces[] = $this->armholeBindingPiece(
            (float) ($pieces[1]['meta']['armhole_length'] ?? 0) + (float) ($pieces[3]['meta']['armhole_length'] ?? 0),
            ['prefix' => 'mermaid-'],
        );

        $pieces = array_merge($pieces, $this->liningSet($g, $params, [
            'prefix' => 'mermaid-',
            'shape' => 'fitted',
            'length' => min(30.0, $flareStart),
            'default' => true,
        ]));

        return $this->finishBlock($pieces, $g);
    }
}
