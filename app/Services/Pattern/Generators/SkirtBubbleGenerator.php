<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن حبابی.
 *
 * لایه رو از کمر و دم چین می‌خورد و دم آن به آسترِ کوتاه‌ترِ زیر دوخته می‌شود؛
 * همین کوتاه‌تر بودن آستر است که حباب را می‌سازد. آستر روی خط کمر با لایه رو یکی
 * دوخته می‌شود، پس فقط لایه رو در دور کمر شمرده می‌شود.
 */
class SkirtBubbleGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_bubble';
    }

    public function label(): string
    {
        return 'دامن حبابی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(50, 30, 90),
            [
                'puff' => [
                    'label' => 'بلندی اضافه لایه رو (حباب)', 'min' => 4, 'max' => 30, 'step' => 1, 'default' => 12,
                    'unit' => 'سانتی‌متر',
                ],
                'fullness' => [
                    'label' => 'نسبت پُری چین کمر', 'min' => 1.2, 'max' => 3, 'step' => 0.1, 'default' => 1.8,
                ],
                'hem_taper' => [
                    'label' => 'تنگی دم آستر', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 3,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 50);
        $puff = max(2.0, (float) $this->param($params, 'puff', 12));
        $ratio = max(1.1, (float) $this->param($params, 'fullness', 1.8));
        $taper = -abs((float) $this->param($params, 'hem_taper', 3));

        $fabric = max($mx['waist_target'] * $ratio, $mx['hip_target'] + 10);
        $liningHem = ($mx['quarter_hip'] + $taper) * 4;

        $note = 'لایه رو '.$this->fa(round($puff, 1)).' سانتی‌متر بلندتر از آستر بریده می‌شود و دم آن ('
            .$this->fa(round($fabric, 1)).' سانتی‌متر) با چین به دم آستر ('
            .$this->fa(round($liningHem, 1)).' سانتی‌متر) می‌رسد.';

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->rectPanel([
                'side' => $side,
                'part' => $side === 'front' ? 'skirt_front' : 'skirt_back',
                'code' => 'bubble-'.$side,
                'name' => $side === 'front' ? 'لایه رو جلو' : 'لایه رو پشت',
                'width' => $fabric / 4,
                'length' => $length + $puff,
                'waist_target' => $mx['waist_target'],
                'waist_finished' => round($mx['waist_target'] / 4, 2),
                'hip_y' => round($mx['hip_y'], 2),
                'notes' => [$note],
                'fullness' => [
                    $this->fullness('gather', 0, $fabric / 4, $mx['waist_target'] / 4, ['label' => 'چین کمر']),
                    $this->fullness('gather', 2, $fabric / 4, $liningHem / 4, ['label' => 'چین دم روی آستر']),
                ],
            ]);
        }

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->blockPanel($mx, [
                'side' => $side,
                'length' => $length,
                'hem_delta' => $taper,
                'dart_count' => $side === 'front' ? 1 : 2,
                'count_waist' => false,
                'code' => 'bubble-lining-'.$side,
                'name' => $side === 'front' ? 'آستر جلو' : 'آستر پشت',
                'part' => 'skirt_lining',
            ]);
        }

        return $this->finish(array_merge($pieces, $this->bandPieces($mx, $params)));
    }
}
