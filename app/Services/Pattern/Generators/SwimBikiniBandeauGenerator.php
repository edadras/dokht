<?php

namespace App\Services\Pattern\Generators;

/**
 * بیکینی بندو (بی‌بند).
 *
 * تاپ بالا بی‌بند است، پس چیزی جز چسبیدن نگهش نمی‌دارد. همین یک جمله همهٔ
 * تصمیم‌های الگو را می‌سازد:
 *
 *   - آزادی منفی بیشتر از بقیهٔ مایوها.
 *   - کش لبهٔ بالا و پایین هر دو، نه فقط یکی.
 *   - بند گردنِ برداشتنی به‌عنوان گزینه: در آب حرکت‌دار (شنا، موج) بندو
 *     می‌افتد و بند گردن تنها راه نگه‌داشتنش است.
 *
 * تاپ می‌تواند صاف باشد یا وسطش گره بخورد؛ گره فقط تزیین نیست، پارچهٔ وسط را
 * جمع می‌کند و روی سینه جا می‌دهد.
 */
class SwimBikiniBandeauGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_bikini_bandeau';
    }

    public function label(): string
    {
        return 'بیکینی بندو (بی‌بند)';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'top_height' => [
                'label' => 'بلندی تاپ', 'min' => 10, 'max' => 26, 'step' => 0.5,
                'default' => 15, 'unit' => 'سانتی‌متر',
            ],
            'centre_knot' => [
                'label' => 'گره وسط', 'type' => 'toggle', 'default' => true,
            ],
            'removable_strap' => [
                'label' => 'بند گردن برداشتنی', 'type' => 'toggle', 'default' => true,
            ],
            'rise_drop' => [
                'label' => 'پایین‌تر نشستن کمر شورت', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'leg_rise' => [
                'label' => 'بالا آمدن خط پا', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
            ],
            'coverage' => [
                'label' => 'پوشش پشت', 'type' => 'select', 'default' => 'medium',
                'options' => ['full' => 'کامل', 'medium' => 'معمولی', 'cheeky' => 'کم'],
            ],
        ], stretch: 0.8);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretch($params);
        $bust = ((float) ($measurements['bust'] ?? 92)) * $stretch;
        $height = (float) $this->param($params, 'top_height', 15);
        $knot = $this->flag($params, 'centre_knot', true);

        // گره، پارچه می‌خورد: پهنای بیشتری لازم است
        $width = ($bust / 2) + ($knot ? 8 : 0);

        $top = $this->bandPiece('bandeau-top', 'تاپ بندو', $width, $height, [
            'cut' => 2, 'part' => 'front_bodice',
            'meta' => [
                'girth_role' => 'shell',
                'girth' => ['bust' => round($width, 2)],
                'girth_factor' => 2,
                'edges' => ['default', 'side', 'hem', 'side'],
                'notes' => $knot
                    ? ['هشت سانتی‌متر پهنای اضافه برای گره وسط حساب شده؛ گره پارچه را جمع می‌کند و روی سینه جا می‌دهد.']
                    : [],
            ],
        ]);

        $top = $this->elastic($top, 'default', 'کش لبهٔ بالا', $params);
        $top = $this->elastic($top, 'hem', 'کش لبهٔ پایین', $params);

        $pieces = [$top];

        if ($this->flag($params, 'removable_strap', true)) {
            $pieces[] = $this->tie(54, 1.5, 'bandeau-strap', 'بند گردن برداشتنی', 1, [
                'با دو حلقه به تاپ وصل می‌شود و برداشتنی است؛ در آبِ حرکت‌دار بدون بند، بندو می‌افتد.',
            ]);
        }

        foreach ($this->swimBottom($measurements, $params, [
            'rise_drop' => (float) $this->param($params, 'rise_drop', 8),
            'leg_rise' => (float) $this->param($params, 'leg_rise', 6),
            'coverage' => (string) $this->param($params, 'coverage', 'medium'),
            'prefix' => 'bandeau',
        ]) as $piece) {
            if (($piece['meta']['part'] ?? '') !== 'gusset') {
                $piece = $this->elastic($piece, 'waist', 'کش کمر شورت', $params);
                $piece = $this->elastic($piece, 'hem', 'کش خط پای شورت', $params);
            }

            $pieces[] = $piece;
        }

        $notes = array_merge($this->swimNotes($params), [
            'تاپ بی‌بند است و فقط با چسبیدن می‌ایستد؛ برای همین آزادی منفی این مدل از بقیه بیشتر است.',
        ]);

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $notes),
        ));
    }
}
