<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;

/**
 * خروجی DXF (نسخه R12، متنی) برای بردن الگو به نرم‌افزارهای CAD و کاتر.
 *
 * واحد فایل میلی‌متر است (سانتی‌متر × ۱۰) و محور y در CAD به بالا است، پس y الگو
 * قرینه می‌شود. هر قطعه روی یک لایه با نام خودش قرار می‌گیرد و خط برش (با جای دوخت)
 * روی لایه‌ای با پسوند _SA کشیده می‌شود.
 */
class DxfExporter
{
    public function __construct(protected SeamAllowanceService $seams = new SeamAllowanceService) {}

    /**
     * تولید فایل DXF.
     *
     * گزینه‌ها: seam_allowance (خط برش هم کشیده شود)، grainline، labels، gap (فاصله قطعه‌ها).
     */
    public function export(Pattern $pattern, array $options = []): string
    {
        $withSeam = $options['seam_allowance'] ?? true;
        $withGrainline = $options['grainline'] ?? true;
        $withLabels = $options['labels'] ?? true;
        $gap = (float) ($options['gap'] ?? 5);

        $layers = [];
        $entities = '';
        $offsetX = 0.0;
        $minX = 0.0;
        $maxX = 0.0;
        $maxY = 0.0;

        foreach ($pattern->pieces as $piece) {
            $layer = $this->layerName($piece);
            $layers[$layer] = true;

            [$pieceMinX, $pieceMinY, $pieceMaxX, $pieceMaxY] = $piece->bounds();
            $dx = $offsetX - $pieceMinX;

            $sewing = array_map(
                fn (array $point) => $this->toDxf($point, $dx, 0),
                Geometry::flatten($piece->outline ?? []),
            );

            $entities .= $this->polyline($sewing, $layer);

            if ($withSeam) {
                $layers[$layer.'_SA'] = true;
                $cutting = array_map(
                    fn (array $point) => $this->toDxf($point, $dx, 0),
                    $this->seams->cuttingLine($piece),
                );
                $entities .= $this->polyline($cutting, $layer.'_SA');
            }

            foreach ($piece->darts ?? [] as $dart) {
                if (! isset($dart['apex']['x'], $dart['legs'][0]['x'], $dart['legs'][1]['x'])) {
                    continue;
                }

                $layers[$layer.'_DART'] = true;

                foreach ($dart['legs'] as $leg) {
                    $entities .= $this->line(
                        $this->toDxf($leg, $dx, 0),
                        $this->toDxf($dart['apex'], $dx, 0),
                        $layer.'_DART',
                    );
                }
            }

            if ($withGrainline && isset($piece->grainline['from']['x'], $piece->grainline['to']['x'])) {
                $layers[$layer.'_GRAIN'] = true;
                $entities .= $this->line(
                    $this->toDxf($piece->grainline['from'], $dx, 0),
                    $this->toDxf($piece->grainline['to'], $dx, 0),
                    $layer.'_GRAIN',
                );
            }

            foreach ($piece->notches ?? [] as $notch) {
                if (! isset($notch['x'], $notch['y'])) {
                    continue;
                }

                $layers[$layer.'_NOTCH'] = true;
                $point = $this->toDxf($notch, $dx, 0);
                $entities .= $this->line(
                    ['x' => $point['x'] - 2, 'y' => $point['y']],
                    ['x' => $point['x'] + 2, 'y' => $point['y']],
                    $layer.'_NOTCH',
                );
            }

            if ($withLabels) {
                $layers['TEXT'] = true;
                $entities .= $this->text(
                    $piece->code.' x'.$piece->cut_quantity,
                    ($pieceMinX + $dx) * 10,
                    -($pieceMaxY * 10) - 12,
                    'TEXT',
                );
            }

            $offsetX += ($pieceMaxX - $pieceMinX) + $gap;
            $maxX = max($maxX, $offsetX * 10);
            $maxY = max($maxY, ($pieceMaxY - $pieceMinY) * 10);
        }

        if ($layers === []) {
            $layers['PATTERN'] = true;
        }

        return $this->header(-20, -$maxY - 40, $maxX + 20, 20)
            .$this->tables(array_keys($layers))
            .$this->section('ENTITIES', $entities)
            .$this->pair(0, 'EOF');
    }

