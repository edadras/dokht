<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * یقه پاپیونی (بندی).
 *
 * یک نوار بلند که میانه‌اش روی خط یقه دوخته می‌شود و دو سرش آزاد می‌ماند تا
 * پاپیون یا گره بخورد. پس نوار سه بخش دارد: دو دنباله و یک میانه؛ فقط میانه
 * برچسب خط یقه می‌گیرد و همان است که باید اندازه دور یقه دربیاید.
 *
 * نوار روی مورب پارچه بریده می‌شود تا گره نرم بیفتد و لبه‌اش موقع پیچیدن
 * نشکند؛ اگر پارچه نازک باشد می‌شود روی راستای پارچه هم برید و در پارامترها
 * انتخاب می‌شود.
 */
class TieCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_tie';
    }

    public function label(): string
    {
        return 'یقه پاپیونی (بندی)';
    }

    public function description(): string
    {
        return 'نوار بلند دور گردن با دو دنباله برای گره یا پاپیون؛ یقه بلوز پاپیون‌دار.';
    }

    public function paramsSchema(): array
    {
        return [
            'band_height' => [
                'label' => 'پهنای نوار', 'min' => 1.5, 'max' => 9, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'پهنای نوار پس از تا شدن.',
            ],
            'tie_length' => [
                'label' => 'بلندی هر دنباله', 'min' => 15, 'max' => 90, 'step' => 1, 'default' => 45,
                'unit' => 'سانتی‌متر', 'hint' => 'برای پاپیون دست‌کم ۳۵ سانت لازم است.',
            ],
            'tie_end' => [
                'label' => 'سر دنباله', 'type' => 'select', 'default' => 'angled',
                'options' => ['angled' => 'کج', 'square' => 'راست', 'pointed' => 'نوک‌تیز'],
            ],
            'bias' => [
                'label' => 'برش روی مورب', 'type' => 'toggle', 'default' => true,
                'hint' => 'گره نرم‌تر می‌افتد ولی پارچه بیشتری می‌برد.',
            ],
            'ease' => $this->easeField(0.5),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $target = max(8.0, $neck['full'] + (float) $p['ease']);
        $tie = (float) $p['tie_length'];
        $height = (float) $p['band_height'] * 2;

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->band($span, $tie, $height, (string) $p['tie_end'], ! empty($p['bias'])),
            $target,
        );

        $piece = $this->neckNotches($piece, [
            ['at' => 0.0, 'label' => 'مرکز جلو (سر راست)', 'pair' => 'center_front'],
            ['at' => $neck['front'], 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
            ['at' => $neck['half'], 'label' => 'مرکز پشت', 'pair' => 'center_back'],
            ['at' => $neck['half'] + $neck['back'], 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
            ['at' => $length, 'label' => 'مرکز جلو (سر چپ)', 'pair' => 'center_front'],
        ]);

        $total = round($length + (2 * $tie), 2);

        return [
            'pieces' => [$piece],
            'notes' => [
                'نوار روی هم '.Format::cm($total).' بلندی دارد: '.Format::cm($length)
                    .' میانه که روی خط یقه می‌نشیند و دو دنباله '.Format::cm($tie).'ی.',
                'فقط میانه نوار به خط یقه دوخته می‌شود؛ دنباله‌ها از مرکز جلو به بعد آزادند و در همان‌جا گره می‌خورند.',
                ! empty($p['bias'])
                    ? 'نوار روی مورب ۴۵ درجه بریده می‌شود؛ اگر پارچه به اندازه طول نوار پهنا ندارد، نوار را وسط دنباله وصله بزنید نه وسط میانه.'
                    : 'نوار روی راستای پارچه بریده می‌شود؛ گره سفت‌تر می‌افتد و برای پارچه نازک مناسب‌تر است.',
            ],
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'tie_length' => $tie,
                'total_length' => $total,
                'bias' => ! empty($p['bias']),
            ],
        ];
    }

    /**
     * نوار سه‌بخشی: دنباله، میانه (خط یقه)، دنباله.
     *
     * @return array<string, mixed>
     */
    protected function band(float $span, float $tie, float $height, string $end, bool $bias): array
    {
        $span = max(6.0, $span);
        $slant = match ($end) {
            'square' => 0.0,
            'pointed' => $height * 0.5,
            default => $height * 0.6,
        };

        $left = 0.0;
        $right = $tie + $span + $tie;

        // بالای نوار: سر چپ ← آغاز میانه ← پایان میانه ← سر راست
        $top = [
            Geometry::point($left + ($end === 'pointed' ? 0 : $slant), 0),
            Geometry::point($tie, 0),
            Geometry::point($tie + $span, 0),
            Geometry::point($right - ($end === 'pointed' ? 0 : $slant), 0),
        ];

        $bottom = [
            Geometry::point($right, $height),
            Geometry::point($left, $height),
        ];

        if ($end === 'pointed') {
            $top[0] = Geometry::point($left + $slant, 0);
            $bottom[1] = Geometry::point($left + $slant, $height);
            $top[3] = Geometry::point($right - $slant, 0);
            $bottom[0] = Geometry::point($right - $slant, $height);

            $outline = [
                $top[0], $top[1], $top[2], $top[3],
                Geometry::point($right, $height / 2),
                $bottom[0], $bottom[1],
                Geometry::point($left, $height / 2),
            ];
            $edges = ['default', 'neck', 'default', 'side', 'side', 'hem', 'side', 'side'];
        } else {
            $outline = array_merge($top, $bottom);
            $edges = ['default', 'neck', 'default', 'side', 'hem', 'side'];
        }

        $piece = $this->newPiece([
            'code' => 'collar-tie',
            'name' => 'یقه پاپیونی',
            'cut_quantity' => 1,
            'outline' => $outline,
            'markers' => [
                $this->marker('fold', 'خط تای نوار', $left, $height / 2, $right, $height / 2),
                $this->marker('cb', 'مرکز پشت', $tie + ($span / 2), 0, $tie + ($span / 2), $height),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $edges,
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'tie',
                'tie_length' => round($tie, 2),
                'bias' => $bias,
            ],
        ]);

        $piece['grainline'] = $bias
            ? $this->grainlineBetween(['x' => $tie, 'y' => $height - 0.5], ['x' => $tie + $height - 1, 'y' => 0.5], 'مورب ۴۵ درجه')
            : $this->grainlineBetween(['x' => $tie, 'y' => $height / 2], ['x' => $tie + min($span, 20.0), 'y' => $height / 2]);

        return $piece;
    }
}
