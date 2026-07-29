<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن طبقه‌ای.
 *
 * هر طبقه یک مستطیل است که به طبقه بالاتر چین داده می‌شود:
 *   پهنای طبقه n = پهنای طبقه n−۱ × نسبت طبقه
 * طبقه اول با چین به کمر بسته می‌شود، پس پهنای آن هم نسبت پُری کمر را دارد و هم
 * از دور باسن بزرگ‌تر است تا دامن از روی باسن رد شود.
 */
class SkirtTieredGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_tiered';
    }

    public function label(): string
    {
        return 'دامن طبقه‌ای';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(75, 35, 125),
            [
                'tiers' => [
                    'label' => 'تعداد طبقه', 'min' => 2, 'max' => 5, 'step' => 1, 'default' => 3,
                ],
                'tier_ratio' => [
                    'label' => 'نسبت پهنای هر طبقه به طبقه بالا', 'min' => 1.1, 'max' => 2.2, 'step' => 0.05,
                    'default' => 1.5,
                ],
                'tier_growth' => [
                    'label' => 'نسبت بلندی هر طبقه به طبقه بالا', 'min' => 0.8, 'max' => 1.8, 'step' => 0.05,
                    'default' => 1.2,
                ],
                'waist_gather' => [
                    'label' => 'نسبت چین کمر', 'min' => 1, 'max' => 2.5, 'step' => 0.05, 'default' => 1.35,
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 75);
        $tiers = max(2, min(5, (int) $this->param($params, 'tiers', 3)));
        $ratio = max(1.05, (float) $this->param($params, 'tier_ratio', 1.5));
        $growth = max(0.6, (float) $this->param($params, 'tier_growth', 1.2));
        $gather = max(1.0, (float) $this->param($params, 'waist_gather', 1.35));

        $heightUnits = 0.0;

        for ($i = 0; $i < $tiers; $i++) {
            $heightUnits += $growth ** $i;
        }

        $firstHeight = $length / $heightUnits;
        $width = max($mx['waist_target'] * $gather, $mx['hip_target'] + 6);
        $pieces = [];
        $previous = $mx['waist_target'];

        for ($i = 0; $i < $tiers; $i++) {
            $height = $firstHeight * ($growth ** $i);
            $isFirst = $i === 0;

            $meta = [
                'side' => 'front',
                'part' => 'skirt_tier',
                'code' => 'tier-'.($i + 1),
                'name' => 'طبقه '.$this->fa($i + 1),
                'width' => $width / 2,
                'length' => $height,
                'cut_quantity' => 2,
                'on_fold' => false,
                'top_edge' => $isFirst ? 'waist' : 'default',
                'hip_y' => $isFirst ? round($mx['hip_y'], 2) : null,
                'fullness' => [
                    $this->fullness('gather', 0, $width / 2, $previous / 2, [
                        'label' => $isFirst ? 'چین کمر' : 'چین درز طبقه',
                    ]),
                ],
                'notes' => [
                    'طبقه '.$this->fa($i + 1).': پارچه '.$this->fa(round($width, 1))
                        .' سانتی‌متر با چین به '.$this->fa(round($previous, 1)).' سانتی‌متر می‌رسد ('
                        .$this->fa(round($width / $previous, 2)).' برابر).',
                ],
            ];

            if ($isFirst) {
                $meta['waist_target'] = $mx['waist_target'];
                $meta['waist_finished'] = round($mx['waist_target'] / 2, 2);
            }

            $pieces[] = $this->rectPanel(array_filter($meta, fn ($value) => $value !== null));
            $previous = $width;
            $width *= $ratio;
        }

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }
}
