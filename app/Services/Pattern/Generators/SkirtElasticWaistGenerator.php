<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن کمر کشی.
 *
 * ساده‌ترین دامن دنیا و همان که هیچ‌جای این کاتالوگ نبود: دو مستطیل که با کش
 * دور کمر جمع می‌شوند. نه زیپ دارد، نه ساسون، نه کمربند دوخته‌شده.
 *
 * دو عدد کل کار را تعیین می‌کنند:
 *
 *   پهنای پارچه   باید از دور باسن بیشتر باشد، وگرنه دامن از باسن رد نمی‌شود؛
 *                 هرچقدر هم کش داشته باشد فرقی نمی‌کند.
 *   بلندی کش      کش همیشه کوتاه‌تر از دور کمر بریده می‌شود؛ همان کوتاهی است که
 *                 دامن را نگه می‌دارد. اگر برابر دور کمر باشد، دامن می‌افتد.
 *
 * جای کش یا از خودِ پارچه (لوله‌ای که برمی‌گردد) ساخته می‌شود یا با نوار جدا؛
 * دومی روی پارچهٔ ضخیم بهتر است چون کمر را کلفت نمی‌کند.
 */
class SkirtElasticWaistGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_elastic_waist';
    }

    public function label(): string
    {
        return 'دامن کمر کشی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(60, 25, 120),
            [
                'fullness' => [
                    'label' => 'نسبت پُری', 'min' => 1.15, 'max' => 3, 'step' => 0.05, 'default' => 1.6,
                    'hint' => 'یک‌ونیم برابر یعنی چین ملایم؛ دو برابر یعنی دامن پُرچین.',
                ],
                'elastic_width' => [
                    'label' => 'پهنای کش', 'min' => 1, 'max' => 8, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'elastic_ratio' => [
                    'label' => 'کوتاهی کش نسبت به دور کمر', 'min' => 0.75, 'max' => 1, 'step' => 0.01,
                    'default' => 0.92,
                    'hint' => 'کمتر از ۰٫۸۵ روی کمر رد می‌اندازد.',
                ],
                'casing' => [
                    'label' => 'جای کش', 'type' => 'select', 'default' => 'self',
                    'options' => ['self' => 'از خود پارچه (برگردان)', 'band' => 'نوار جدا'],
                ],
                'hem_flare' => [
                    'label' => 'گشادتر شدن دم', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی دامن راستهٔ چین‌دار.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params, ['dart_share' => 0]);
        $length = (float) $this->param($params, 'length', 60);
        $fullness = max(1.1, (float) $this->param($params, 'fullness', 1.6));
        $elastic = (float) $this->param($params, 'elastic_width', 3);
        $ratio = min(1.0, max(0.75, (float) $this->param($params, 'elastic_ratio', 0.92)));
        $selfCasing = $this->param($params, 'casing', 'self') === 'self';
        $flare = (float) $this->param($params, 'hem_flare', 0);

        // پارچه باید هم چین بخورد و هم از باسن رد شود؛ هرکدام بزرگ‌تر بود برنده است
        $width = max($mx['waist_target'] * $fullness, $mx['hip_target'] + 8);

        // جای کش از خود پارچه یعنی این‌قدر بالای خط کمر اضافه می‌ماند
        $casingExtra = $selfCasing ? ($elastic * 2) + 1.5 : 0.0;

        $pieces = [];

        foreach ([['front', 'دامن جلو'], ['back', 'دامن پشت']] as [$side, $name]) {
            $pieces[] = $this->rectPanel([
                'side' => $side,
                'part' => $side === 'front' ? 'skirt_front' : 'skirt_back',
                'code' => 'elastic-'.$side,
                'name' => $name,
                'width' => ($width / 2) + ($flare / 2),
                'length' => $length + $casingExtra,
                'cut_quantity' => 1,
                'on_fold' => true,
                'top_edge' => 'waist',
                'hip_y' => round($mx['hip_y'] + $casingExtra, 2),
                'waist_target' => $mx['waist_target'],
                'waist_finished' => round($mx['waist_target'] / 2, 2),
                'fullness' => [
                    $this->fullness('gather', 0, ($width / 2) + ($flare / 2), $mx['waist_target'] / 2, [
                        'label' => 'جمع شدن با کش',
                    ]),
                ],
                'notes' => [
                    $selfCasing
                        ? 'بالای پنل '.$this->fa(round($casingExtra, 1)).' سانتی‌متر اضافه دارد؛ همان برمی‌گردد و جای کش می‌شود.'
                        : 'جای کش با نوار جدا دوخته می‌شود؛ پنل خودش اضافهٔ بالا ندارد.',
                ],
            ]);
        }

        if (! $selfCasing) {
            $pieces[] = $this->bandPiece($mx['waist_target'], [
                'code' => 'elastic-casing',
                'name' => 'نوار جای کش',
                'height' => $elastic + 1.5,
                'overlap' => 1.5,
                'interfacing' => false,
                'notes' => ['نوار جای کش؛ کش از داخلش رد می‌شود.'],
            ]);
        }

        $elasticLength = round($mx['waist_target'] * $ratio, 1);

        $pieces[0]['meta']['notions'] = [[
            'type' => 'elastic',
            'label' => 'کش کمر '.$this->fa($elastic).' سانتی‌متری',
            'length' => $elasticLength,
        ]];

        $pieces[0]['meta']['notes'][] = 'کش '.$this->fa($elasticLength).' سانتی‌متر بریده می‌شود؛ '
            .$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از دور کمر.';

        return $this->finishSkirt($pieces, ['zip' => 'none']);
    }
}
