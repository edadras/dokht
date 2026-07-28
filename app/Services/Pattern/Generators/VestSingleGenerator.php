<?php

namespace App\Services\Pattern\Generators;

/**
 * جلیقه تک‌ردیف دکمه.
 *
 * تنه بدون آستین با حلقه‌ای که چند سانتی‌متر از حلقه لباس آستین‌دار تنگ‌تر و
 * بالاتر است تا زیر بغل باز نماند. حلقه و یقه با نوار اریب تمیز می‌شوند و جلو
 * یک رج دکمه دارد. پشت جلیقه یا از همان پارچه یا از آستر بریده می‌شود.
 */
class VestSingleGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'vest_single';
    }

    public function label(): string
    {
        return 'جلیقه تک‌ردیف دکمه';
    }

    /** هم‌پوشانی جلو: در جلیقه تک‌ردیف فقط اضافه جای دکمه است. */
    protected function overlap(array $params): float
    {
        return (float) $this->param($params, 'button_stand', 2);
    }

    /** تعداد رج دکمه. */
    protected function rows(): int
    {
        return 1;
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 0, 'neck_width_extra' => 1.5, 'front_neck_depth_extra' => 6, 'waist_dart_share' => 0.6]),
            $this->fitParam('fitted'),
            $this->garmentLengthParam(22, 4, 60),
            $this->openingParam('button', 2),
            [
                'armhole_lift' => [
                    'label' => 'بالا آمدن حلقه آستین', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'armhole_narrow' => [
                    'label' => 'تنگ‌تر شدن حلقه از سرشانه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'hem_point' => [
                    'label' => 'نوک‌دار بودن لبه جلو', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(false, 11, 12),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.0, 'loose' => 2.5]);
        $stand = $this->overlap($params);
        $length = (float) $this->param($params, 'length', 22);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'vest-',
            'grow' => $grow,
            'shape' => 'fitted',
            'stand' => $stand,
            'bust_dart' => true,
            'front_name' => 'تنه جلوی جلیقه',
            'back_name' => 'تنه پشت جلیقه',
            'facing_width' => 8,
            'buttons' => (int) $this->param($params, 'buttons', 4),
            'sleeve' => [],
            'panel' => [
                'armhole_drop' => -(float) $this->param($params, 'armhole_lift', 2),
                'shoulder_extra' => -(float) $this->param($params, 'armhole_narrow', 2),
            ],
            'collar' => 'none',
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 2)],
        ]);

        $armhole = $this->armholeOf([$pieces[0], $pieces[1]]);
        $pieces[] = $this->armholeBindingPiece($armhole, ['prefix' => 'vest-']);

        if ($this->rows() > 1) {
            $pieces[0]['meta']['double_breasted'] = true;
        }

        if ($this->flag($params, 'hem_point', true)) {
            $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
                'لبه پایین جلو به شکل نوک‌دار بریده می‌شود: از درز پهلو تا خط مرکز جلو '
                    .$this->fa(4).' سانتی‌متر پایین‌تر می‌آید.',
            ]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'vest-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
