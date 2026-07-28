<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه دامن‌های کلوش (کامل، نیم و ربع).
 *
 * شعاع کمر از خود اندازه کمر درمی‌آید نه از جدول:
 *   طول کمان کمر = کسر دایره × ۲π × شعاع = دور کمر  ⇒  شعاع = کمر ÷ (۲π × کسر)
 * هر قطعه نیمِ جلو یا نیمِ پشت است و روی تای پارچه بریده می‌شود، پس زاویه هر قطعه
 * یک‌چهارمِ کسرِ دایره است و چهار تای آن دقیقاً همان کسر را می‌سازد.
 */
abstract class SkirtCircleBase extends SkirtBaseGenerator
{
    /** کسر دایره: ۱ کلوش کامل، ۰٫۵ نیم‌کلوش، ۰٫۲۵ ربع‌کلوش. */
    abstract protected function fraction(): float;

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(65, 25, 130),
            [
                'waist_drop' => [
                    'label' => 'پایین‌تر نشستن کمر', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                    'hint' => 'اگر کمربند پایین‌تر از گودی کمر بنشیند، دور آن نقطه بزرگ‌تر است.',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $waist = $this->circleWaist($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 65);
        $fraction = $this->fraction();

        $pieces = [
            $this->circlePanel([
                'side' => 'front', 'waist' => $waist, 'fraction' => $fraction, 'length' => $length,
                'name' => 'دامن کلوش جلو',
            ]),
            $this->circlePanel([
                'side' => 'back', 'waist' => $waist, 'fraction' => $fraction, 'length' => $length,
                'name' => 'دامن کلوش پشت',
            ]),
        ];

        if ($this->flag($params, 'waistband', true)) {
            $pieces[] = $this->bandPiece($waist, [
                'height' => (float) $this->param($params, 'waistband_height', 4),
            ]);
        }

        return $this->finish($pieces);
    }

    /**
     * دور کمری که کمان از آن حساب می‌شود.
     *
     * اگر کمربند پایین‌تر از گودی کمر بنشیند، دور بدن در آن نقطه به نسبت فاصله تا
     * باسن بزرگ‌تر است و همان عدد ملاک شعاع می‌شود.
     */
    protected function circleWaist(array $measurements, array $ease, array $params): float
    {
        $waist = $this->m($measurements, 'waist', 74) + $this->ease($ease, 'waist', 4);
        $hip = $this->m($measurements, 'hip', 98) + $this->ease($ease, 'hip', 6);
        $drop = max(0.0, (float) $this->param($params, 'waist_drop', 0));
        $hipY = max(12.0, $this->m($measurements, 'waist_to_hip', 21));

        if ($drop < 0.1 || $hip <= $waist) {
            return $waist;
        }

        return $waist + (($hip - $waist) * min(1.0, $drop / $hipY));
    }
}
