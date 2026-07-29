<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن چین‌دار (دیرندل).
 *
 * دو مستطیل ساده که با چین به کمر بسته می‌شوند. پهنای پارچه از نسبت پُری می‌آید،
 * ولی هرگز کمتر از دور باسن به‌علاوه آزادی نمی‌شود وگرنه دامن از روی باسن رد نمی‌شود.
 */
class SkirtGatheredGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_gathered';
    }

    public function label(): string
    {
        return 'دامن چین‌دار (دیرندل)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(58, 25, 115),
            [
                'fullness' => [
                    'label' => 'نسبت پُری چین', 'min' => 1.2, 'max' => 3.5, 'step' => 0.1, 'default' => 1.8,
                    'hint' => 'پارچه چند برابر دور کمر بریده شود.',
                ],
                'hip_clearance' => [
                    'label' => 'حداقل آزادی روی باسن', 'min' => 2, 'max' => 25, 'step' => 1, 'default' => 8,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 58);
        $ratio = max(1.1, (float) $this->param($params, 'fullness', 1.8));
        $clearance = max(0.0, (float) $this->param($params, 'hip_clearance', 8));

        $needed = $mx['hip_target'] + $clearance;
        $fabric = max($mx['waist_target'] * $ratio, $needed);
        $realRatio = $fabric / $mx['waist_target'];

        $note = 'پهنای پارچه دور دامن '.$this->fa(round($fabric, 1)).' سانتی‌متر است ('
            .$this->fa(round($realRatio, 2)).' برابر دور کمر) و با چین به '
            .$this->fa(round($mx['waist_target'], 1)).' سانتی‌متر جمع می‌شود.';

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->rectPanel([
                'side' => $side,
                'part' => $side === 'front' ? 'skirt_front' : 'skirt_back',
                'code' => 'gathered-'.$side,
                'name' => $side === 'front' ? 'دامن چین‌دار جلو' : 'دامن چین‌دار پشت',
                'width' => $fabric / 4,
                'length' => $length,
                'waist_target' => $mx['waist_target'],
                'waist_finished' => round($mx['waist_target'] / 4, 2),
                'hip_y' => round($mx['hip_y'], 2),
                'notes' => [$note],
                'fullness' => [
                    $this->fullness('gather', 0, $fabric / 4, $mx['waist_target'] / 4, [
                        'label' => 'چین کمر',
                    ]),
                ],
            ]);
        }

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }
}
