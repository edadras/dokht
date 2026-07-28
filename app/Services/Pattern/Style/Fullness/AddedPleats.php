<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * پایه مشترک «پیلی زدن روی یک پنل ساده».
 *
 * حساب پیلی صریح است: پارچه = اندازه تمام‌شده + تعداد پیلی × جای هر پیلی.
 * جای هر پیلی برای پیلی تیغه‌ای و آکاردئونی ۲×ژرفای تا و برای پیلی جعبه‌ای ۴×ژرفای
 * تاست، چون جعبه‌ای از دو تای روبه‌رو ساخته می‌شود.
 *
 * پیلی تای موازی راستای پارچه است، پس جای آن باید در همه بلندی پنل یک‌اندازه
 * اضافه شود؛ برای همین نیم‌رخ درز پهلو افقی جابه‌جا می‌شود و پنل به شکل گوه باز
 * نمی‌شود. جای هر پیلی با یک خط و یک نشانه روی خط کمر علامت می‌خورد.
 */
abstract class AddedPleats extends FullnessStyle
{
    /** knife | box | inverted | accordion */
    abstract protected function pleatType(): string;

    /** چند تا فولد در هر پیلی: تیغه‌ای یکی، جعبه‌ای دوتا. */
    protected function folds(): int
    {
        return in_array($this->pleatType(), ['box', 'inverted'], true) ? 2 : 1;
    }

    protected function pleatParams(int $count, float $depth): array
    {
        return [
            'count' => [
                'label' => 'تعداد پیلی روی هر پنل', 'min' => 1, 'max' => 16, 'step' => 1, 'default' => $count,
            ],
            'depth' => [
                'label' => 'ژرفای تای هر پیلی', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => $depth,
                'unit' => 'سانتی‌متر',
                'hint' => 'جای هر پیلی روی پارچه '.($this->folds() === 2 ? 'چهار' : 'دو').' برابر این عدد است.',
            ],
            'to_hem' => [
                'label' => 'پیلی تا دم لباس اتو شود', 'type' => 'toggle', 'default' => true,
                'hint' => 'خاموش یعنی فقط تا خط باسن اتو می‌شود و پایین‌تر آزاد می‌ریزد.',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        $hosts = $this->pleatHosts($pieces);

        if ($hosts === []) {
            return $this->noPanelMessage();
        }

        foreach ($hosts as $index) {
            if ($this->sideEdges($pieces[$index]) === []) {
                return 'پنل «'.$pieces[$index]['name'].'» درز پهلو ندارد، پس جای پیلی روی آن باز نمی‌شود.';
            }
        }

        return true;
    }

    /** @return array<int, int> */
    protected function pleatHosts(array $pieces): array
    {
        return array_values(array_filter(
            $this->panelIndexes($pieces),
            fn (int $index) => $this->edgeWithTag($pieces[$index], 'waist') !== null,
        ));
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(1, (int) $this->num($context, 'count', 4));
        $depth = max(0.3, $this->num($context, 'depth', 4));
        $toHem = $this->flag($context, 'to_hem', true);
        $each = $depth * 2 * $this->folds();
        $hosts = $this->pleatHosts($pieces);

        $before = $this->girth($pieces, 'waist', $hosts);
        $added = 0.0;

        foreach ($hosts as $index) {
            $rawBefore = $this->rawLength($pieces[$index], 'waist');
            $piece = $this->widenPanel($pieces[$index], $each * $count);
            $edge = $this->edgeWithTag($piece, 'waist');

            if ($edge === null) {
                continue;
            }

            // آنچه واقعاً به طول لبه کمر اضافه شد؛ روی لبه کمرِ خوابیده (پشت شلوار)
            // کمی کمتر از جابه‌جایی افقی است و همین عدد — نه عدد اسمی — ثبت می‌شود
            // تا اندازه تمام‌شده کمر یک نخ هم نلغزد
            $grew = max(0.0, $this->rawLength($piece, 'waist') - $rawBefore);
            $height = Geometry::height($piece['outline']);
            $stop = $toHem ? $height : min($height, $this->markerY($piece, 'hip') ?? ($height * 0.4));

            for ($i = 0; $i < $count; $i++) {
                $spot = Geometry::pointOnEdge($piece['outline'], $edge, ($i + 0.5) / $count);
                $piece['pleats'][] = [
                    'label' => $this->label().' '.$this->fa($i + 1),
                    'style' => $this->pleatType(),
                    'depth' => round($grew / $count, 2),
                    'fold_depth' => round($depth, 2),
                    'intake' => round($grew / $count, 2),
                    'from' => Geometry::point($spot['x'], $spot['y']),
                    'to' => Geometry::point($spot['x'], $stop),
                ];
                $piece['notches'][] = $this->notch($spot['x'], $spot['y'], $edge, 'خط '.$this->label(), 'pleat');
            }

            $piece = $this->recordAcross($piece, 'waist', $grew, 'pleats', [
                'count' => $count,
                'type' => $this->pleatType(),
                'depth' => round($depth, 2),
                'label' => $this->label(),
            ]);

            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['pleat_depth'] = round($depth, 2);
            $piece['meta']['pleat_count'] = $count;

            $added += $this->repeats($piece) * $each * $count;
            $pieces[$index] = $piece;
        }

        $after = $this->girth($pieces, 'waist', $hosts);

        return $this->report($pieces, [
            $this->note('tip', $this->fa($count).' '.$this->label().' با ژرفای تای '.Format::cm($depth)
                .' روی هر پنل نشست؛ جای هر پیلی روی پارچه '.Format::cm($each).' است.'),
            $this->note('info', 'اندازه تمام‌شده کمر عوض نشد ('.Format::cm($after)
                .')، فقط پارچه '.Format::cm($added).' پهن‌تر شد.'),
        ], $added, [
            'waist_before' => $before,
            'waist_after' => $after,
            'pleat_count' => $count,
            'pleat_depth' => round($depth, 2),
            'pleat_allowance' => round($each, 2),
        ]);
    }
}
