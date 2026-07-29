<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * تایت ورزشی.
 *
 * لایهٔ چسبان پایین‌تنه: پارچهٔ کشیِ پرکشش و آزادی *منفی*. الگو با ضریب کشسانی
 * کوچک‌تر از بدن بریده می‌شود و همان تنگی است که تایت را هنگام دویدن سر جایش
 * نگه می‌دارد؛ تایتِ اندازهٔ بدن پایین می‌رود.
 *
 * چهار چیز که تایت را از یک لگینگ ساده جدا می‌کند و همه در الگو دیده می‌شوند:
 *
 *   ۱. نوار کمر پهن و دولا، با کش داخلش. کمرِ باریک روی تایت ورزشی می‌پیچد.
 *   ۲. لُنگهٔ فاق (گاست): یک لوزی که فاق را از نقطهٔ چهارراهِ درز خلاص می‌کند. بدون
 *      آن، چهار درز روی یک نقطه جمع می‌شوند و همان‌جا اول از همه پاره می‌شود.
 *   ۳. زانو با آزادی کمتری از ران بسته می‌شود تا پارچه روی زانو چروک نکند.
 *   ۴. جیب زیپ‌دار کمر، چون تایت جیب پهلو ندارد که چیزی در آن جا شود.
 */
class ActiveTightsGenerator extends PantsBaseGenerator
{
    use ActiveFabric, PieceRoles;

    /** این مدل در فهرست، زیر «لباس ورزشی» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'active';
    }

    public static function key(): string
    {
        return 'active_tights';
    }

    public function label(): string
    {
        return 'تایت ورزشی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(-2, -1),
            $this->stretchParam(0.85, 'تایت باید محسوس کوچک‌تر از بدن بریده شود؛ ۰٫۸۵ یعنی پانزده درصد کوچک‌تر.'),
            [
                'waistband_height' => [
                    'label' => 'بلندی نوار کمر', 'min' => 4, 'max' => 16, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                    'hint' => 'نوار پهن روی تایت ورزشی نمی‌پیچد؛ نوار باریک می‌پیچد.',
                ],
                'band_stretch' => [
                    'label' => 'کشش نوار کمر', 'min' => 0.6, 'max' => 0.95, 'step' => 0.01, 'default' => 0.9,
                ],
                'gusset' => [
                    'label' => 'لنگه فاق (گاست)', 'type' => 'toggle', 'default' => true,
                ],
                'waist_pocket' => [
                    'label' => 'جیب زیپ‌دار کمر', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'stretch' => $this->activeStretch($params, 0.85),
            // ران و زانو هر دو چسبان؛ دم پا از زانو هم کمی جمع‌تر
            'hem_vs_knee' => -2.0,
            'thigh_ease' => 0.0,
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'waist_clearance' => 0.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.9),
            'waist_ease' => 0.0,
            'hip_ease' => 0.0,
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->activeStretch($params, 0.85);
        $pieces = parent::generate($measurements, $ease, $params);

        foreach ($pieces as $index => $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (! in_array($part, ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $pieces[$index]['meta']['girth_role'] = 'shell';
            $pieces[$index]['meta']['notes'] = array_merge(
                $piece['meta']['notes'] ?? [],
                $this->compressionNotes($stretch),
            );
        }

        $pieces = $this->stampStretch($pieces, $stretch, ['front_leg', 'back_leg']);

        if ($this->flag($params, 'gusset', true)) {
            $pieces[] = $this->gussetPiece($measurements, $stretch);
        }

        if ($this->flag($params, 'waist_pocket', true)) {
            $pieces[] = $this->waistPocket($measurements, $stretch);
        }

        return $this->finish($this->withGirthRoles($pieces));
    }

    /**
     * لنگهٔ فاق: لوزی کوچکی که میان چهار درزِ فاق می‌نشیند.
     *
     * پهنایش از دور ران می‌آید نه از عددی ثابت؛ روی بدن درشت‌تر، فاصلهٔ دو ران
     * بیشتر است و لنگه هم باید پهن‌تر باشد.
     *
     * @return array<string, mixed>
     */
    protected function gussetPiece(array $m, float $stretch): array
    {
        $width = max(7.0, min(14.0, $this->m($m, 'thigh', 57) * 0.16 * $stretch));
        $length = $width * 1.9;

        return $this->piece([
            'code' => 'tights-gusset',
            'name' => 'لنگه فاق',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point($width / 2, 0),
                Geometry::curve($width, $length * 0.42, $width * 0.95, $length * 0.16),
                Geometry::curve($width / 2, $length, $width * 0.95, $length * 0.78),
                Geometry::curve(0, $length * 0.42, $width * 0.05, $length * 0.78),
            ],
            'grainline' => $this->grainline($width * 0.5, $length * 0.12, $length * 0.88),
            'meta' => [
                'part' => 'gusset',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'نوکِ بالا به نقطهٔ فاق جلو و نوکِ پایین به نقطهٔ فاق پشت دوخته می‌شود؛'
                        .' دو پهلویش روی درز داخل پا می‌نشیند.',
                    'بدون این لنگه، چهار درز روی یک نقطه جمع می‌شوند و همان‌جا اول از همه پاره می‌شود.',
                ],
            ],
        ]);
    }

    /** جیب زیپ‌دار داخل نوار کمر. */
    protected function waistPocket(array $m, float $stretch): array
    {
        $width = max(12.0, min(20.0, $this->m($m, 'waist', 74) * 0.22 * $stretch));

        return $this->piece([
            'code' => 'tights-waist-pocket',
            'name' => 'جیب کمر',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, 14),
                Geometry::point(0, 14),
            ],
            'grainline' => $this->grainline($width * 0.5, 1, 13),
            'meta' => [
                'part' => 'pocket',
                'edges' => ['waist', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notions' => [[
                    'type' => 'zip',
                    'label' => 'زیپ جیب کمر',
                    'count' => 1,
                    'length' => round($width - 2, 1),
                ]],
                'notes' => ['داخل نوار کمرِ پشت دوخته می‌شود؛ دهانه‌اش زیر لبهٔ بالای نوار پنهان است.'],
            ],
        ]);
    }
}
