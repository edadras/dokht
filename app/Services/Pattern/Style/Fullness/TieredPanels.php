<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;
use App\Services\Pattern\Transform\StyleLineCutter;
use App\Support\Format;

/**
 * طبقه‌ای کردن.
 *
 * پنل از یک تراز به پایین بریده می‌شود و به‌جای تکه پایینی، طبقه‌های مستطیلی
 * می‌نشینند که هرکدام به طبقه بالای خودش چین داده می‌شوند:
 *
 *   پهنای طبقه n = پهنای طبقه n−۱ × نسبت طبقه
 *
 * چون پهنای هر طبقه از دورِ دوخته‌شده طبقه بالایی حساب می‌شود، چینِ ثبت‌شده دقیقاً
 * همان چیزی است که سر چرخ جمع می‌شود و اندازه تمام‌شده هیچ‌جا نمی‌لغزد.
 */
class TieredPanels extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_tiers';
    }

    public function label(): string
    {
        return 'طبقه‌ای کردن';
    }

    public function description(): string
    {
        return 'پایین لباس بریده می‌شود و به‌جایش طبقه‌های چین‌دار می‌نشیند.';
    }

    public function paramsSchema(): array
    {
        return [
            'tiers' => [
                'label' => 'تعداد طبقه', 'min' => 1, 'max' => 4, 'step' => 1, 'default' => 2,
                'hint' => 'طبقه‌هایی که زیر تکه بالایی اضافه می‌شوند.',
            ],
            'start' => [
                'label' => 'شروع طبقه‌ها از', 'min' => 0.2, 'max' => 0.8, 'step' => 0.05, 'default' => 0.45,
                'hint' => 'نسبت به بلندی پنل؛ نیم یعنی از وسط.',
            ],
            'ratio' => [
                'label' => 'نسبت پهنای هر طبقه به طبقه بالا', 'min' => 1.05, 'max' => 2.2, 'step' => 0.05,
                'default' => 1.5,
            ],
            'growth' => [
                'label' => 'نسبت بلندی هر طبقه به طبقه بالا', 'min' => 0.6, 'max' => 1.8, 'step' => 0.05,
                'default' => 1.15,
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        $hosts = $this->panelIndexes($pieces);

        if ($hosts === []) {
            return $this->noPanelMessage();
        }

        foreach ($hosts as $index) {
            $height = Geometry::height($pieces[$index]['outline']);

            if ($height < 28) {
                return 'پنل «'.$pieces[$index]['name'].'» فقط '.Format::cm($height)
                    .' بلندی دارد و جای طبقه‌بندی ندارد؛ دست‌کم ۲۸ سانتی‌متر لازم است.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $tiers = max(1, min(4, (int) $this->num($context, 'tiers', 2)));
        $start = min(0.85, max(0.15, $this->num($context, 'start', 0.45)));
        $ratio = max(1.02, $this->num($context, 'ratio', 1.5));
        $growth = max(0.5, $this->num($context, 'growth', 1.15));

        $hosts = $this->panelIndexes($pieces);
        $before = $this->rawGirth($pieces, 'hem', $hosts);
        $added = 0.0;
        $newPieces = [];
        $seam = 0;

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $height = Geometry::height($piece['outline']);
            $cutY = round($height * $start, 2);

            // لبه برش «درز طبقه» است نه دم لباس؛ فقط آخرین طبقه دم دارد
            [$top, $bottom] = StyleLineCutter::cutHorizontal($piece, $cutY, [
                'tag' => 'default',
                'codes' => [$piece['code'], $piece['code'].'-drop'],
                'names' => [$piece['name'], $piece['name'].' (تکه پایین)'],
                'pair' => 'tier-'.$seam,
            ]);

            $dropHeight = Geometry::height($bottom['outline']);
            $joinWidth = $this->joinWidth($top, $cutY);
            $repeats = $this->repeats($piece);

            $top['meta']['fullness_style'] = static::key();
            $top['meta']['tier_index'] = 0;
            $pieces[$index] = $top;

            // بلندی طبقه‌ها روی همان قدی که بریده شد پخش می‌شود
            $units = 0.0;

            for ($t = 0; $t < $tiers; $t++) {
                $units += $growth ** $t;
            }

            $first = $dropHeight / max(0.01, $units);
            $previous = $joinWidth;

            for ($t = 0; $t < $tiers; $t++) {
                $width = $previous * $ratio;
                $tierHeight = $first * ($growth ** $t);

                $tier = $this->tierPiece(
                    $width,
                    $tierHeight,
                    $piece,
                    $t + 1,
                    $repeats,
                    $t === $tiers - 1,
                );
                $tier = FullnessRecorder::gathers($tier, 0, $width - $previous, [
                    'label' => 'چین درز طبقه '.$this->fa($t + 1),
                    'source' => static::key(),
                ]);

                $added += $repeats * ($width - $previous);
                $newPieces[] = $tier;
                $previous = $width;
            }

            $seam++;
        }

        $pieces = array_merge($pieces, $newPieces);
        $after = $this->rawGirth($pieces, 'hem', $this->panelIndexes($pieces));

        return $this->report($pieces, [
            $this->note('tip', 'لباس از '.$this->fa(round($start * 100)).'٪ بلندی بریده شد و '
                .$this->fa($tiers).' طبقه زیر آن نشست؛ هر طبقه '.$this->fa(round($ratio, 2))
                .' برابر طبقه بالای خودش است.'),
            $this->note('info', 'دور دم لباس به '.Format::cm($after).' رسید. درز هر طبقه را با کوک درشت '
                .'جمع کنید و نشانه‌های جفت را روی هم بگذارید تا چین یکنواخت بماند.'),
        ], $added, [
            'tiers' => $tiers,
            'hem_before' => $before,
            'hem_after' => $after,
            'ratio' => round($ratio, 2),
        ]);
    }

    /**
     * پهنای لبه‌ای که طبقه بعد به آن دوخته می‌شود.
     *
     * بعد از برش افقی، لبه پایینِ تکه بالایی همان درز طبقه است؛ چون برچسبش
     * default است، آن را از روی جای برش پیدا می‌کنیم نه از روی برچسب.
     */
    protected function joinWidth(array $piece, float $cutY): float
    {
        $outline = array_values($piece['outline']);
        $bottom = Geometry::height($outline);
        $total = 0.0;

        for ($edge = 0; $edge < count($outline); $edge++) {
            $a = $outline[$edge];
            $b = $outline[($edge + 1) % count($outline)];

            if (abs(((float) $a['y']) - $bottom) < 0.15 && abs(((float) $b['y']) - $bottom) < 0.15) {
                $total += Geometry::edgeLength($outline, $edge);
            }
        }

        return $total > 0.1 ? round($total, 3) : round(Geometry::width($outline), 3);
    }

    /**
     * یک طبقه: مستطیل ساده با لبه بالای چین‌خور.
     *
     * @return array<string, mixed>
     */
    protected function tierPiece(float $width, float $height, array $host, int $index, int $repeats, bool $last): array
    {
        $width = max(4.0, $width);
        $height = max(3.0, $height);

        return $this->piece([
            'code' => $host['code'].'-tier-'.$index,
            'name' => $host['name'].' — طبقه '.$this->fa($index),
            'cut_quantity' => max(1, $repeats),
            'on_fold' => false,
            'mirror' => false,
            'outline' => $this->rect($width, $height),
            'grainline' => $this->grainline($width * 0.5, 1.5, $height - 1.5),
            'markers' => [$this->marker('tier', 'درز طبقه', 0, 0, $width)],
            'meta' => [
                'part' => 'skirt_tier',
                'edges' => ['default', 'side', $last ? 'hem' : 'default', 'side'],
                'fold_edges' => [],
                'side' => $host['meta']['side'] ?? 'front',
                'side_edges' => [1, 3],
                'hem_edges' => $last ? [2] : [],
                'tier_index' => $index,
                'tier_last' => $last,
                'finished_width' => round($width, 2),
                'length' => round($height, 2),
                'fullness' => [],
            ],
        ]);
    }
}
