<?php

namespace App\Services\Pattern\Generators;

/**
 * مانتو کمردار.
 *
 * تنه با ساسون کمر و درز پهلوی کمرگیر فرم می‌گیرد و یک کمربند پارچه‌ای روی خط
 * کمر بسته می‌شود. حلقه‌های کمربند روی درز پهلو و هم‌تراز خط کمر دوخته می‌شوند،
 * پس کمربند سر نمی‌خورد.
 */
class ManteauBeltedGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'manteau_belted';
    }

    public function label(): string
    {
        return 'مانتو کمردار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['waist_dart_share' => 0.6]),
            $this->fitParam('fitted'),
            $this->garmentLengthParam(58, 20, 115),
            $this->openingParam('button', 2.5),
            $this->collarParam('turn'),
            $this->sleeveParam('set_in', 58),
            [
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 25, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'belt_width' => [
                    'label' => 'پهنای کمربند', 'min' => 2, 'max' => 9, 'step' => 0.5,
                    'default' => 4.5, 'unit' => 'سانتی‌متر',
                ],
                'belt_loops' => [
                    'label' => 'حلقه کمربند داشته باشد', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 15, 17),
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]);
        $beltWidth = (float) $this->param($params, 'belt_width', 4.5);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'manteau-belted-',
            'grow' => $grow,
            'shape' => 'fitted',
            'bust_dart' => true,
            'front_name' => 'تنه جلوی مانتو کمردار',
            'back_name' => 'تنه پشت مانتو کمردار',
            'facing_width' => 9,
        ]);

        $pieces[] = $this->beltPiece($measurements, $params, ['prefix' => 'manteau-belted-', 'width' => $beltWidth]);

        if ($this->flag($params, 'belt_loops', true)) {
            $pieces[] = $this->beltLoopPiece($beltWidth, ['prefix' => 'manteau-belted-', 'cut' => 2]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'manteau-belted-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
