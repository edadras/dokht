<?php

namespace App\Services\Pattern\Style\Hem;

use App\Support\Format;

/** لبه صاف: ساده‌ترین دم لباس؛ فقط پهنای تو گذاشتن تعیین می‌شود. */
class StraightHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_straight';
    }

    public function label(): string
    {
        return 'لبه صاف';
    }

    public function description(): string
    {
        return 'دم لباس صاف می‌ماند و فقط به اندازه دلخواه تو برگردانده می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'allowance' => $this->allowanceParam(3.0),
            'double_fold' => [
                'label' => 'دو بار تا شود', 'type' => 'toggle', 'default' => true,
                'hint' => 'لبه خام دیده نمی‌شود ولی پارچه بیشتری می‌خورد.',
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $allowance = $this->num($context, 'allowance', 3);
        $double = $this->flag($context, 'double_fold', true);
        $needed = $double ? ($allowance * 2) + 0.5 : $allowance + 1;
        $names = [];

        foreach ($this->hemHostIndexes($pieces) as $index) {
            $piece = $this->setHemAllowance($pieces[$index], $needed);
            $piece['meta']['hem_style'] = static::key();
            $pieces[$index] = $piece;
            $names[] = $piece['name'];
        }

        return $this->result($pieces, [
            $this->note('tip', 'لبه صاف با '.Format::cm($allowance).' تو گذاشتن روی '
                .implode('، ', $names).' تنظیم شد.'),
            $this->fabricNote($needed, $double ? 'لبه دو بار تاشده' : 'لبه یک بار تاشده'),
        ], ['allowance' => round($needed, 2)]);
    }
}
