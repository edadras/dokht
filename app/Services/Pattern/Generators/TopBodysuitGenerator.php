<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * بادی (تاپ سرخود).
 *
 * تاپی که به‌جای تمام شدن روی کمر، از زیر پا رد می‌شود و با قزن بسته می‌شود؛
 * برای همین هیچ‌وقت از شلوار بیرون نمی‌زند.
 *
 * دو چیز بادی را از تاپ بلند جدا می‌کند و هر دو حساب دارند:
 *
 *   ۱. «قد فاق» — فاصلهٔ کمر تا زیر پا از جلو و پشت. اگر کوتاه بریده شود لباس
 *      روی شانه فشار می‌آورد و بالا می‌کشد؛ برای همین از اندازهٔ بدن خوانده
 *      می‌شود، نه از یک عدد ثابت.
 *   ۲. کشسانی. بادی بدون کشسانی پوشیده و درآورده نمی‌شود، پس الگو با آزادی
 *      منفی بریده می‌شود و اگر پارچه کشی نباشد باید بست پهلو داشته باشد.
 *
 * لبهٔ پا و درز فاق با کش تمام می‌شوند و ردیف قزن روی نوار فاق می‌نشیند.
 */
class TopBodysuitGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_bodysuit';
    }

    public function label(): string
    {
        return 'بادی (تاپ سرخود)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            [
                'shoulder_width' => [
                    'label' => 'بالای بادی', 'type' => 'select', 'default' => 'strap',
                    'options' => [
                        'full' => 'سرشانهٔ کامل (آستین‌دار)',
                        'strap' => 'بند پهن',
                        'thin' => 'بند باریک',
                    ],
                ],
                'neck_drop' => [
                    'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 22, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'leg_rise' => [
                    'label' => 'بالا آمدن خط پا', 'min' => 0, 'max' => 14, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                    'hint' => 'هرچه بیشتر، خط پا بازتر و بالاتر (فرم فرانسوی).',
                ],
                'crotch_ease' => [
                    'label' => 'آزادی قد فاق', 'min' => -4, 'max' => 8, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                    'hint' => 'اگر بادی روی شانه فشار می‌آورد، این عدد را زیاد کنید.',
                ],
                'gusset' => [
                    'label' => 'نوار فاق با قزن', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 0);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->knitEase($ease, 4.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $width = (string) $this->param($params, 'shoulder_width', 'strap');
        $strap = match ($width) {
            'thin' => 2.0,
            'strap' => 4.5,
            default => null,
        };

        // قد فاق از اندازهٔ بدن می‌آید: کمر تا نشیمن، رفت و برگشت
        $rise = (float) ($measurements['rise'] ?? $measurements['crotch_depth'] ?? 27);
        $crotch = $rise + (float) $this->param($params, 'crotch_ease', 2);
        $legRise = (float) $this->param($params, 'leg_rise', 4);

        $shared = [
            'shape' => 'straight',
            'length' => $crotch,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        if ($strap !== null) {
            $shared['shoulder_extra'] = ($g['neck_width'] + $strap) - $g['shoulder_half'];
            $shared['across_extra'] = -min(4.0, $strap * 0.5);
            $shared['armhole_drop'] = 2.0;
        }

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'bodysuit-front',
            'name' => 'بادی جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 6),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'bodysuit-back',
            'name' => 'بادی پشت',
        ]));

        // خط پا روی جلو و پشت در یک ارتفاع به درز پهلو می‌رسد
        $legSideY = max(
            2.0,
            min(
                Geometry::bounds($front['outline'])[3],
                Geometry::bounds($back['outline'])[3],
            ) - 6.0 - $legRise,
        );

        $front = $this->carveLeg($front, $legSideY);
        $back = $this->carveLeg($back, $legSideY);

        $pieces = [$front, $back];

        if ($this->flag($params, 'gusset', true)) {
            $pieces[] = $this->bandPiece('bodysuit-gusset', 'نوار فاق', 14, 7, [
                'cut' => 2, 'part' => 'gusset',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['دو لایه بریده می‌شود؛ ردیف قزن روی همین نوار می‌نشیند.'],
                ],
            ]);
        }

        $notes = [
            $this->finishNote($params, ['یقه', 'حلقه', 'لبهٔ پا']),
            ['type' => 'info', 'text' => 'قد فاق از اندازهٔ بدن خوانده شد ('.$this->fa(round($crotch)).' سانتی‌متر از خط کمر)؛ اگر بادی روی شانه می‌کشد، «آزادی قد فاق» را بالا ببرید.'],
            ['type' => 'info', 'text' => 'لبهٔ پا با کش تمام می‌شود؛ کش را حدود ۱۵ درصد کوتاه‌تر از خودِ لبه ببرید تا جمع کند و باز نماند.'],
            ['type' => 'warning', 'text' => 'الگو با آزادی منفی برای پارچهٔ کشی بریده شده؛ روی پارچهٔ بافته بادی پوشیده نمی‌شود مگر اینکه بست پهلو بگذارید.'],
        ];

        return $this->finishBlock($this->noted($pieces, $notes), $g);
    }

    /**
     * بریدن خط پا از لبهٔ پایین قطعه.
     *
     * خط پا از درز پهلو بالا می‌رود و به مرکز می‌رسد؛ پس تکهٔ بالا نگه داشته
     * می‌شود، نه پایین.
     */
    protected function carveLeg(array $piece, float $sideY): array
    {
        [, , , $maxY] = Geometry::bounds($piece['outline']);

        return $this->cutTop($piece, [
            'keep' => 'upper',
            'center' => $maxY - 0.4,
            'side' => $sideY,
            'shape' => 'scoop',
        ]);
    }
}
