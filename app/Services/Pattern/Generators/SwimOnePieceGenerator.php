<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * مایو یک‌تکه.
 *
 * تنه‌ای که از شانه تا فاق می‌رود؛ از بیرون ساده است و از نظر الگو سخت‌ترین
 * مایو، چون یک عدد کل راحتی‌اش را تعیین می‌کند: «قد تنه» — فاصلهٔ شانه تا فاق
 * از جلو و پشت.
 *
 * اگر این عدد کوتاه باشد مایو روی شانه فشار می‌آورد و لای پا می‌کشد؛ اگر بلند
 * باشد روی تن چروک می‌افتد. در پارچهٔ کشی هم نمی‌شود با کش جبرانش کرد، چون
 * کشش پارچه در همان راستا مصرف شده است. برای همین این‌جا از اندازهٔ خودِ بدن
 * خوانده می‌شود و یک «آزادی قد تنه» جدا دارد که در پرو تنظیم می‌شود.
 */
class SwimOnePieceGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_onepiece';
    }

    public function label(): string
    {
        return 'مایو یک‌تکه';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema($this->onePieceParams());
    }

    /** @return array<string, array<string, mixed>> */
    protected function onePieceParams(array $extra = []): array
    {
        return array_merge([
            'torso_ease' => [
                'label' => 'آزادی قد تنه', 'min' => -6, 'max' => 10, 'step' => 0.5,
                'default' => 0, 'unit' => 'سانتی‌متر',
                'hint' => 'اگر مایو روی شانه فشار می‌آورد این عدد را بالا ببرید؛ اگر چروک می‌افتد پایین بیاورید.',
            ],
            'strap_width' => [
                'label' => 'پهنای بند', 'min' => 1.5, 'max' => 8, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'neck_drop' => [
                'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 26, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'back_drop' => [
                'label' => 'گودی پشت', 'min' => 0, 'max' => 34, 'step' => 0.5,
                'default' => 12, 'unit' => 'سانتی‌متر',
            ],
            'leg_rise' => [
                'label' => 'بالا آمدن خط پا', 'min' => 0, 'max' => 16, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
            ],
            'gusset' => [
                'label' => 'پهنای نوار فاق', 'min' => 6, 'max' => 16, 'step' => 0.5,
                'default' => 9, 'unit' => 'سانتی‌متر',
            ],
            'bust_cups' => [
                'label' => 'جای فنجان سینه', 'type' => 'toggle', 'default' => true,
            ],
        ], $extra);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->onePieceBody($measurements, $ease, $params);

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $this->swimNotes($params)),
        ));
    }

    /**
     * تنهٔ مایو یک‌تکه: جلو، پشت، نوار فاق و آنچه خواسته شده.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function onePieceBody(array $measurements, array $ease, array $params, array $o = []): array
    {
        $ease = $this->swimEase($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 4);
        $rise = (float) ($measurements['rise'] ?? $measurements['crotch_depth'] ?? 26);
        $torso = $rise + (float) $this->param($params, 'torso_ease', 0);

        $shared = [
            'shape' => 'straight',
            'length' => $torso,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'across_extra' => -min(4.0, $strap * 0.5),
            'armhole_drop' => 2.5,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => ($o['prefix'] ?? 'onepiece').'-front',
            'name' => 'مایو جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 8),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => ($o['prefix'] ?? 'onepiece').'-back',
            'name' => 'مایو پشت',
            'neck_depth_extra' => (float) $this->param($params, 'back_drop', 12),
        ]));

        // خط پا: از درز پهلو بالا می‌رود و تکهٔ بالا می‌ماند
        $legRise = (float) $this->param($params, 'leg_rise', 6);
        $gusset = max(6.0, (float) $this->param($params, 'gusset', 9));

        $sideY = max(
            4.0,
            min(Geometry::bounds($front['outline'])[3], Geometry::bounds($back['outline'])[3]) - 5.0 - $legRise,
        );

        $front = $this->carveLegLine($front, $sideY, $gusset);
        $back = $this->carveLegLine($back, $sideY, $gusset);

        foreach ([&$front, &$back] as &$piece) {
            $piece = $this->elastic($piece, 'hem', 'کش خط پا', $params);
            $piece = $this->elastic($piece, 'neck', 'کش یقه', $params);
            $piece = $this->elastic($piece, 'armhole', 'کش حلقه', $params);
        }

        unset($piece);

        $pieces = [$front, $back, $this->gussetPiece($gusset, ($o['prefix'] ?? 'onepiece'))];

        if ($this->flag($params, 'bust_cups', true)) {
            $pieces[] = $this->cupPocket();
        }

        return $pieces;
    }

    /** بریدن خط پا از لبهٔ پایین. */
    protected function carveLegLine(array $piece, float $sideY, float $gusset): array
    {
        [, , , $maxY] = Geometry::bounds($piece['outline']);

        return $this->cutTop($piece, [
            'keep' => 'upper',
            'center' => $maxY - 0.4,
            'side' => $sideY,
            'shape' => 'scoop',
        ]);
    }

    protected function gussetPiece(float $width, string $prefix): array
    {
        return $this->bandPiece($prefix.'-gusset', 'نوار فاق', $width, 6.5, [
            'cut' => 2, 'part' => 'gusset',
            'meta' => [
                'girth_role' => 'lining',
                'notes' => [
                    'دو لایه: یکی از پارچهٔ رو و یکی از آستر.',
                    'نوار فاق هم بهداشتی است و هم نمی‌گذارد پارچه در آب نازک شود.',
                ],
            ],
        ]);
    }

    protected function cupPocket(): array
    {
        return $this->bandPiece('cup-pocket', 'جیب فنجان سینه', 34, 8, [
            'cut' => 1, 'part' => 'lining',
            'meta' => [
                'girth_role' => 'lining',
                'notes' => [
                    'نواری که روی آستر جلو دوخته می‌شود و فنجان از کنارش داخل می‌رود؛'
                        .' یک طرفش باز می‌ماند تا فنجان درآید و مایو شسته شود.',
                ],
            ],
        ]);
    }
}