    /** نام لایه: حروف بزرگ لاتین، بدون فاصله (محدودیت DXF R12). */
    protected function layerName(PatternPiece $piece): string
    {
        $name = strtoupper((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $piece->code ?: 'PIECE'));
        $name = trim($name, '_');

        return $name === '' ? 'PIECE_'.$piece->id : substr($name, 0, 30);
    }

    /** تبدیل نقطه الگو (سانتی‌متر، y به پایین) به مختصات DXF (میلی‌متر، y به بالا). */
    protected function toDxf(array $point, float $dx, float $dy): array
    {
        return [
            'x' => round((((float) ($point['x'] ?? 0)) + $dx) * 10, 3),
            'y' => round(-((((float) ($point['y'] ?? 0)) + $dy) * 10), 3),
        ];
    }

    protected function header(float $minX, float $minY, float $maxX, float $maxY): string
    {
        return $this->section('HEADER',
            $this->pair(9, '$ACADVER').$this->pair(1, 'AC1009')
            .$this->pair(9, '$INSUNITS').$this->pair(70, 4) // ۴ = میلی‌متر
            .$this->pair(9, '$MEASUREMENT').$this->pair(70, 1)
            .$this->pair(9, '$EXTMIN').$this->pair(10, $minX).$this->pair(20, $minY).$this->pair(30, 0)
            .$this->pair(9, '$EXTMAX').$this->pair(10, $maxX).$this->pair(20, $maxY).$this->pair(30, 0)
        );
    }

    /** @param  array<int, string>  $layers */
    protected function tables(array $layers): string
    {
        $body = $this->pair(0, 'TABLE').$this->pair(2, 'LAYER').$this->pair(70, count($layers) + 1)
            .$this->layer('0', 7);

        $color = 1;

        foreach ($layers as $layer) {
            $body .= $this->layer($layer, ($color % 7) + 1);
            $color++;
        }

        $body .= $this->pair(0, 'ENDTAB');

        return $this->section('TABLES', $body);
    }

    protected function layer(string $name, int $color): string
    {
        return $this->pair(0, 'LAYER')
            .$this->pair(2, $name)
            .$this->pair(70, 0)
            .$this->pair(62, $color)
            .$this->pair(6, 'CONTINUOUS');
    }

    /**
     * چندضلعی بسته به شکل POLYLINE + VERTEX + SEQEND (سازگار با R12).
     *
     * @param  array<int, array{x: float, y: float}>  $points
     */
    protected function polyline(array $points, string $layer): string
    {
        if (count($points) < 2) {
            return '';
        }

        $body = $this->pair(0, 'POLYLINE')
            .$this->pair(8, $layer)
            .$this->pair(66, 1)
            .$this->pair(70, 1) // بسته
            .$this->pair(10, 0).$this->pair(20, 0).$this->pair(30, 0);

        foreach ($points as $point) {
            $body .= $this->pair(0, 'VERTEX')
                .$this->pair(8, $layer)
                .$this->pair(10, $point['x'])
                .$this->pair(20, $point['y'])
                .$this->pair(30, 0);
        }

        return $body.$this->pair(0, 'SEQEND').$this->pair(8, $layer);
    }

    protected function line(array $from, array $to, string $layer): string
    {
        return $this->pair(0, 'LINE')
            .$this->pair(8, $layer)
            .$this->pair(10, $from['x']).$this->pair(20, $from['y']).$this->pair(30, 0)
            .$this->pair(11, $to['x']).$this->pair(21, $to['y']).$this->pair(31, 0);
    }

    protected function text(string $value, float $x, float $y, string $layer, float $height = 8): string
    {
        return $this->pair(0, 'TEXT')
            .$this->pair(8, $layer)
            .$this->pair(10, $x).$this->pair(20, $y).$this->pair(30, 0)
            .$this->pair(40, $height)
            .$this->pair(1, $this->asciiOnly($value));
    }

    protected function section(string $name, string $body): string
    {
        return $this->pair(0, 'SECTION').$this->pair(2, $name).$body.$this->pair(0, 'ENDSEC');
    }

    /** هر مقدار DXF دو خط است: کد گروه و مقدار. */
    protected function pair(int $code, float|int|string $value): string
    {
        if (is_float($value) || is_int($value)) {
            $value = is_int($value) ? (string) $value : rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

            if ($value === '' || $value === '-') {
                $value = '0';
            }
        }

        return $code."\n".$value."\n";
    }

    /** متن DXF R12 فقط اَسکی است. */
    protected function asciiOnly(string $value): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '', $value);

        return $clean === '' ? 'PIECE' : $clean;
    }
}
