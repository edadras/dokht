<?php

namespace App\Services\Pattern\Generators;

/**
 * بیکینی مثلثی.
 *
 * دو فنجان مثلثی که فقط با بند نگه داشته می‌شوند، به‌علاوهٔ شورت.
 *
 * چیزی که این مدل را از بقیه جدا می‌کند این است که هیچ درزی وزن را نمی‌گیرد؛
 * همهٔ کار روی بندهاست. سه پیامد دارد و هر سه در الگو هست:
 *
 *   - مثلث روی اریب بریده می‌شود، چون فقط اریب روی سینه شکل می‌گیرد.
 *   - بند گردن و بند زیرسینه هر دو باید قابل تنظیم باشند؛ اندازهٔ درست را
 *     فقط روی تن می‌شود پیدا کرد، پس بندها بلندتر بریده می‌شوند.
 *   - بند زیرسینه از داخل لولهٔ لبهٔ فنجان رد می‌شود، پس لبهٔ پایین فنجان
 *     باید به‌شکل لوله دوخته شود، نه ساده.
 */
class SwimBikiniTriangleGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_bikini_triangle';
    }

    public function label(): string
    {
        return 'بیکینی مثلثی';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'cup_width' => [
                'label' => 'پهنای فنجان', 'min' => 12, 'max' => 30, 'step' => 0.5,
                'default' => 18, 'unit' => 'سانتی‌متر',
            ],
            'cup_height' => [
                'label' => 'بلندی فنجان', 'min' => 10, 'max' => 26, 'step' => 0.5,
                'default' => 16, 'unit' => 'سانتی‌متر',
            ],
            'sliding' => [
                'label' => 'فنجان روی بند بلغزد (تنظیم‌شونده)', 'type' => 'toggle', 'default' => true,
            ],
            'rise_drop' => [
                'label' => 'پایین‌تر نشستن کمر شورت', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'leg_rise' => [
                'label' => 'بالا آمدن خط پا', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 7, 'unit' => 'سانتی‌متر',
            ],
            'coverage' => [
                'label' => 'پوشش پشت', 'type' => 'select', 'default' => 'medium',
                'options' => ['full' => 'کامل', 'medium' => 'معمولی', 'cheeky' => 'کم'],
            ],
            'side_tie' => [
                'label' => 'بند پهلوی شورت', 'type' => 'toggle', 'default' => true,
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretch($params);
        $under = ((float) ($measurements['underbust'] ?? ($measurements['bust'] ?? 92) * 0.86)) * $stretch;

        $pieces = [
            $this->trianglePiece(
                (float) $this->param($params, 'cup_width', 18),
                (float) $this->param($params, 'cup_height', 16),
            ),
            $this->tie($under + 60, 1.2, 'bikini-band-tie', 'بند زیرسینه', 1, [
                'از لولهٔ لبهٔ پایین فنجان‌ها رد می‌شود و پشت گره می‌خورد؛ بلندتر بریده شده تا تنظیم شود.',
            ]),
            $this->tie(52, 1.2, 'bikini-neck-tie', 'بند گردن', 2, [
                'دو بند که پشت گردن گره می‌خورند.',
            ]),
        ];

        foreach ($this->swimBottom($measurements, $params, [
            'rise_drop' => (float) $this->param($params, 'rise_drop', 8),
            'leg_rise' => (float) $this->param($params, 'leg_rise', 7),
            'coverage' => (string) $this->param($params, 'coverage', 'medium'),
            'prefix' => 'bikini',
        ]) as $piece) {
            if (($piece['meta']['part'] ?? '') !== 'gusset') {
                $piece = $this->elastic($piece, 'waist', 'کش کمر شورت', $params);
                $piece = $this->elastic($piece, 'hem', 'کش خط پای شورت', $params);
            }

            $pieces[] = $piece;
        }

        if ($this->flag($params, 'side_tie', true)) {
            $pieces[] = $this->tie(46, 1.2, 'bikini-side-tie', 'بند پهلوی شورت', 4);
        }

        $notes = array_merge($this->swimNotes($params), [
            'هیچ درزی وزن را نمی‌گیرد؛ همهٔ کار روی بندهاست، پس همه‌شان بلندتر از اندازه بریده شده‌اند تا در پرو کوتاه شوند.',
            'لبهٔ پایین فنجان به‌شکل لوله دوخته می‌شود تا بند زیرسینه از داخلش رد شود.',
        ]);

        if ($this->flag($params, 'sliding', true)) {
            $notes[] = 'فنجان روی بند می‌لغزد، پس فاصلهٔ دو فنجان قابل تنظیم است؛ برای همین جای فنجان روی الگو ثابت نشده.';
        }

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $notes),
        ));
    }
}
