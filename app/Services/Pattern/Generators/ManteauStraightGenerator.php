<?php

namespace App\Services\Pattern\Generators;

/**
 * مانتو جلوباز راسته.
 *
 * پرکارترین برش مانتوی ایرانی: تنه از زیر بغل تا لبه پایین بدون کمرگیری می‌آید،
 * جلو با اضافه جای دکمه باز می‌شود، سجاف جلو و سجاف یقه پشت لبه را تمیز می‌کند و
 * آستین معمولی روی حلقه می‌نشیند. چاک پهلو دلخواه است و برای مانتوهای بلند
 * راحتی راه رفتن را برمی‌گرداند.
 */
class ManteauStraightGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'manteau_straight';
    }

    public function label(): string
    {
        return 'مانتو جلوباز راسته';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(),
            $this->fitParam('regular'),
            $this->garmentLengthParam(62, 20, 120),
            $this->openingParam('button', 2.5),
            $this->collarParam('stand'),
            $this->sleeveParam('set_in', 58),
            [
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 45, 'step' => 1,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 15, 17),
            $this->liningParam(false),
        );
    }

    /** فرم درز پهلوی این مدل: جذب کمرگیر، بقیه راسته. */
    protected function manteauShape(array $params): string
    {
        return $this->fitShape($params, ['fitted' => 'fitted', 'regular' => 'straight', 'loose' => 'straight']);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.5, 'regular' => 2.0, 'loose' => 4.0]);
        $vent = (float) $this->param($params, 'side_vent', 18);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'manteau-',
            'grow' => $grow,
            'shape' => $this->manteauShape($params),
            'front_name' => 'تنه جلوی مانتو',
            'back_name' => 'تنه پشت مانتو',
            'facing_width' => 9,
        ]);

        $pieces[0] = $this->markSideVent($pieces[0], $vent);
        $pieces[1] = $this->markSideVent($pieces[1], $vent);

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'manteau-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
