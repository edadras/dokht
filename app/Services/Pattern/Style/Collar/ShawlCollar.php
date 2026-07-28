<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * یقه شال.
 *
 * برگردانی که خرک ندارد: از نقطه شکست تا پشت گردن یک خط پیوسته و نرم است. به
 * همین دلیل روی تنه جلو هیچ گوشه‌ای بریده نمی‌شود و لبه برگردان با یک منحنی به
 * نقطه گلوگاه می‌رسد.
 *
 * دو نکته که یقه شال را از بقیه جدا می‌کند:
 *
 *   راستای پارچه — یقه شال در راستای خط خواب بریده می‌شود، نه موازی مرکز پشت.
 *     اگر روی راستای معمول بریده شود، لبه بیرونی یقه در برگشت کش می‌آید و یقه
 *     موج می‌خورد. این تنها یقه‌ای است که راستای پارچه‌اش از خط خواب می‌آید.
 *
 *   دوخت — در کار کلاسیک، یقه شال و سجاف جلو یک تکه بریده می‌شوند و درزشان فقط
 *     در مرکز پشت است. این‌جا دو قطعه جدا داده می‌شود تا هر دو راه (یک‌تکه یا
 *     درزدار روی سرشانه) باز بماند؛ در یادداشت‌ها گفته می‌شود.
 */
class ShawlCollar extends BaseLapel
{
    public static function key(): string
    {
        return 'collar_shawl';
    }

    public function label(): string
    {
        return 'یقه شال';
    }

    public function description(): string
    {
        return 'برگردان پیوسته و بدون خرک، با خط خواب نرم؛ یقه کت مجلسی و ربدوشامبر.';
    }

    public function paramsSchema(): array
    {
        return array_merge($this->lapelFields(8.5, 22.0), [
            'curve' => [
                'label' => 'گردی لبه شال', 'min' => 0, 'max' => 6, 'step' => 0.25, 'default' => 2,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی لبه شال خط راست؛ بیشتر یعنی شکم‌دارتر.',
            ],
            'top_width' => [
                'label' => 'پهنای شال کنار گلوگاه', 'min' => 2, 'max' => 12, 'step' => 0.5, 'default' => 5,
                'unit' => 'سانتی‌متر', 'hint' => 'برگردان از نوک به سمت گردن باریک می‌شود.',
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

        // پهن‌ترین جای شال، حدود دوسوم خط خواب
        $wide = $this->add($this->add($break, $up, $rollLength * 0.62), $out, (float) $p['lapel_width']);
        $near = $this->add($this->add($break, $up, $rollLength * 0.94), $out, (float) $p['top_width']);

        // نقطه کنترل منحنی پایین: از نقطه شکست تا پهن‌ترین جا شکم می‌دهد
        $lowControl = $this->add(
            Geometry::lerp($break, $wide, 0.55),
            $out,
            (float) $p['curve'],
        );

        // منحنی بالا: از پهن‌ترین جا تا کنار گلوگاه، نرم و بدون گوشه
        $highControl = $this->add(Geometry::lerp($wide, $near, 0.5), $out, (float) $p['curve'] * 0.5);

        $frame['gorge_length'] = round($this->length($this->vec($near, $gorge)), 3);

        return [
            'gorge' => Geometry::point($gorge['x'], $gorge['y']),
            'middle' => [
                Geometry::curve($wide['x'], $wide['y'], $lowControl['x'], $lowControl['y']),
                Geometry::curve($near['x'], $near['y'], $highControl['x'], $highControl['y']),
            ],
            'tags' => ['default', 'default', 'default', 'default'],
        ];
    }

    /**
     * یقه شال: راستای پارچه در امتداد خط خواب.
     *
     * @return array<string, mixed>
     */
    protected function collarPiece(float $span, float $width, float $stand, float $gorge, array $p): array
    {
        $piece = parent::collarPiece($span, $width, $stand, $gorge, $p);
        $piece['code'] = 'collar-shawl';
        $piece['name'] = 'یقه شال';
        $piece['meta']['collar_kind'] = 'shawl';

        // خط خواب همان راستای پارچه است؛ خط نشانه خواب را برمی‌داریم و روی آن راستا می‌گذاریم
        $roll = null;

        foreach ($piece['markers'] as $marker) {
            if (($marker['key'] ?? '') === 'roll_line') {
                $roll = $marker;
            }
        }

        if ($roll !== null) {
            $piece['grainline'] = $this->grainlineBetween(
                $roll['from'],
                $roll['to'],
                'راستای پارچه در امتداد خط خواب',
            );
            $piece['meta']['grain_on_roll_line'] = true;
        }

        return $piece;
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $result = parent::draft($neck, $p, $pieces, $context);

        $result['notes'][] = 'راستای پارچه یقه شال روی خط خواب گذاشته شد، نه موازی مرکز پشت؛'
            .' با راستای معمول، لبه بیرونی در برگشت کش می‌آید و یقه موج می‌خورد.';
        $result['notes'][] = 'در کار کلاسیک، یقه شال و سجاف جلو یک تکه بریده می‌شوند و فقط در مرکز پشت درز دارند؛'
            .' این‌جا جدا داده شده تا اگر پارچه کم است، درز سرشانه بگیرید. اندازه‌ها در هر دو حالت یکی است.';

        if (isset($result['meta']['outer_edge'], $result['meta']['measured'])) {
            $result['notes'][] = 'لبه بیرونی یقه شال '.Format::cm((float) $result['meta']['outer_edge'])
                .' و لبه یقه‌اش '.Format::cm((float) $result['meta']['measured'])
                .' است؛ تا وقتی لبه بیرونی بلندتر باشد، شال روی خط خواب می‌خوابد.';
        }

        return $result;
    }
}
