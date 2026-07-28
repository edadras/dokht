<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه دامن‌های پیلی‌دار با پیلی شمرده‌شده.
 *
 * حساب پارچه صریح است:
 *   پهنای پارچه = دور دم تمام‌شده + تعداد پیلی × جای هر پیلی
 * جای هر پیلی برای «تیغه‌ای» ۲×عمق و برای «جعبه‌ای» و «جعبه‌ای برعکس» ۴×عمق است.
 * چون پارچه از کمر تا دم یک پهنا دارد، هر پیلی در خط کمر به اندازه اختلاف کمر و
 * باسن عمیق‌تر بسته می‌شود و همین عدد در meta ثبت می‌شود.
 */
abstract class SkirtPleatBase extends SkirtBaseGenerator
{
    /** جای پارچه یک پیلی با این عمق. */
    abstract protected function pleatConsumption(float $depth): float;

    /** کلید سبک پیلی برای رندر و برگه فنی. */
    abstract protected function pleatStyle(): string;

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(58, 30, 110),
            [
                'pleat_count' => [
                    'label' => 'تعداد پیلی دور دامن', 'min' => 4, 'max' => 48, 'step' => 4,
                    'default' => $this->defaultCount(), 'hint' => 'به مضرب چهار گرد می‌شود تا چهار پنل قرینه شوند.',
                ],
                'pleat_depth' => [
                    'label' => 'عمق هر پیلی', 'min' => 1, 'max' => 12, 'step' => 0.5,
                    'default' => $this->defaultDepth(), 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم دامن روی باسن', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 4,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    protected function defaultCount(): int
    {
        return 16;
    }

    protected function defaultDepth(): float
    {
        return 4.0;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 58);
        $depth = max(0.5, (float) $this->param($params, 'pleat_depth', $this->defaultDepth()));
        $count = max(4, ((int) round(((float) $this->param($params, 'pleat_count', $this->defaultCount())) / 4)) * 4);

        $consumption = $this->pleatConsumption($depth);
        $finishedHem = $mx['hip_target'] + max(0.0, (float) $this->param($params, 'hem_ease', 4));
        $fabric = $finishedHem + ($count * $consumption);

        $note = 'پهنای دم دامن روی پارچه '.$this->fa(round($fabric, 1)).' سانتی‌متر است: '
            .$this->fa(round($finishedHem, 1)).' سانتی‌متر پهنای تمام‌شده + '
            .$this->fa(round($count * $consumption, 1)).' سانتی‌متر جای پیلی ('
            .$this->fa($count).' پیلی × '.$this->fa(round($consumption, 1)).' سانتی‌متر).';

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->pleatedRectPanel([
                'side' => $side,
                'part' => $side === 'front' ? 'skirt_front' : 'skirt_back',
                'code' => 'pleated-'.$side,
                'name' => $side === 'front' ? 'دامن پیلی‌دار جلو' : 'دامن پیلی‌دار پشت',
                'width' => $fabric / 4,
                'length' => $length,
                'pleats' => max(1, (int) ($count / 4)),
                'depth' => $depth,
                'style' => $this->pleatStyle(),
                'finished_waist' => $mx['waist_target'] / 4,
                'waist_target' => $mx['waist_target'],
                'hip_y' => round($mx['hip_y'], 2),
                'notes' => [$note],
            ]);
        }

        return $this->finish(array_merge($pieces, $this->bandPieces($mx, $params)));
    }
}
