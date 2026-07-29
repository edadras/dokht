<?php

namespace App\Services\Pattern\Generators;

/**
 * پپلوم: دامنکِ کوتاه کلوش که روی خط کمرِ بالاتنه دوخته می‌شود.
 *
 * شعاع کمر مثل دامن کلوش از خود اندازه کمر درمی‌آید، فقط قد آن کوتاه است و
 * کمربند ندارد چون به بالاتنه وصل می‌شود.
 */
class SkirtPeplumGenerator extends SkirtCircleBase
{
    public static function key(): string
    {
        return 'skirt_peplum';
    }

    public function label(): string
    {
        return 'پپلوم';
    }

    protected function fraction(): float
    {
        return 1.0;
    }

    public function paramsSchema(): array
    {
        $schema = array_merge(
            $this->lengthParam(20, 8, 45),
            [
                'circle' => [
                    'label' => 'میزان کلوش', 'type' => 'select', 'default' => 1,
                    // کلیدها رشته‌اند تا PHP اعشار را به عدد صحیح تبدیل نکند
                    'options' => ['0.25' => 'ربع دایره (کم‌موج)', '0.5' => 'نیم دایره', '1' => 'دایره کامل (پرموج)'],
                ],
                'back_longer' => [
                    'label' => 'بلندتر بودن پشت', 'min' => 0, 'max' => 25, 'step' => 1, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4, false),
        );

        // پپلوم به بالاتنه دوخته می‌شود و بستِ خودش را ندارد
        unset($schema['waist_drop'], $schema['zip']);

        return $schema;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $waist = $this->m($measurements, 'waist', 74) + $this->ease($ease, 'waist', 4);
        $length = (float) $this->param($params, 'length', 20);
        $backExtra = max(0.0, (float) $this->param($params, 'back_longer', 0));
        $fraction = (float) $this->param($params, 'circle', 1);
        $fraction = in_array($fraction, [0.25, 0.5, 1.0], true) ? $fraction : 1.0;

        $pieces = [
            $this->circlePanel([
                'side' => 'front', 'waist' => $waist, 'fraction' => $fraction, 'length' => $length,
                'code' => 'peplum-front', 'name' => 'پپلوم جلو', 'part' => 'peplum',
            ]),
            $this->circlePanel([
                'side' => 'back', 'waist' => $waist, 'fraction' => $fraction, 'length' => $length + $backExtra,
                'code' => 'peplum-back', 'name' => 'پپلوم پشت', 'part' => 'peplum',
            ]),
        ];

        if ($this->flag($params, 'waistband', false)) {
            $pieces[] = $this->bandPiece($waist, [
                'height' => (float) $this->param($params, 'waistband_height', 4),
            ]);
        }

        return $this->finishSkirt($pieces, $params);
    }
}
