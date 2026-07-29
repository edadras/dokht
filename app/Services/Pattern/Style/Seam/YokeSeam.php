<?php

namespace App\Services\Pattern\Style\Seam;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\StyleLineCutter;
use App\Support\Format;

/**
 * یوک: بریدن نوار بالای قطعه با یک خط افقی.
 *
 * همان چیزی که در پیراهن مردانه روی سرشانهٔ پشت است، در شلوار جین روی باسن پشت،
 * و در دامن روی کمر. کارش هم فقط تزیین نیست: یوک اجازه می‌دهد راستای پارچهٔ آن
 * تکه فرق کند (نوار عرضی روی یوک) و ساسون سرشانه یا باسن در خودِ درز یوک حل شود.
 *
 * برش از بالای قطعه اندازه گرفته می‌شود، نه از کمر؛ چون «بالا» تنها چیزی است که
 * روی هر سه جور میزبان — بالاتنه، دامن و شلوار — یک معنا دارد.
 */
class YokeSeam extends SeamStyle
{
    public static function key(): string
    {
        return 'seam_yoke';
    }

    public function label(): string
    {
        return 'یوک (برش بالا)';
    }

    public function description(): string
    {
        return 'نوار بالای قطعه با یک خط افقی جدا می‌شود؛ یوک پیراهن، یوک جین و یوک دامن.';
    }

    public function paramsSchema(): array
    {
        return [
            'where' => $this->whereParam('back'),
            'depth' => [
                'label' => 'بلندی یوک', 'min' => 3, 'max' => 40, 'step' => 0.5, 'default' => 9,
                'unit' => 'سانتی‌متر', 'hint' => 'از بالاترین جای قطعه به پایین اندازه می‌شود.',
            ],
            'cross_grain' => [
                'label' => 'راستای پارچهٔ یوک عرضی باشد',
                'type' => 'toggle', 'default' => false,
                'hint' => 'راه‌راه یا طرح‌دار که باشد، یوکِ عرضی خودش یک تزیین است.',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->alreadyCut($pieces)) {
            return 'این لباس همین حالا یوک دارد.';
        }

        $hosts = $this->hostIndexes($pieces, $context);

        if ($hosts === []) {
            return $this->noHostMessage($context);
        }

        $depth = $this->num($context, 'depth', 9);

        foreach ($hosts as $index) {
            $height = Geometry::height($pieces[$index]['outline']);

            if ($depth >= $height - 3) {
                return 'بلندی یوک از خودِ قطعهٔ «'.$pieces[$index]['name'].'» بیشتر است؛ عدد کمتری بگذارید.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $depth = $this->num($context, 'depth', 9);
        $cross = $this->flag($context, 'cross_grain');

        $notes = [];
        $seam = 0.0;
        $count = 0;

        // از آخر به اول، تا شماره‌های بعدی با جاگذاری دو نیمه به هم نریزد
        foreach (array_reverse($this->hostIndexes($pieces, $context)) as $index) {
            $host = $pieces[$index];
            [, $minY] = Geometry::bounds($host['outline']);

            try {
                $halves = StyleLineCutter::cutHorizontal($host, $minY + $depth, [
                    'tag' => 'default',
                    'codes' => [($host['code'] ?? 'piece').'-yoke', $host['code'] ?? 'piece'],
                    'names' => [$host['name'].' — یوک', $host['name']],
                    'pair' => 'yoke-'.($host['code'] ?? $index),
                ]);
            } catch (\InvalidArgumentException $error) {
                $notes[] = $this->note('warning', 'یوک روی «'.$host['name'].'» بریده نشد: '.$error->getMessage());

                continue;
            }

            // cutHorizontal بالایی را اول برمی‌گرداند: یوک همان بالایی است
            if ($cross) {
                $halves[0] = $this->rotateGrain($halves[0]);
            }

            [$pieces, $length] = $this->placeHalves(
                $pieces, $index, $halves, keep: 1, suffix: 'yoke', newName: $host['name'].' — یوک',
            );

            $seam = max($seam, $length);
            $count++;
        }

        if ($count === 0) {
            return $this->result($pieces, $notes);
        }

        $notes[] = $this->note('info', 'یوک به بلندی '.Format::cm($depth).' جدا شد؛ درز یوک با نشانه‌های جفت روی هر دو تکه علامت خورده است.');

        if ($cross) {
            $notes[] = $this->note('info', 'راستای پارچهٔ یوک عرضی است؛ در چیدمان جای بیشتری می‌خواهد و اگر پارچه کش دارد، یوک را لایی بزنید.');
        }

        $notes[] = $this->seamNote($seam, $count);

        return $this->result($pieces, $notes, ['seam_added' => round($seam * $count, 2)]);
    }

    /** راستای پارچه ۹۰ درجه می‌چرخد؛ خودِ قطعه سر جایش می‌ماند. */
    protected function rotateGrain(array $piece): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece['outline']);

        $piece['grainline'] = [
            'from' => Geometry::point($minX + 0.8, ($minY + $maxY) / 2),
            'to' => Geometry::point($maxX - 0.8, ($minY + $maxY) / 2),
            'label' => 'راستای پارچه (عرضی)',
        ];

        $piece['meta']['cross_grain'] = true;

        return $piece;
    }
}
