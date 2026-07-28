<?php

namespace App\Services\Pattern\Style\Hem;

use App\Support\Format;

/**
 * لبه گرد (دم‌کتی): پهلو بالا می‌آید و مرکز پایین می‌ماند، مثل دم پیراهن مردانه.
 *
 * چون همین اندازه از جلو و پشت با هم برداشته می‌شود، دو درز پهلو باز هم هم‌اندازه‌اند.
 */
class CurvedHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_curved';
    }

    public function label(): string
    {
        return 'لبه گرد (دم‌کتی)';
    }

    public function description(): string
    {
        return 'دم لباس در پهلو بالا می‌آید و در مرکز بلند می‌ماند؛ دم کلاسیک پیراهن.';
    }

    public function paramsSchema(): array
    {
        return [
            'rise' => [
                'label' => 'بالا آمدن پهلو', 'min' => 2, 'max' => 18, 'step' => 0.5, 'default' => 7,
                'unit' => 'سانتی‌متر', 'hint' => 'همین اندازه از درز پهلو کوتاه می‌شود.',
            ],
            'curve' => [
                'label' => 'گودی منحنی', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'allowance' => $this->allowanceParam(1.2),
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $rise = $this->num($context, 'rise', 7);
        $curve = $this->num($context, 'curve', 2.5);
        $allowance = $this->num($context, 'allowance', 1.2);
        $names = [];

        foreach ($this->hemHostIndexes($pieces) as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');

            if ($edge === null) {
                continue;
            }

            $piece = $this->shapeHem($piece, $edge, 0.0, -$rise, $curve);
            $piece = $this->setHemAllowance($piece, ($allowance * 2) + 0.4);
            $piece['meta']['hem_style'] = static::key();
            $piece['meta']['side_seam_change'] = round(-$rise, 2);

            $pieces[$index] = $this->reindexAnchors($piece);
            $names[] = $piece['name'];
        }

        return $this->result($pieces, [
            $this->note('tip', 'دم لباس گرد شد: پهلو '.Format::cm($rise)
                .' بالا آمد و مرکز سر جای خودش ماند ('.implode('، ', $names).').'),
            $this->note('info', 'درز پهلو '.Format::cm($rise).' کوتاه‌تر شد، ولی چون روی جلو و پشت '
                .'یکسان اجرا شده، دو درز باز هم هم‌اندازه‌اند.'),
            $this->note('warning', 'لبه گرد را باریک (حدود '.Format::cm($allowance)
                .') تو بگذارید؛ لبه پهن روی منحنی چین می‌خورد.'),
        ], ['rise' => round($rise, 2)]);
    }
}
