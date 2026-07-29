<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن پاکتی کمربلند.
 *
 * کش کمر پایین‌تر از لبهٔ بالای پارچه دوخته می‌شود، پس بالای کش یک چین‌خوردگی
 * ایستاده می‌ماند — همان چیزی که به آن «پاکتی» می‌گویند.
 *
 * نکته‌ای که همیشه اشتباه می‌شود: بلندی دامن باید از خط کمر حساب شود، ولی
 * پارچه از بالای پاکت شروع می‌شود. اگر این دو را یکی بگیرند دامن به اندازهٔ
 * بلندی پاکت کوتاه‌تر درمی‌آید. این‌جا هر دو جدا حساب شده‌اند.
 *
 * کمر معمولاً بالاتر از خط کمر طبیعی می‌نشیند؛ همان بالا آمدن است که دامن را
 * «کمربلند» می‌کند و در الگو با دور کمر باریک‌تر حساب می‌شود.
 */
class SkirtPaperbagGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_paperbag';
    }

    public function label(): string
    {
        return 'دامن پاکتی کمربلند';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(62, 25, 120),
            [
                'frill' => [
                    'label' => 'بلندی پاکت (بالای کش)', 'min' => 2, 'max' => 12, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'rise' => [
                    'label' => 'بالا آمدن کمر از خط کمر طبیعی', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'fullness' => [
                    'label' => 'نسبت پُری', 'min' => 1.2, 'max' => 3, 'step' => 0.05, 'default' => 1.7,
                ],
                'elastic_width' => [
                    'label' => 'پهنای کش', 'min' => 1, 'max' => 6, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'belt_loops' => [
                    'label' => 'جادکمه‌ای و بند کمر', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params, ['dart_share' => 0]);
        $length = (float) $this->param($params, 'length', 62);
        $frill = (float) $this->param($params, 'frill', 5);
        $rise = (float) $this->param($params, 'rise', 3);
        $fullness = max(1.15, (float) $this->param($params, 'fullness', 1.7));
        $elastic = (float) $this->param($params, 'elastic_width', 2.5);

        // کمر بالاتر از خط کمر طبیعی می‌نشیند، پس باریک‌تر است
        $waist = max(45.0, $mx['waist_target'] - ($rise * 0.55));
        $width = max($waist * $fullness, $mx['hip_target'] + 8);

        // پارچه = پاکت + جای کش + قد دامن؛ خط کمر جایی وسط این‌هاست
        $casing = ($elastic * 2) + 1.2;
        $total = $frill + $casing + $length + $rise;

        $pieces = [];

        foreach ([['front', 'دامن پاکتی جلو', 'skirt_front'], ['back', 'دامن پاکتی پشت', 'skirt_back']] as [$side, $name, $part]) {
            $pieces[] = $this->rectPanel([
                'side' => $side,
                'part' => $part,
                'code' => 'paperbag-'.$side,
                'name' => $name,
                'width' => $width / 2,
                'length' => $total,
                'cut_quantity' => 1,
                'on_fold' => true,
                // لبهٔ بالا همان لبه‌ای است که دور کمر رویش اندازه می‌شود؛ اگر
                // برچسبش «کمر» نباشد، چینِ ثبت‌شده از اندازهٔ تمام‌شده کم نمی‌شود
                // و دامن به اندازهٔ کل پارچه گزارش می‌شود.
                'top_edge' => 'waist',
                'hip_y' => round($mx['hip_y'] + $frill + $casing + $rise, 2),
                'waist_target' => $waist,
                'waist_finished' => round($waist / 2, 2),
                'fullness' => [
                    $this->fullness('gather', 0, $width / 2, $waist / 2, ['label' => 'جمع شدن با کش']),
                ],
                // کلید این خط عمداً «waist» نیست: روی پنل چین‌دار، پهنای پارچه در
                // خط کمر با دور کمرِ تمام‌شده یکی نیست، و خط نشانه‌ای با کلید waist
                // یعنی «دور کمر این‌قدر است» — که این‌جا درست نیست.
                'markers' => [
                    $this->marker('casing_top', 'خط بالای جای کش', 0, $frill, $width / 2),
                    $this->marker('casing_bottom', 'خط کمر (پایین جای کش)', 0, $frill + $casing, $width / 2),
                ],
                'notes' => [
                    'دو رج دوخت در '.$this->fa($frill).' و '.$this->fa(round($frill + $casing, 1))
                        .' سانتی‌متری لبهٔ بالا، جای کش را می‌سازد؛ بالای آن پاکت است.',
                ],
            ]);
        }

        $elasticLength = round($waist * 0.92, 1);

        $pieces[0]['meta']['notions'] = [[
            'type' => 'elastic',
            'label' => 'کش کمر '.$this->fa($elastic).' سانتی‌متری',
            'length' => $elasticLength,
        ]];

        if ($this->flag($params, 'belt_loops', true)) {
            $pieces[] = $this->bandPiece($waist + 70, [
                'code' => 'paperbag-tie',
                'name' => 'بند کمر',
                'height' => 2,
                'overlap' => 0,
                'cut_quantity' => 1,
                'interfacing' => false,
                'notes' => ['از جادکمه‌ای‌ها رد می‌شود و جلو گره می‌خورد.'],
            ]);

            $pieces[] = $this->bandPiece(30, [
                'code' => 'paperbag-loops',
                'name' => 'جادکمه‌ای (پنج عدد)',
                'height' => 1.5,
                'overlap' => 0,
                'cut_quantity' => 1,
                'interfacing' => false,
                'notes' => ['یک نوار بلند بریده و به پنج تکه تقسیم می‌شود.'],
            ]);
        }

        return $this->finishSkirt($pieces, ['zip' => 'none']);
    }
}
