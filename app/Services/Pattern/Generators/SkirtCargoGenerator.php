<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن کارگو.
 *
 * دامن راسته یا کمی گشاد با جیب‌های بیرونی بزرگ و درپوش‌دار روی پهلو.
 *
 * جیب کارگو روی دامن یک مشکل دارد که روی شلوار ندارد: جیب روی پهلو می‌افتد و
 * پهلوی دامن جایی است که دامن دور بدن می‌چرخد. جیبِ پُر، دامن را می‌کشد و
 * می‌چرخاند. دو کار جلویش را می‌گیرد و هر دو این‌جا هست:
 *
 *   - جیب کمی جلوتر از درز پهلو می‌نشیند، نه دقیقاً رویش.
 *   - پشت جیب لایی می‌خورد تا وزنش روی سطح بزرگ‌تری پخش شود.
 *
 * چین یا پیلی روی خودِ جیب هم اضافه شده؛ همان چیزی است که به جیب کارگو حجم
 * می‌دهد و بدون آن جیب فقط یک تکه پارچهٔ دوخته‌شده است.
 */
class SkirtCargoGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_cargo';
    }

    public function label(): string
    {
        return 'دامن کارگو';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(52, 30, 110),
            [
                'hem_flare' => [
                    'label' => 'گشادی دم', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'pocket_width' => [
                    'label' => 'پهنای جیب', 'min' => 10, 'max' => 24, 'step' => 0.5,
                    'default' => 15, 'unit' => 'سانتی‌متر',
                ],
                'pocket_height' => [
                    'label' => 'بلندی جیب', 'min' => 10, 'max' => 26, 'step' => 0.5,
                    'default' => 17, 'unit' => 'سانتی‌متر',
                ],
                'pocket_gusset' => [
                    'label' => 'حجم جیب (پیلی کناری)', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'flap' => [
                    'label' => 'درپوش جیب', 'type' => 'toggle', 'default' => true,
                ],
                'belt_loops' => [
                    'label' => 'جادکمه‌ای کمر', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 52);
        $flare = (float) $this->param($params, 'hem_flare', 6);

        $width = (float) $this->param($params, 'pocket_width', 15);
        $height = (float) $this->param($params, 'pocket_height', 17);
        $gusset = (float) $this->param($params, 'pocket_gusset', 2.5);

        $pieces = [];

        foreach ([['front', 'دامن جلو', 'skirt_front'], ['back', 'دامن پشت', 'skirt_back']] as [$side, $name, $part]) {
            $pieces[] = $this->blockPanel($mx, [
                'side' => $side,
                'length' => $length,
                'hem_delta' => $flare,
                'code' => 'cargo-'.$side,
                'name' => $name,
                'part' => $part,
            ]);
        }

        // جیب با پیلی کناری: پارچه از اندازهٔ تمام‌شده پهن‌تر بریده می‌شود
        $pieces[] = $this->rectPanel([
            'side' => 'front',
            'part' => 'pocket',
            'code' => 'cargo-pocket',
            'name' => 'جیب کارگو',
            'width' => ($width + ($gusset * 2)) / 2,
            'length' => $height + 3.5,
            'cut_quantity' => 2,
            'on_fold' => true,
            'top_edge' => 'hem',
            'fullness' => $gusset > 0.1 ? [
                $this->fullness('pleat', 0, ($width + ($gusset * 2)) / 2, $width / 2, [
                    'label' => 'پیلی کناری جیب',
                ]),
            ] : [],
            'notes' => [
                'لبهٔ بالای جیب '.$this->fa(3.5).' سانتی‌متر برگردان دارد.',
                $gusset > 0.1
                    ? 'دو پیلی '.$this->fa($gusset).' سانتی‌متری در دو کنارهٔ جیب، حجم جیب را می‌سازد.'
                    : 'جیب بی‌حجم است؛ برای جیب کارگوی واقعی پیلی کناری بگذارید.',
                'جیب کمی جلوتر از درز پهلو دوخته می‌شود، نه رویش؛ وگرنه وزنش دامن را می‌چرخاند.',
            ],
        ]);

        if ($this->flag($params, 'flap', true)) {
            $pieces[] = $this->rectPanel([
                'side' => 'front',
                'part' => 'pocket',
                'code' => 'cargo-flap',
                'name' => 'درپوش جیب',
                'width' => ($width + 1.5) / 2,
                'length' => 6,
                'cut_quantity' => 4,
                'on_fold' => true,
                'top_edge' => 'default',
                'notes' => ['دو لایه برای هر درپوش؛ لایی می‌خورد.'],
            ]);

            $pieces[count($pieces) - 1]['meta']['interfacing'] = true;
            $pieces[count($pieces) - 1]['meta']['notions'] = [[
                'type' => 'snap', 'label' => 'دکمه فشاری درپوش جیب', 'count' => 2,
            ]];
        }

        if ($this->flag($params, 'belt_loops', true)) {
            $pieces[] = $this->bandPiece(30, [
                'code' => 'cargo-loops',
                'name' => 'جادکمه‌ای (پنج عدد)',
                'height' => 1.5,
                'overlap' => 0,
                'cut_quantity' => 1,
                'interfacing' => false,
                'notes' => ['یک نوار بلند بریده و به پنج تکه تقسیم می‌شود.'],
            ]);
        }

        $pieces = array_merge($pieces, $this->bandPieces($mx, $params));

        return $this->finishSkirt($pieces, $params);
    }
}
