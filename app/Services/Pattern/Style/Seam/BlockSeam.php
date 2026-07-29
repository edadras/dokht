<?php

namespace App\Services\Pattern\Style\Seam;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\StyleLineCutter;

/**
 * کالربلاک: بریدن قطعه با یک خط افقی در جایی که خودتان می‌گویید.
 *
 * فرقش با یوک این است که یوک نوار کوچکی از بالاست و کار سازه‌ای می‌کند، ولی
 * کالربلاک هرجای قطعه می‌نشیند و کارش رنگ و طرح است: نیمهٔ بالا یک پارچه، نیمهٔ
 * پایین پارچه‌ای دیگر.
 *
 * جای برش نسبی است تا در سایزبندی همان‌جا بماند، و اگر بخواهید می‌شود به جای
 * درصد روی یک خط نشانهٔ واقعی نشست (کمر، باسن، سینه) که در همهٔ سایزها معنایش
 * یکی است.
 */
class BlockSeam extends SeamStyle
{
    public static function key(): string
    {
        return 'seam_block';
    }

    public function label(): string
    {
        return 'کالربلاک (برش عرضی)';
    }

    public function description(): string
    {
        return 'قطعه با یک خط افقی به دو تکه می‌شود تا هر تکه پارچه یا رنگ خودش را بگیرد.';
    }

    public function paramsSchema(): array
    {
        return [
            'where' => $this->whereParam('both'),
            'at' => [
                'label' => 'جای برش',
                'type' => 'select', 'default' => 'ratio',
                'options' => [
                    'ratio' => 'درصدی از بلندی قطعه',
                    'waist' => 'روی خط کمر',
                    'hip' => 'روی خط باسن',
                    'bust' => 'روی خط سینه',
                ],
            ],
            'position' => [
                'label' => 'جای برش (اگر درصدی است)', 'min' => 10, 'max' => 90, 'step' => 1, 'default' => 45,
                'unit' => 'درصد بلندی', 'hint' => 'از بالای قطعه به پایین.',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->alreadyCut($pieces)) {
            return 'این لباس همین حالا برش عرضی دارد.';
        }

        $hosts = $this->hostIndexes($pieces, $context);

        if ($hosts === []) {
            return $this->noHostMessage($context);
        }

        $at = $this->text($context, 'at', 'ratio');

        if ($at === 'ratio') {
            return true;
        }

        foreach ($hosts as $index) {
            if ($this->markerY($pieces[$index], $at) === null) {
                return 'قطعهٔ «'.$pieces[$index]['name'].'» خط نشانهٔ خواسته‌شده را ندارد؛ برش درصدی را انتخاب کنید.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $at = $this->text($context, 'at', 'ratio');
        $ratio = min(90.0, max(10.0, $this->num($context, 'position', 45))) / 100;

        $notes = [];
        $seam = 0.0;
        $count = 0;

        foreach (array_reverse($this->hostIndexes($pieces, $context)) as $index) {
            $host = $pieces[$index];
            [, $minY, , $maxY] = Geometry::bounds($host['outline']);

            $y = $at === 'ratio'
                ? $minY + (($maxY - $minY) * $ratio)
                : ($this->markerY($host, $at) ?? $minY + (($maxY - $minY) * $ratio));

            $y = max($minY + 2, min($maxY - 2, $y));

            try {
                $halves = StyleLineCutter::cutHorizontal($host, $y, [
                    'tag' => 'default',
                    'codes' => [$host['code'] ?? 'piece', ($host['code'] ?? 'piece').'-lower'],
                    'names' => [$host['name'].' — تکهٔ بالا', $host['name'].' — تکهٔ پایین'],
                    'pair' => 'block-'.($host['code'] ?? $index),
                ]);
            } catch (\InvalidArgumentException $error) {
                $notes[] = $this->note('warning', 'برش عرضی روی «'.$host['name'].'» انجام نشد: '.$error->getMessage());

                continue;
            }

            [$pieces, $length] = $this->placeHalves(
                $pieces, $index, $halves, keep: 0, suffix: 'lower', newName: $host['name'].' — تکهٔ پایین',
            );

            $seam = max($seam, $length);
            $count++;
        }

        if ($count === 0) {
            return $this->result($pieces, $notes);
        }

        $where = $at === 'ratio' ? round($ratio * 100).'٪ بلندی' : 'خط '.$this->markerLabel($at);
        $notes[] = $this->note('info', 'برش عرضی روی '.$where.' انجام شد؛ هر تکه می‌تواند پارچهٔ خودش را داشته باشد.');
        $notes[] = $this->note('info', 'اگر دو پارچه وزن یکسانی ندارند، سبک‌تر را لایی نازک بزنید تا درز موج نیندازد.');
        $notes[] = $this->seamNote($seam, $count);

        return $this->result($pieces, $notes, ['seam_added' => round($seam * $count, 2)]);
    }

    protected function markerLabel(string $key): string
    {
        return match ($key) {
            'waist' => 'کمر',
            'hip' => 'باسن',
            'bust' => 'سینه',
            default => $key,
        };
    }
}
