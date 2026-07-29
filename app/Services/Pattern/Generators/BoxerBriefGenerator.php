<?php

namespace App\Services\Pattern\Generators;

/**
 * باکسر مردانه.
 *
 * شورتی با پاچهٔ کوتاه که تا میانهٔ ران می‌آید، با درز کناری یک‌سره از کمر تا دم
 * پاچه، و نوار فاق جدا.
 *
 * درز کناری اختیاری نیست: باکسرِ بدون درز کناری (که از یک پنل لوله‌ای دوخته
 * شود) روی ران می‌پیچد و بالا می‌رود، چون هیچ خطی نیست که پارچه را سرِ جایش
 * نگه دارد.
 *
 * یک نکتهٔ اندازه که همیشه جای اشتباه است: پهنای پاچه روی الگو **نصفِ دور دم
 * پاچه** است، نه فاصلهٔ افقی دو طرف ران. پارچه تخت بریده می‌شود و روی پا لوله
 * می‌شود؛ برای همین درز کناری از کمر به پایین به بیرون باز می‌شود، و این درست
 * است نه ایراد درفت.
 */
class BoxerBriefGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'boxer_brief';
    }

    public function label(): string
    {
        return 'باکسر مردانه';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema([
            'rise_drop' => [
                'label' => 'پایین‌تر نشستن کمر از خط کمر', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 5, 'unit' => 'سانتی‌متر',
            ],
            'leg_length' => [
                'label' => 'قد پاچه از خط فاق', 'min' => 5, 'max' => 28, 'step' => 1,
                'default' => 14, 'unit' => 'سانتی‌متر',
            ],
            'seat' => [
                'label' => 'بلندی بیشتر مرکز پشت', 'min' => 0, 'max' => 8, 'step' => 0.5,
                'default' => 3.5, 'unit' => 'سانتی‌متر',
            ],
            'gusset' => [
                'label' => 'پهنای نوار فاق', 'min' => 7, 'max' => 16, 'step' => 0.5,
                'default' => 10, 'unit' => 'سانتی‌متر',
                'hint' => 'در باکسر مردانه نوار فاق پهن‌تر از شورت زنانه است.',
            ],
            'waistband_height' => [
                'label' => 'بلندی کمرهٔ کشباف', 'min' => 2, 'max' => 6, 'step' => 0.5,
                'default' => 3.5, 'unit' => 'سانتی‌متر',
            ],
            'band_ratio' => [
                'label' => 'کوتاهی کمرهٔ کشباف', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                'default' => 0.85,
            ],
        ], stretch: 0.85);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->boxerPanels($measurements, $params, [
            'prefix' => 'boxer',
            'rise_drop' => (float) $this->param($params, 'rise_drop', 5),
            'leg_length' => (float) $this->param($params, 'leg_length', 14),
            'seat' => (float) $this->param($params, 'seat', 3.5),
            'gusset' => (float) $this->param($params, 'gusset', 10),
        ]);

        $stretch = $this->stretchOf($params);
        $rise = $this->bodyRise($measurements);
        $drop = (float) $this->param($params, 'rise_drop', 5);
        $waist = $this->m($measurements, 'waist', 82) * $stretch;
        $hip = $this->m($measurements, 'hip', 98) * $stretch;

        $girth = $waist + (($hip - $waist) * min(1.0, $drop / max(1.0, $rise)));
        $height = (float) $this->param($params, 'waistband_height', 3.5);
        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'band_ratio', 0.85)));

        $pieces[] = $this->bandPiece('boxer-waistband', 'کمرهٔ کشباف', ($girth * $ratio) / 2, $height, [
            'cut' => 2,
            'fold_line' => true,
            'part' => 'waistband',
            'meta' => [
                'girth_role' => 'trim',
                'band_girth' => round($girth, 2),
                'notions' => [[
                    'type' => 'elastic',
                    'label' => 'کمرهٔ کشباف '.$this->fa($height).' سانتی‌متری',
                    'length' => round($girth * $ratio, 1),
                    'edge_length' => round($girth, 1),
                ]],
                'notes' => [
                    'کمرهٔ آماده (کشباف بافته) به‌جای نوار پارچه‌ای هم می‌شود؛ بلندی و طول یکی است.',
                    'دو تکه بریده می‌شود، جلو و پشت، و روی درز کناری به هم می‌رسد.',
                ],
            ],
        ]);

        return $this->finishUnderwear($pieces, $this->underwearNotes($params, [
            'درز کناری از کمر تا دم پاچه یک‌سره است؛ بدون آن باکسر روی ران می‌پیچد.',
            'دم پاچه را با دوخت دوسوزنه برگردانید؛ نوار کش روی دم پاچه رد می‌اندازد.',
            'نوار فاق پهن‌تر از شورت زنانه است و از پنبه بریده می‌شود.',
        ]));
    }
}
