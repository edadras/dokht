<?php

namespace App\Services\Pattern\Generators;

/**
 * سوتین بدون فنر.
 *
 * کاپ دوتکه، سینه‌بند کشی و بست قزنی پشت؛ همان سوتین روزمره‌ای که بیشترین
 * فروش را دارد و کمترین جزئیات را می‌بخشد.
 *
 * «بدون فنر» یعنی هیچ سیمی زیر کاپ نیست که وزن سینه را بگیرد، پس آن وزن جای
 * دیگری باید برود. سه تصمیم در این الگو دقیقاً برای همین است:
 *
 *   - **سینه‌بند بلندتر و تنگ‌تر.** بدون فنر، تمام وزن روی نوار زیر سینه می‌افتد.
 *     پس کشِ زیر سینه این‌جا محسوس‌تر از بقیهٔ لبه‌ها کوتاه بریده می‌شود.
 *   - **درزِ کاپ بالا و پایین.** کاپ یک‌تکه روی سینه تخت می‌ماند؛ فقط درزِ افقی
 *     است که به آن عمق می‌دهد، و بدون فنر این عمق تنها چیزی است که سینه را
 *     نگه می‌دارد.
 *   - **قاب کاپ از پارچهٔ کم‌کشش.** اگر قاب هم کش بیاید، کاپ زیر سینه می‌افتد و
 *     سوتین بی‌فایده می‌شود.
 */
class BraSoftGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'bra_soft';
    }

    public function label(): string
    {
        return 'سوتین بدون فنر';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema(
            array_merge($this->braSchema(bandHeight: 4, cupRatio: 0.23), [
                'band_ratio' => [
                    'label' => 'کوتاهی کش زیر سینه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                    'default' => 0.82,
                    'hint' => 'کوتاه‌تر از بقیهٔ لبه‌ها، چون بدون فنر همهٔ وزن روی همین نوار است.',
                ],
                'cup_lining' => [
                    'label' => 'آستر کاپ از تور بی‌کشش', 'type' => 'toggle', 'default' => true,
                ],
            ]),
            stretch: 0.9,
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $bandRatio = (float) $this->param($params, 'band_ratio', 0.82);

        $pieces = $this->braPieces($measurements, $params, [
            'prefix' => 'bra-soft',
            'cup_ratio' => (float) $this->param($params, 'cup_ratio', 0.23),
            'band_height' => (float) $this->param($params, 'band_height', 4),
            'hook_rows' => (float) $this->param($params, 'hook_rows', 2),
            'hook_columns' => (float) $this->param($params, 'hook_columns', 3),
            'strap_width' => (float) $this->param($params, 'strap_width', 1.5),
            'cup_cut' => $this->flag($params, 'cup_lining', true) ? 4 : 2,
        ]);

        // کش زیر سینه جدا از بقیهٔ لبه‌ها و کوتاه‌تر: بدون فنر، این نوار تنها
        // چیزی است که سوتین را روی تن نگه می‌دارد
        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['bra_cradle', 'bra_wing'], true)) {
                continue;
            }

            $pieces[$index]['meta']['notions'] = array_values(array_filter(
                $piece['meta']['notions'] ?? [],
                fn (array $notion) => ($notion['type'] ?? '') !== 'elastic',
            ));

            $pieces[$index] = $this->elasticOn(
                $pieces[$index],
                'hem',
                'کش زیر سینه — '.($piece['meta']['part'] === 'bra_cradle' ? 'قاب جلو' : 'بال پشت'),
                $params,
                $bandRatio,
            );
        }

        return $this->finishUnderwear($pieces, $this->underwearNotes($params, [
            'کش زیر سینه '.$this->fa(round((1 - $bandRatio) * 100)).' درصد کوتاه‌تر از خودِ لبه بریده شده؛'
                .' بدون فنر، همهٔ وزن روی همین نوار می‌افتد.',
            'اگر سینه‌بند بالا می‌رود، پیش از هر تغییری همین کش را کوتاه‌تر کنید؛ بلند کردن بند شانه کار را بدتر می‌کند.',
            'کاپ را از پارچهٔ کم‌کشش و در راستای پارچه ببرید؛ کاپ کشی روی سینه پهن می‌شود و فرمش را از دست می‌دهد.',
        ]));
    }
}
