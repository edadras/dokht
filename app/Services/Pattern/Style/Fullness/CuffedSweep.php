<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * دم‌پا برگردان.
 *
 * دم پا به بیرون تا می‌شود و دیده می‌شود. برای اینکه برگردان کامل باشد، قطعه به
 * اندازه «دو برابر پهنای برگردان + یک سانتی‌متر تو گذاشتن» بلندتر بریده می‌شود:
 * یک بار به بیرون تا می‌خورد و یک بار به داخل، و آن یک سانت لبه‌اش تو می‌رود.
 *
 * چون درز پهلو و درز داخل پا هم با همین اندازه بلند می‌شوند، جلو و پشت باز هم به
 * هم می‌خورند. اگر دم پا باریک‌شونده باشد، برگردان روی جای بازتری از پا می‌افتد؛
 * همین را در یادداشت می‌گوییم.
 */
class CuffedSweep extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_cuff';
    }

    public function label(): string
    {
        return 'دم‌پا برگردان';
    }

    public function description(): string
    {
        return 'دم شلوار یا دامن به بیرون تا می‌شود؛ قطعه دو برابر پهنای برگردان بلندتر بریده می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => [
                'label' => 'پهنای برگردان', 'min' => 1.5, 'max' => 12, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر', 'hint' => 'همین اندازه از بیرون دیده می‌شود.',
            ],
            'turn_under' => [
                'label' => 'تو گذاشتن لبه', 'min' => 0.5, 'max' => 4, 'step' => 0.5, 'default' => 1,
                'unit' => 'سانتی‌متر',
            ],
            'tack' => [
                'label' => 'دوخت پنهان روی درزها', 'type' => 'toggle', 'default' => true,
                'hint' => 'برگردان با چند کوک روی درز پهلو و درز داخل پا ثابت می‌شود.',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        $hosts = $this->panelIndexes($pieces);

        if ($hosts === []) {
            return $this->noPanelMessage();
        }

        $depth = $this->num($context, 'depth', 4);

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $width = $this->hemLength($piece);

            if ($width < $depth + 4) {
                return 'دم پنل «'.$piece['name'].'» فقط '.Format::cm($width)
                    .' است؛ برگردانِ این‌قدر پهن روی دمِ به این باریکی تا نمی‌خورد.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $depth = max(0.5, $this->num($context, 'depth', 4));
        $under = max(0.2, $this->num($context, 'turn_under', 1));
        $tack = $this->flag($context, 'tack', true);
        $extra = round(($depth * 2) + $under, 2);
        $hosts = $this->panelIndexes($pieces);

        $added = 0.0;
        $names = [];

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $edges = $this->hemEdges($piece);

            if ($edges === []) {
                continue;
            }

            $piece = PieceOps::extend($piece, 'hem', $extra, [
                'edges' => $edges,
                'direction' => $this->hemOutward($piece),
            ]);

            $height = Geometry::height($piece['outline']);
            $piece['markers'][] = $this->marker('fold', 'خط تای برگردان', 0, $height - $depth, 4, $height - $depth);
            $piece['markers'][] = $this->marker('fold', 'خط تای دوم برگردان', 0, $height - $extra, 4, $height - $extra);
            $piece['meta']['hem_allowance'] = 0.0;
            $piece['meta']['allowance_overrides']['hem'] = 0.0;
            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['hem_turnup'] = round($depth, 2);
            $piece['meta']['hem_extension'] = $extra;

            $added += $this->repeats($piece) * $extra;
            $names[] = $piece['name'];
            $pieces[$index] = $this->reindexAnchors($piece);
        }

        $notes = [
            $this->note('tip', 'برگردان '.Format::cm($depth).'ی روی '.implode('، ', $names).' گذاشته شد؛ '
                .'هر قطعه '.Format::cm($extra).' بلندتر بریده می‌شود ('.Format::cm($depth).' × ۲ + '
                .Format::cm($under).' تو گذاشتن).'),
            $this->note('info', 'جای دوخت لبه دم صفر شد، چون همین بلندی اضافه خودش برگردان است.'),
        ];

        if ($tack) {
            $notes[] = $this->note('tip', 'برگردان را با چند کوک پنهان روی درز پهلو و درز داخل پا ثابت کنید '
                .'تا موقع شستن باز نشود.');
        }

        return $this->report($pieces, $notes, $added, [
            'depth' => round($depth, 2),
            'extension' => $extra,
        ]);
    }

    /**
     * بردار عمود بر دم، رو به بیرون قطعه.
     *
     * وقتی دم بعد از برش و باز کردن چند لبه شده، عمودِ خودکارِ PieceOps گیج
     * می‌شود؛ اینجا از خط بین دو سرِ دم حسابش می‌کنیم و اگر آن هم به‌دردنخور بود،
     * رو به پایین می‌گیریم.
     *
     * @return array{x: float, y: float}
     */
    protected function hemOutward(array $piece): array
    {
        $corners = $this->hemCorners($piece);
        $outline = array_values($piece['outline']);

        if ($corners !== null) {
            $dx = ((float) $outline[$corners['side']]['x']) - ((float) $outline[$corners['center']]['x']);
            $dy = ((float) $outline[$corners['side']]['y']) - ((float) $outline[$corners['center']]['y']);
            $norm = sqrt(($dx * $dx) + ($dy * $dy));

            if ($norm > 0.1) {
                return ['x' => -$dy / $norm, 'y' => $dx / $norm];
            }
        }

        return ['x' => 0.0, 'y' => 1.0];
    }
}
