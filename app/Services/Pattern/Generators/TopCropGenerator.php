<?php

namespace App\Services\Pattern\Generators;

/**
 * کراپ‌تاپ.
 *
 * تنها تاپی از این گروه که تفاوتش در خط بالا نیست، در دم لباس است: بالاتنه‌ای
 * که بالاتر از خط کمر تمام می‌شود.
 *
 * یک نکتهٔ فنی که در کراپ همیشه فراموش می‌شود و این‌جا خودکار حساب شده: وقتی دم
 * لباس بالای خط کمر می‌ایستد، جای باریک‌ترین قسمت بدن دیگر روی لباس نیست. پس
 * ساسون کمر بی‌معنا می‌شود و اگر بماند، دم لباس را جمع می‌کند و بالا می‌زند.
 * برای همین هرجا کراپ از یک اندازه کوتاه‌تر باشد ساسون کمر خودش برداشته می‌شود
 * و کاهش لازم به درز پهلو می‌رود.
 */
class TopCropGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_crop';
    }

    public function label(): string
    {
        return 'کراپ‌تاپ';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema([
            'neck_drop' => [
                'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 22, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'shoulder_width' => [
                'label' => 'پهنای سرشانه', 'type' => 'select', 'default' => 'strap',
                'options' => [
                    'full' => 'سرشانهٔ کامل (آستین‌دار)',
                    'strap' => 'بند پهن',
                    'thin' => 'بند باریک',
                ],
                'hint' => 'با «سرشانهٔ کامل» می‌شود روی این کراپ آستین گذاشت.',
            ],
            'knit' => [
                'label' => 'پارچه کشی است', 'type' => 'toggle', 'default' => true,
            ],
        ], length: -14);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $knit = $this->flag($params, 'knit', true);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 4.0]);
        $ease = $knit ? $this->knitEase($ease, 2.0) : $ease;

        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = $this->bodyLength($params, $g, -14);

        $width = (string) $this->param($params, 'shoulder_width', 'strap');
        $strap = match ($width) {
            'thin' => 2.0,
            'strap' => 4.5,
            default => null,
        };

        // ساسون کمر روی لباسی که به کمر نمی‌رسد بی‌معناست
        $keepsWaistDart = $length > -6;

        $shared = [
            'shape' => $knit || ! $keepsWaistDart ? 'straight' : $this->fitShape($params),
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => $keepsWaistDart,
        ];

        if ($strap !== null) {
            $shared['shoulder_extra'] = ($g['neck_width'] + $strap) - $g['shoulder_half'];
            $shared['across_extra'] = -min(4.0, $strap * 0.5);
            $shared['armhole_drop'] = 2.0;
        }

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'crop-front',
            'name' => 'کراپ جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 4),
            'bust_dart' => ! $knit,
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'crop-back',
            'name' => 'کراپ پشت',
        ]));

        $notes = [$this->finishNote($params, ['یقه', 'حلقه', 'دم لباس'])];

        $notes[] = $keepsWaistDart
            ? ['type' => 'info', 'text' => 'دم لباس نزدیک خط کمر است، پس ساسون کمر سر جایش مانده.']
            : ['type' => 'info', 'text' => 'دم لباس بالاتر از خط کمر می‌ایستد؛ ساسون کمر برداشته شد چون باریک‌ترین جای بدن روی لباس نیست و ساسون فقط دم را بالا می‌زد.'];

        if ($width === 'full') {
            $notes[] = ['type' => 'info', 'text' => 'سرشانه کامل است، پس می‌توانید روی این کراپ هر آستینی از کاتالوگ بگذارید.'];
        }

        return $this->finishBlock($this->noted([$front, $back], $notes), $g, $grow);
    }
}
