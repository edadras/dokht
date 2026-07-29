<?php

namespace App\Services\Pattern\Generators;

/**
 * شورت مایو (زنانه و مردانه).
 *
 * تنها قطعهٔ مشترک همهٔ مایوهاست: زیر بیکینی، زیر تانکینی، و به‌تنهایی مایو
 * اسلیپ مردانه.
 *
 * سه عدد شکلش را تعیین می‌کنند و هر سه این‌جا پارامترند:
 *
 *   جای کمر    روی خط کمر (کمربلند) یا پایین‌تر (کمرکوتاه). پایین‌تر که برود،
 *              دور همان‌جا بزرگ‌تر است و الگو خودش این را حساب می‌کند.
 *   خط پا      افقی، یا بالا رفته (فرم فرانسوی) که پا را بلندتر نشان می‌دهد.
 *   پوشش پشت   کامل، معمولی، یا کم.
 *
 * نوار فاق در همهٔ حالت‌ها هست و اختیاری نیست.
 */
class SwimBriefsGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_briefs';
    }

    public function label(): string
    {
        return 'شورت مایو';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'rise_drop' => [
                'label' => 'پایین‌تر نشستن کمر', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 7, 'unit' => 'سانتی‌متر',
                'hint' => 'صفر یعنی کمربلند (فرم رترو).',
            ],
            'leg_rise' => [
                'label' => 'بالا آمدن خط پا', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
                'hint' => 'هرچه بیشتر، فرم فرانسوی‌تر و پا بلندتر دیده می‌شود.',
            ],
            'coverage' => [
                'label' => 'پوشش پشت', 'type' => 'select', 'default' => 'medium',
                'options' => ['full' => 'کامل', 'medium' => 'معمولی', 'cheeky' => 'کم'],
            ],
            'gusset' => [
                'label' => 'پهنای نوار فاق', 'min' => 6, 'max' => 16, 'step' => 0.5,
                'default' => 9, 'unit' => 'سانتی‌متر',
            ],
            'side_tie' => [
                'label' => 'بند پهلو (به‌جای درز)', 'type' => 'toggle', 'default' => false,
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->swimBottom($measurements, $params, [
            'rise_drop' => (float) $this->param($params, 'rise_drop', 7),
            'leg_rise' => (float) $this->param($params, 'leg_rise', 6),
            'coverage' => (string) $this->param($params, 'coverage', 'medium'),
            'gusset' => (float) $this->param($params, 'gusset', 9),
            'prefix' => 'briefs',
        ]);

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') === 'gusset') {
                continue;
            }

            $pieces[$index] = $this->elastic($piece, 'waist', 'کش کمر', $params);
            $pieces[$index] = $this->elastic($pieces[$index], 'hem', 'کش خط پا', $params);
        }

        if ($this->flag($params, 'side_tie', false)) {
            $pieces[] = $this->tie(46, 1.5, 'briefs-side-tie', 'بند پهلو', 4, [
                'دو بند برای هر پهلو؛ به‌جای درز پهلو گره می‌خورند و اندازه را قابل تنظیم می‌کنند.',
            ]);
        }

        $pieces = $this->withLining($pieces, $params);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $text) => ['type' => 'info', 'text' => $text],
            $this->swimNotes($params),
        )));
    }
}
