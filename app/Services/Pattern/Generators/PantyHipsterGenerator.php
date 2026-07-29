<?php

namespace App\Services\Pattern\Generators;

/**
 * شورت هیپستر.
 *
 * همان شورت، ولی کمرش روی خط باسن می‌نشیند نه روی گودی کمر. این یک جملهٔ ساده،
 * سه پیامد در الگو دارد و هر سه این‌جا هست:
 *
 *   ۱. **دور کمرِ شورت دیگر دور کمر نیست.** روی خط باسن، دور بدن بزرگ‌تر است؛ پس
 *      پهنای بالای پنل از میان کمر و باسن حساب می‌شود، نه از خودِ کمر. اگر این
 *      نشود، شورت اصلاً بالا نمی‌آید.
 *   ۲. **درز پهلو کوتاه‌تر است.** فاصلهٔ کمرِ شورت تا خط پا کم می‌شود، پس خط پا
 *      کم‌عمق‌تر و صاف‌تر می‌شود.
 *   ۳. **کش کمر پهن‌تر و شل‌تر.** روی باسن، کشِ تنگ رد می‌اندازد و بالا هم می‌رود؛
 *      نوار پهن‌تر فشار را پخش می‌کند.
 */
class PantyHipsterGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'panty_hipster';
    }

    public function label(): string
    {
        return 'شورت هیپستر';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema(
            array_merge($this->bottomSchema(riseDrop: 9, gusset: 8, coverage: 'medium'), [
                'seat' => [
                    'label' => 'بلندی بیشتر مرکز پشت', 'min' => 0, 'max' => 8, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'waist_band' => [
                    'label' => 'بلندی نوار کمر', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'روی خط باسن، نوار پهن‌تر فشار را پخش می‌کند و رد نمی‌اندازد.',
                ],
            ]),
            stretch: 0.86,
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $drop = (float) $this->param($params, 'rise_drop', 9);

        $pieces = $this->pantyPanels($measurements, $params, [
            'prefix' => 'hipster',
            'part' => 'panty',
            'rise_drop' => $drop,
            // درز پهلوی هیپستر ناچار کوتاه‌تر است: کمر پایین‌تر آمده ولی فاق سر جایش مانده
            'side_seam' => min((float) $this->param($params, 'side_seam', 7), 6.0),
            'coverage' => (string) $this->param($params, 'coverage', 'medium'),
            'gusset' => (float) $this->param($params, 'gusset', 8),
            'seat' => (float) $this->param($params, 'seat', 2.5),
        ]);

        $stretch = $this->stretchOf($params);
        $rise = $this->bodyRise($measurements);
        $waist = $this->m($measurements, 'waist', 74) * $stretch;
        $hip = $this->m($measurements, 'hip', 98) * $stretch;

        // دور همان‌جایی که نوار کمر می‌نشیند، نه دور کمر
        $girth = $waist + (($hip - $waist) * min(1.0, $drop / max(1.0, $rise)));
        $height = (float) $this->param($params, 'waist_band', 3);
        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.9)));

        $pieces[] = $this->bandPiece('hipster-band', 'نوار کمر', ($girth * $ratio) / 2, $height, [
            'cut' => 2,
            'fold_line' => true,
            'part' => 'binding',
            'meta' => [
                'girth_role' => 'trim',
                'band_girth' => round($girth, 2),
                'notions' => [[
                    'type' => 'elastic',
                    'label' => 'کش کمر '.$this->fa($height).' سانتی‌متری',
                    'length' => round($girth * $ratio, 1),
                    'edge_length' => round($girth, 1),
                ]],
                'notes' => [
                    'نوار روی دورِ همان تراز بریده شده ('.$this->fa(round($girth)).' سانتی‌متر)، نه روی دور کمر؛'
                        .' شورت هیپستر روی باسن می‌نشیند و آن‌جا بدن بزرگ‌تر است.',
                    'دو تکه بریده می‌شود، جلو و پشت، و روی درز پهلو به هم می‌رسد.',
                ],
            ],
        ]);

        return $this->finishUnderwear($pieces, $this->underwearNotes($params, [
            'کمرِ شورت روی خط باسن می‌نشیند؛ دور بالای الگو از میان کمر و باسن حساب شده، نه از خودِ کمر.',
            'اگر شورت پایین می‌افتد، اول نوار کمر را کوتاه‌تر کنید نه کل الگو را تنگ‌تر.',
        ]));
    }
}
