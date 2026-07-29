<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن پیلی آفتابی.
 *
 * پیلی آفتابی روی پارچه کلوش پرس می‌شود، پس درفت آن یک کلوش است که کمرش به اندازه
 * «نسبت پُری» بزرگ‌تر گرفته شده و بعد با پیلی به دور کمر واقعی جمع می‌شود:
 *   شعاع کمر = (دور کمر × نسبت پُری) ÷ (۲π × کسر دایره)
 * چون پیلی‌ها روی کمانِ کمر تنگ و روی کمانِ دم باز می‌شوند، پهنای پیلی از کمر به
 * دم زیاد می‌شود؛ همان چیزی که به این مدل نام آفتابی داده است.
 */
class SkirtSunburstGenerator extends SkirtCircleBase
{
    public static function key(): string
    {
        return 'skirt_pleat_sunburst';
    }

    public function label(): string
    {
        return 'دامن پیلی آفتابی';
    }

    protected function fraction(): float
    {
        return 0.5;
    }

    public function paramsSchema(): array
    {
        return array_merge(parent::paramsSchema(), [
            'fullness' => [
                'label' => 'نسبت پُری پارچه', 'min' => 1.4, 'max' => 3.0, 'step' => 0.1, 'default' => 2.0,
                'hint' => 'کمر پارچه چند برابر دور کمر بریده شود تا با پیلی جمع شود.',
            ],
            'pleat_count' => [
                'label' => 'تعداد پیلی دور دامن', 'min' => 12, 'max' => 96, 'step' => 4, 'default' => 48,
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $finished = $this->circleWaist($measurements, $ease, $params);
        $ratio = max(1.2, (float) $this->param($params, 'fullness', 2.0));
        $count = max(4, ((int) round(((float) $this->param($params, 'pleat_count', 48)) / 4)) * 4);
        $length = (float) $this->param($params, 'length', 65);
        $fabric = $finished * $ratio;
        $depth = ($fabric - $finished) / (2 * $count);

        $note = 'کمر پارچه '.$this->fa(round($fabric, 1)).' سانتی‌متر بریده می‌شود و با '
            .$this->fa($count).' پیلی (هرکدام '.$this->fa(round(2 * $depth, 2))
            .' سانتی‌متر جا) به '.$this->fa(round($finished, 1)).' سانتی‌متر می‌رسد.';

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->circlePanel([
                'side' => $side,
                'waist' => $fabric,
                'waist_target' => $finished,
                'waist_finished' => $finished / 4,
                'fraction' => $this->fraction(),
                'length' => $length,
                'code' => 'sunburst-'.$side,
                'name' => $side === 'front' ? 'دامن آفتابی جلو' : 'دامن آفتابی پشت',
                'fullness' => [
                    $this->fullness('pleat', 0, $fabric / 4, $finished / 4, [
                        'label' => 'پیلی آفتابی',
                        'style' => 'sunburst',
                        'count' => (int) ($count / 4),
                        'depth' => round($depth, 2),
                    ]),
                ],
                'notes' => [$note],
            ]);
        }

        if ($this->flag($params, 'waistband', true)) {
            $pieces[] = $this->bandPiece($finished, [
                'height' => (float) $this->param($params, 'waistband_height', 4),
            ]);
        }

        return $this->finishSkirt($pieces, $params);
    }
}
