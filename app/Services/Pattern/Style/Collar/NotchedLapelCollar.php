<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;

/**
 * یقه انگلیسی (برگردان با خرک).
 *
 * یقه کت کلاسیک: برگردانِ تنه و سر یقه به هم نمی‌رسند و میانشان یک بریدگی هفتی
 * می‌ماند — همان «خرک» یا notch. روی الگو خرک از دو تکه ساخته می‌شود که هرکدام
 * روی یک قطعه‌اند: لبه بالای برگردان روی تنه جلو، و گوشه سر یقه روی خود یقه.
 * این دو در «خط گلو» به هم دوخته می‌شوند و بریدگی میانشان باز می‌ماند.
 *
 * نوک برگردان از خط خواب اندازه گرفته می‌شود نه از خط مرکز جلو؛ به همین دلیل
 * است که برگردان دو کت با پهنای برابر ولی نقطه شکست متفاوت، یک شکل درنمی‌آید.
 */
class NotchedLapelCollar extends BaseLapel
{
    public static function key(): string
    {
        return 'collar_notched';
    }

    public function label(): string
    {
        return 'یقه انگلیسی';
    }

    public function description(): string
    {
        return 'برگردان کت با خرک هفتی میان برگردان و سر یقه؛ یقه کت و بلیزر کلاسیک.';
    }

    public function paramsSchema(): array
    {
        return array_merge($this->lapelFields(8.0, 20.0), [
            'notch_drop' => [
                'label' => 'فاصله نوک برگردان از بالای خط خواب', 'min' => 2, 'max' => 16, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر', 'hint' => 'هرچه بیشتر، نوک برگردان پایین‌تر و خرک بازتر.',
            ],
            'notch_depth' => [
                'label' => 'گودی خرک', 'min' => 0.5, 'max' => 5, 'step' => 0.25, 'default' => 1.75,
                'unit' => 'سانتی‌متر', 'hint' => 'دهانه هفتی میان برگردان و یقه.',
            ],
        ]);
    }

    protected function lapelChain(array &$frame, array $p): array
    {
        $break = $frame['break'];
        $gorge = $frame['gorge'];
        $up = $frame['up'];
        $out = $frame['out'];
        $rollLength = (float) $frame['roll_length'];

        $along = max($rollLength * 0.3, min($rollLength * 0.92, $rollLength - (float) $p['notch_drop']));
        $point = $this->add($this->add($break, $up, $along), $out, (float) $p['lapel_width']);

        // گوشه خرک: میان نوک برگردان و گلوگاه، عمود بر خط این دو، کشیده به داخل لباس
        $chord = $this->unit($this->vec($point, $gorge));
        $inward = ['x' => -$chord['y'], 'y' => $chord['x']];

        if ($this->dot($inward, $out) > 0) {
            $inward = ['x' => -$inward['x'], 'y' => -$inward['y']];
        }

        $notch = $this->add(Geometry::lerp($point, $gorge, 0.45), $inward, (float) $p['notch_depth']);

        $frame['gorge_length'] = round($this->length($this->vec($notch, $gorge)), 3);

        return [
            'gorge' => Geometry::point($gorge['x'], $gorge['y']),
            'middle' => [
                Geometry::point($point['x'], $point['y']),
                Geometry::point($notch['x'], $notch['y']),
            ],
            'tags' => ['default', 'default', 'default', 'default'],
        ];
    }
}
