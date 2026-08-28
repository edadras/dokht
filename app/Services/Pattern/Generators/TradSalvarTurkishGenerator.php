<?php

namespace App\Services\Pattern\Generators;

/**
 * شالوارِ ترکی.
 *
 * شلوارِ بسیار گشاد با فاقِ بلند: پهنای بالای لِنگه چند برابرِ دور باسن است و
 * پارچهٔ اضافه با کشِ نیفه جمع می‌شود. لِنگهٔ چهارگوشِ فاق (گاسِت) اجباری است —
 * بی آن، فاقِ بلند هنگام نشستن پاره می‌شود.
 *
 * تفاوتش با شلوارِ شلوارقمیص در دو عدد است: پُریِ بیشتر و دمِ پای جمع‌تر، پس
 * سایه‌اش «کیسه‌ای» می‌شود نه «راست».
 */
class TradSalvarTurkishGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_salvar';
    }

    public function label(): string
    {
        return 'شالوار ترکی';
    }

    public static function group(): string
    {
        return 'traditional';
    }

    public function paramsSchema(): array
    {
        return [
            'fullness' => [
                'label' => 'پُری شلوار', 'min' => 1.6, 'max' => 3.2, 'step' => 0.05,
                'default' => 2.4,
                'hint' => 'ضریبِ دور باسن. شالوار از شلوارِ محلیِ معمولی پُرتر است.',
            ],
            'length' => [
                'label' => 'قد شلوار', 'min' => 70, 'max' => 120, 'step' => 1,
                'default' => 98, 'unit' => 'سانتی‌متر',
            ],
            'ankle' => [
                'label' => 'دور دم پا پس از چین', 'min' => 18, 'max' => 46, 'step' => 1,
                'default' => 26, 'unit' => 'سانتی‌متر',
                'hint' => 'دمِ پا با کش جمع می‌شود؛ این عدد اندازهٔ نهاییِ آن است.',
            ],
            'gusset' => [
                'label' => 'اندازه لِنگه فاق', 'min' => 12, 'max' => 28, 'step' => 1,
                'default' => 20, 'unit' => 'سانتی‌متر',
            ],
            'casing' => [
                'label' => 'بلندی نیفه کمر', 'min' => 3, 'max' => 10, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->shalwarPieces($measurements, [
            'prefix' => 'salvar-',
            'fullness' => (float) $this->param($params, 'fullness', 2.4),
            'length' => (float) $this->param($params, 'length', 98),
            'ankle' => (float) $this->param($params, 'ankle', 26),
            'gusset' => (float) $this->param($params, 'gusset', 20),
            'casing' => (float) $this->param($params, 'casing', 6),
            'ankle_gather' => 1.9,
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'شالوار از شلوارِ محلیِ معمولی پُرتر است؛ پارچهٔ اضافه با کشِ نیفه جمع می‌شود.',
            'لِنگهٔ فاق اجباری است: فاقِ بلندِ بی‌لِنگه هنگام نشستن پاره می‌شود.',
        ]);

        return $this->finish($pieces);
    }
}
