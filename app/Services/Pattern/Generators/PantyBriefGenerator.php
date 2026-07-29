<?php

namespace App\Services\Pattern\Generators;

/**
 * شورت کلاسیک.
 *
 * کمر روی گودی کمر یا کمی پایین‌تر، پوشش کامل نشیمن، خط پای بلند و نوار فاق
 * پنبه‌ای. ساده‌ترین شورت کاتالوگ و همان که بیشتر دوخته می‌شود.
 *
 * دو چیز که در نگاه اول دیده نمی‌شوند ولی الگو را می‌سازند:
 *
 *   - **بلندی بیشترِ پشت روی مرکز پشت گرفته می‌شود، نه روی پهلو.** نشیمن گرد است
 *     و پارچهٔ بیشتری می‌خواهد؛ اگر آن پارچه را از پهلو بگیریم، درز پهلوی جلو و
 *     پشت هم‌اندازه نمی‌شوند و شورت روی تن می‌چرخد.
 *   - **نوار فاق جدا و پنبه‌ای است.** نه تزئین است نه انتخاب: پوست باید نفس بکشد.
 *     نوار میان لایهٔ رو و آستر گرفته می‌شود تا هیچ درزی روی پوست نیفتد.
 */
class PantyBriefGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'panty_brief';
    }

    public function label(): string
    {
        return 'شورت کلاسیک';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema(
            array_merge($this->bottomSchema(riseDrop: 2, gusset: 8, coverage: 'full'), [
                'seat' => [
                    'label' => 'بلندی بیشتر مرکز پشت', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                    'hint' => 'گودی نشیمن؛ روی مرکز پشت گرفته می‌شود تا درز پهلو هم‌اندازه بماند.',
                ],
                'waist_binding' => [
                    'label' => 'نوار کمر جدا (به‌جای کشِ روکار)', 'type' => 'toggle', 'default' => false,
                ],
            ]),
            stretch: 0.86,
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->pantyPanels($measurements, $params, [
            'prefix' => 'brief',
            'part' => 'panty',
            'rise_drop' => (float) $this->param($params, 'rise_drop', 2),
            'side_seam' => (float) $this->param($params, 'side_seam', 7),
            'coverage' => (string) $this->param($params, 'coverage', 'full'),
            'gusset' => (float) $this->param($params, 'gusset', 8),
            'seat' => (float) $this->param($params, 'seat', 4),
        ]);

        if ($this->flag($params, 'waist_binding', false)) {
            $stretch = $this->stretchOf($params);
            $waist = $this->m($measurements, 'waist', 74) * $stretch;
            $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.9)));

            $pieces[] = $this->bandPiece('brief-waist-binding', 'نوار کمر', ($waist * $ratio) / 2, 2.2, [
                'cut' => 2,
                'fold_line' => true,
                'part' => 'binding',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => [
                        'نوار دولا می‌شود و لبهٔ کمر را در خود می‌گیرد؛ به‌جای کشِ روکار.',
                        'نوار '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از خودِ لبه بریده شده و روی آن کشیده می‌شود.',
                    ],
                ],
            ]);
        }

        return $this->finishUnderwear($pieces, $this->underwearNotes($params, [
            'خط پا با کش سه‌میلی‌متری تمام می‌شود؛ کش پهن‌تر روی ران رد می‌اندازد.',
            'نوار فاق را پیش از دوختن درز پهلو بدوزید، وگرنه دو سرش زیر درز گیر می‌کند.',
        ]));
    }
}
