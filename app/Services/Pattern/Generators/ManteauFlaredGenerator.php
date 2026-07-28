<?php

namespace App\Services\Pattern\Generators;

/**
 * مانتو جلوباز کلوش.
 *
 * تنه از زیر بغل تا خط کمر آرام گرفته می‌شود و از کمر به پایین کلوش می‌رود، پس
 * لبه پایین پُر می‌افتد و لباس روی باسن نمی‌چسبد. برای پارچه‌های خوش‌ریزش مثل
 * کرپ و لینن بهترین برش مانتو است.
 */
class ManteauFlaredGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'manteau_flared';
    }

    public function label(): string
    {
        return 'مانتو جلوباز کلوش';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(),
            $this->fitParam('regular'),
            $this->garmentLengthParam(66, 25, 120),
            $this->openingParam('button', 2.5),
            $this->collarParam('none'),
            $this->sleeveParam('set_in', 58),
            [
                'hem_flare' => [
                    'label' => 'کلوشی هر پهلو در لبه پایین', 'min' => 4, 'max' => 40, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
                'waist_shape' => [
                    'label' => 'کمرگیری', 'type' => 'toggle', 'default' => true,
                    'hint' => 'با کمرگیری، لباس روی کمر جمع و از آنجا کلوش می‌شود.',
                ],
            ],
            $this->pocketParam(false, 14, 16),
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'manteau-flared-',
            'grow' => $grow,
            'shape' => $this->flag($params, 'waist_shape', true) ? 'flare' : 'trapeze',
            'front_name' => 'تنه جلوی مانتو کلوش',
            'back_name' => 'تنه پشت مانتو کلوش',
            'facing_width' => 9,
            'panel' => ['waist_dart' => false],
        ]);

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'manteau-flared-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
