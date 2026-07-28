<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;

/**
 * یقه نوک‌تیز (پیک).
 *
 * همان یقه انگلیسی است با یک تفاوت: نوک برگردان به جای اینکه پایین‌تر از خط گلو
 * بماند، از آن بالاتر می‌رود و رو به سرشانه تیز می‌شود. پس خرک به جای هفتِ باز،
 * یک شکاف باریک زیر نوک برگردان می‌شود و یقه رسمی‌تر و بلندتر به نظر می‌رسد؛
 * یقه کت دامادی و کت دوردیف.
 *
 * روی الگو تنها فرقش این است که نوک برگردان از انتهای خط خواب هم بالاتر گرفته
 * می‌شود و گوشه خرک به جای اینکه میان نوک و گلوگاه بیفتد، پایین‌تر از گلوگاه
 * روی امتداد خط گلو می‌نشیند.
 */
class PeakLapelCollar extends BaseLapel
{
    public static function key(): string
    {
        return 'collar_peak';
    }

    public function label(): string
    {
        return 'یقه نوک‌تیز (پیک)';
    }

    public function description(): string
    {
        return 'برگردان کت با نوک تیزِ رو به سرشانه؛ یقه کت رسمی و دوردیف.';
    }

    public function paramsSchema(): array
    {
        return array_merge($this->lapelFields(9.0, 22.0), [
            'peak_drop' => [
                'label' => 'فاصله نوک برگردان از بالای خط خواب', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => 2.5,
                'unit' => 'سانتی‌متر', 'hint' => 'کم بگیرید تا نوک بالا و تیز بایستد؛ زیاد یعنی نوک پایین‌تر و آرام‌تر.',
            ],
            'notch_gap' => [
                'label' => 'بازی شکاف خرک', 'min' => 1, 'max' => 7, 'step' => 0.25, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'فاصله گوشه خرک از نقطه گلوگاه روی خط گلو.',
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

        // نوک برگردان: نزدیک بالای خط خواب و بیرون‌تر به اندازه پهنای برگردان
        $along = max($rollLength * 0.45, $rollLength - (float) $p['peak_drop']);
        $point = $this->add($this->add($break, $up, $along), $out, (float) $p['lapel_width']);

        // گوشه خرک پایین‌تر و بیرون‌تر از گلوگاه؛ شکاف باریک زیر نوک باز می‌ماند
        $direction = $this->unit([
            'x' => (-$up['x']) + ($out['x'] * 0.9),
            'y' => (-$up['y']) + ($out['y'] * 0.9),
        ]);
        $notch = $this->add($gorge, $direction, (float) $p['notch_gap']);

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
