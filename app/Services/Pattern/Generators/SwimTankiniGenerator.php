<?php

namespace App\Services\Pattern\Generators;

/**
 * تانکینی.
 *
 * تاپ بلند تا نزدیک باسن به‌علاوهٔ شورت؛ پوشش یک‌تکه را می‌دهد ولی مثل بیکینی
 * دو قطعه است.
 *
 * سود واقعی‌اش همین دو قطعه بودن است و در الگو هم دیده می‌شود: چون تاپ و شورت
 * جدا هستند، «قد تنه» — سخت‌ترین اندازهٔ مایو یک‌تکه — اصلاً مسئله نیست. تاپ
 * روی شورت می‌افتد و هرکس هر قدی داشته باشد، مایو روی شانه فشار نمی‌آورد.
 *
 * تنها نکته این است که تاپ باید به‌قدر کافی بلند باشد که در آب بالا نرود؛
 * پیش‌فرضش تا زیر خط باسن است.
 */
class SwimTankiniGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_tankini';
    }

    public function label(): string
    {
        return 'تانکینی';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'top_length' => [
                'label' => 'بلندی تاپ از خط کمر', 'min' => 0, 'max' => 30, 'step' => 1,
                'default' => 14, 'unit' => 'سانتی‌متر',
                'hint' => 'تاپ کوتاه در آب بالا می‌رود؛ تا زیر خط باسن مطمئن‌تر است.',
            ],
            'strap_width' => [
                'label' => 'پهنای بند', 'min' => 1.5, 'max' => 8, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'neck_drop' => [
                'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 24, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'rise_drop' => [
                'label' => 'پایین‌تر نشستن کمر شورت', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 5, 'unit' => 'سانتی‌متر',
            ],
            'leg_rise' => [
                'label' => 'بالا آمدن خط پا', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 5, 'unit' => 'سانتی‌متر',
            ],
            'coverage' => [
                'label' => 'پوشش پشت', 'type' => 'select', 'default' => 'full',
                'options' => ['full' => 'کامل', 'medium' => 'معمولی', 'cheeky' => 'کم'],
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->swimEase($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);
        $strap = (float) $this->param($params, 'strap_width', 4);

        $shared = [
            'shape' => 'straight',
            'length' => (float) $this->param($params, 'top_length', 14),
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'across_extra' => -min(4.0, $strap * 0.5),
            'armhole_drop' => 2.5,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front', 'code' => 'tankini-front', 'name' => 'تاپ تانکینی — جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 8),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back', 'code' => 'tankini-back', 'name' => 'تاپ تانکینی — پشت',
            'neck_depth_extra' => 4,
        ]));

        $pieces = [];

        foreach ([$front, $back] as $piece) {
            $piece = $this->elastic($piece, 'neck', 'کش یقه', $params);
            $piece = $this->elastic($piece, 'armhole', 'کش حلقه', $params);
            $piece = $this->elastic($piece, 'hem', 'کش دم تاپ', $params);
            $pieces[] = $piece;
        }

        foreach ($this->swimBottom($measurements, $params, [
            'rise_drop' => (float) $this->param($params, 'rise_drop', 5),
            'leg_rise' => (float) $this->param($params, 'leg_rise', 5),
            'coverage' => (string) $this->param($params, 'coverage', 'full'),
            'prefix' => 'tankini',
        ]) as $piece) {
            if (($piece['meta']['part'] ?? '') !== 'gusset') {
                $piece = $this->elastic($piece, 'waist', 'کش کمر شورت', $params);
                $piece = $this->elastic($piece, 'hem', 'کش خط پای شورت', $params);
            }

            $pieces[] = $piece;
        }

        $notes = array_merge($this->swimNotes($params), [
            'چون تاپ و شورت جدا هستند، قد تنه — سخت‌ترین اندازهٔ مایو یک‌تکه — اصلاً مسئله نیست.',
        ]);

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $notes),
        ));
    }
}
