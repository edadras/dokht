<?php

namespace App\Services\Pattern\Generators;

/**
 * سوتین پوش‌آپ.
 *
 * همان کاپ دوتکه، ولی با دو چیز که پوش‌آپ را پوش‌آپ می‌کند:
 *
 *   ۱. **لایهٔ اسفنجی زیر کاپ.** لایه در پایین کاپ ضخیم‌تر از بالاست؛ همین است که
 *      سینه را بالا می‌آورد، نه بزرگ‌تر. لایه هشت درصد کوچک‌تر از کاپ بریده
 *      می‌شود تا لبه‌اش زیر درز پنهان شود.
 *   ۲. **ساسون پایین کاپ.** ساسون از لبهٔ نشستن کاپ به سمت نوک باز می‌شود و
 *      پارچه را به وسط می‌راند. این ساسون لبهٔ پایین کاپ را به اندازهٔ خودش کوتاه
 *      می‌کند، پس قاب کاپ هم باید همان‌قدر کوتاه‌تر بریده شود — وگرنه کاپ روی قاب
 *      چین می‌خورد. این‌جا خودِ درفت این را حساب می‌کند، نه خیاط.
 *
 * برخلاف سوتین بدون فنر، این مدل فنر دارد: بالا آوردن سینه فشار زیادی روی لبهٔ
 * کاپ می‌آورد و بدون فنر آن لبه تا می‌خورد.
 */
class BraPushUpGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'bra_push_up';
    }

    public function label(): string
    {
        return 'سوتین پوش‌آپ';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema(
            array_merge($this->braSchema(bandHeight: 3.5, cupRatio: 0.21), [
                'cup_dart' => [
                    'label' => 'دهانهٔ ساسون پایین کاپ', 'min' => 0.6, 'max' => 4, 'step' => 0.1,
                    'default' => 1.8, 'unit' => 'سانتی‌متر',
                    'hint' => 'ساسون بزرگ‌تر یعنی کاپ بالاتر و جمع‌تر؛ لبهٔ پایین کاپ هم به همان اندازه کوتاه‌تر می‌شود.',
                ],
                'underwire' => [
                    'label' => 'فنر زیر کاپ', 'type' => 'toggle', 'default' => true,
                ],
                'pad' => [
                    'label' => 'لایهٔ اسفنجی پوش‌آپ', 'type' => 'toggle', 'default' => true,
                ],
            ]),
            stretch: 0.9,
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $dart = (float) $this->param($params, 'cup_dart', 1.8);

        $pieces = $this->braPieces($measurements, $params, [
            'prefix' => 'bra-push',
            'cup_ratio' => (float) $this->param($params, 'cup_ratio', 0.21),
            'band_height' => (float) $this->param($params, 'band_height', 3.5),
            'hook_rows' => (float) $this->param($params, 'hook_rows', 2),
            'hook_columns' => (float) $this->param($params, 'hook_columns', 3),
            'strap_width' => (float) $this->param($params, 'strap_width', 1.5),
            'cup_dart' => $dart,
            'padded' => $this->flag($params, 'pad', true),
            'underwire' => $this->flag($params, 'underwire', true),
        ]);

        return $this->finishUnderwear($pieces, $this->underwearNotes($params, [
            'ساسون پایین کاپ '.$this->fa($dart).' سانتی‌متر است و لبهٔ نشستن کاپ را به همان اندازه کوتاه می‌کند؛'
                .' قاب کاپ در همین الگو کوتاه‌تر بریده شده تا دو لبه بر هم بنشینند.',
            'ساسون را پیش از دوختن درزِ کاپ ببندید و اتوی آن را به سمت مرکز جلو بخوابانید.',
            'لایهٔ اسفنجی را نکشید؛ اسفنج کشیده‌شده پس از چند شست‌وشو موج می‌افتد.',
        ]));
    }
}
