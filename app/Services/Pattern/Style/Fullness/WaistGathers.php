<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Support\Format;

/**
 * چین کمر.
 *
 * دو کار با هم انجام می‌شود و هر دو لازم‌اند:
 *   ۱. ساسون‌های کمر بسته نمی‌شوند بلکه به چین تبدیل می‌شوند؛ پارچه همان است ولی
 *      به‌جای دوخته‌شدن، جمع می‌شود.
 *   ۲. به اندازه نسبت پُری خواسته‌شده پارچه اضافه می‌شود، با برش و باز کردن از خط
 *      کمر و پرگار روی دم؛ پس دم لباس تکان نمی‌خورد و فقط کمر پُر می‌شود.
 *
 * دور کمر تمام‌شده عوض نمی‌شود چون هرچه پارچه اضافه شد، همان‌قدر چین ثبت شد.
 */
class WaistGathers extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_waist_gathers';
    }

    public function label(): string
    {
        return 'چین کمر';
    }

    public function description(): string
    {
        return 'ساسون‌های کمر به چین تبدیل می‌شوند و به اندازه نسبت پُری پارچه اضافه می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'fullness' => [
                'label' => 'نسبت پُری چین', 'min' => 1, 'max' => 3, 'step' => 0.05, 'default' => 1.4,
                'hint' => 'پارچه خط کمر چند برابر اندازه تمام‌شده بریده شود.',
            ],
            'slashes' => [
                'label' => 'تعداد برش پخش‌کننده', 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 3,
                'hint' => 'هرچه بیشتر، چین یکنواخت‌تر پخش می‌شود.',
            ],
            'keep_darts' => [
                'label' => 'ساسون‌ها بمانند', 'type' => 'toggle', 'default' => false,
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
            if ($this->edgeWithTag($pieces[$index], 'waist') !== null) {
                return true;
            }
        }

        return 'هیچ‌کدام از پنل‌های این لباس لبه کمر ندارند؛ چین کمر جایی برای نشستن ندارد.';
    }

    public function apply(array $pieces, array $context): array
    {
        $ratio = max(1.0, $this->num($context, 'fullness', 1.4));
        $slashes = max(1, (int) $this->num($context, 'slashes', 3));
        $keepDarts = $this->flag($context, 'keep_darts', false);

        $hosts = array_values(array_filter(
            $this->panelIndexes($pieces),
            fn (int $index) => $this->edgeWithTag($pieces[$index], 'waist') !== null,
        ));

        $before = $this->girth($pieces, 'waist', $hosts);
        $added = 0.0;
        $fromDarts = 0.0;

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'waist');
            $finished = $this->seamLength($piece, $edge);
            $repeats = $this->repeats($piece);

            // ۱) ساسون‌های کمر باز می‌شوند و پارچه‌شان به چین می‌رود
            if (! $keepDarts) {
                $intake = 0.0;

                foreach ($piece['darts'] ?? [] as $dart) {
                    if ((int) ($dart['edge'] ?? -1) === $edge && ($dart['type'] ?? '') === 'waist') {
                        $intake += (float) ($dart['intake'] ?? 0);
                    }
                }

                if ($intake > 0.05) {
                    $piece['darts'] = array_values(array_filter(
                        $piece['darts'],
                        fn ($dart) => ! ((int) ($dart['edge'] ?? -1) === $edge && ($dart['type'] ?? '') === 'waist'),
                    ));
                    $fromDarts += $repeats * $intake;
                }
            }

            // ۲) پارچه اضافه با برش و باز کردن؛ نوک پرگار روی دم می‌ماند
            $extra = max(0.0, ($finished * $ratio) - $this->rawLength($piece, 'waist'));

            if ($extra > 0.05) {
                $piece = $this->slashSpread($piece, $slashes, $extra / $slashes, 'hem', 'waist');
                $added += $repeats * $extra;
            }

            // ۳) هرچه پارچه از اندازه تمام‌شده بیشتر است چین می‌شود؛ اگر ساسون‌ها
            // مانده باشند، سهم آن‌ها از این حساب کم می‌شود تا دوبار شمرده نشود
            $stillDarted = 0.0;

            foreach ($piece['darts'] ?? [] as $dart) {
                if (($dart['type'] ?? '') === 'waist') {
                    $stillDarted += (float) ($dart['intake'] ?? 0);
                }
            }

            $piece = $this->recordAcross(
                $piece,
                'waist',
                max(0.0, $this->rawLength($piece, 'waist') - $finished - $stillDarted),
                'gathers',
                ['label' => 'چین کمر'],
            );

            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['waist_gather_ratio'] = round($ratio, 2);
            $pieces[$index] = $piece;
        }

        $after = $this->girth($pieces, 'waist', $hosts);
        $fabric = $this->rawGirth($pieces, 'waist', $hosts);

        return $this->report($pieces, [
            $this->note('tip', 'خط کمر حالا '.Format::cm($fabric).' پارچه دارد که با چین به '
                .Format::cm($after).' جمع می‌شود؛ نسبت پُری '.$this->fa(round($fabric / max(0.1, $after), 2)).' برابر.'),
            $this->note('info', $fromDarts > 0.05
                ? 'ساسون‌های کمر باز شدند و '.Format::cm($fromDarts).' پارچه‌شان به چین رفت.'
                : 'ساسون‌ها سر جایشان ماندند و چین کنارشان نشست.'),
        ], $added, [
            'waist_before' => $before,
            'waist_after' => $after,
            'waist_fabric' => $fabric,
            'ratio' => round($ratio, 2),
        ]);
    }
}
