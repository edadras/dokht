<?php

namespace App\Services\Pattern\Style\Seam;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\StyleLineCutter;

/**
 * برش مورب: خطی که از یک پهلو تا پهلوی دیگر می‌رود، ولی نه افقی.
 *
 * این همان برشی است که یک لباس را از «درست» به «مال خودش» می‌برد: برش مورب روی
 * تنه، خط اریب روی دامن، برش زانوی مورب روی شلوار. چون دو سرِ خط دو ارتفاع
 * متفاوت دارند، برش روی جلو و پشت هم می‌تواند ادامه پیدا کند و دور تن یک خط
 * پیوسته بسازد.
 *
 * روی قطعهٔ روی تای پارچه اجرا نمی‌شود: آن‌جا هر خط موربی در نیمهٔ دیگر آینه
 * می‌شود و به جای برش مورب، یک هفتِ قرینه درمی‌آید.
 */
class DiagonalSeam extends SeamStyle
{
    public static function key(): string
    {
        return 'seam_diagonal';
    }

    public function label(): string
    {
        return 'برش مورب';
    }

    public function description(): string
    {
        return 'خطی مورب از یک پهلو تا پهلوی دیگر؛ دو سرش را جدا تنظیم می‌کنید و می‌تواند کمانی باشد.';
    }

    public function paramsSchema(): array
    {
        return [
            'where' => $this->whereParam('front'),
            'start' => [
                'label' => 'سر برش روی لبهٔ چپ', 'min' => 5, 'max' => 95, 'step' => 1, 'default' => 35,
                'unit' => 'درصد بلندی', 'hint' => 'از بالای قطعه به پایین.',
            ],
            'end' => [
                'label' => 'سر برش روی لبهٔ راست', 'min' => 5, 'max' => 95, 'step' => 1, 'default' => 60,
                'unit' => 'درصد بلندی',
            ],
            'bow' => [
                'label' => 'کمان برش', 'min' => -10, 'max' => 10, 'step' => 0.5, 'default' => 0,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی خط راست؛ عدد مثبت کمان را به پایین می‌برد.',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->alreadyCut($pieces)) {
            return 'این لباس همین حالا برش مورب دارد.';
        }

        $hosts = $this->hostIndexes($pieces, $context);

        if ($hosts === []) {
            return $this->noHostMessage($context);
        }

        if (abs($this->num($context, 'start', 35) - $this->num($context, 'end', 60)) < 4) {
            return 'دو سر برش تقریباً هم‌ارتفاع‌اند؛ این یعنی برش عرضی، نه مورب.';
        }

        foreach ($hosts as $index) {
            if (! empty($pieces[$index]['on_fold'])) {
                return 'قطعهٔ «'.$pieces[$index]['name'].'» روی تای پارچه بریده می‌شود و خط مورب روی آن قرینه می‌شود.'
                    .' برای برش مورب واقعی، مدلی با مرکز باز انتخاب کنید.';
            }

            if (Geometry::height($pieces[$index]['outline']) < 15) {
                return 'قطعهٔ «'.$pieces[$index]['name'].'» برای برش مورب کوتاه است.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $start = min(95.0, max(5.0, $this->num($context, 'start', 35))) / 100;
        $end = min(95.0, max(5.0, $this->num($context, 'end', 60))) / 100;
        $bow = $this->num($context, 'bow', 0);

        $notes = [];
        $seam = 0.0;
        $count = 0;

        foreach (array_reverse($this->hostIndexes($pieces, $context)) as $index) {
            $host = $pieces[$index];
            [$minX, $minY, $maxX, $maxY] = Geometry::bounds($host['outline']);
            $height = $maxY - $minY;

            $from = ['x' => $minX - 1, 'y' => $minY + ($height * $start)];
            $to = ['x' => $maxX + 1, 'y' => $minY + ($height * $end)];

            $path = [$from];

            if (abs($bow) > 0.05) {
                $middle = [
                    'x' => ($from['x'] + $to['x']) / 2,
                    'y' => ($from['y'] + $to['y']) / 2,
                ];

                $path[] = array_merge($middle, [
                    'curve' => true,
                    'cx' => $middle['x'],
                    'cy' => $middle['y'] + $bow,
                ]);
            }

            $path[] = $to;

            try {
                $halves = StyleLineCutter::cut($host, $path, [
                    'tag' => 'default',
                    'codes' => [$host['code'] ?? 'piece', ($host['code'] ?? 'piece').'-lower'],
                    'names' => [$host['name'].' — بالای برش', $host['name'].' — پایین برش'],
                    'pair' => 'diagonal-'.($host['code'] ?? $index),
                ]);
            } catch (\InvalidArgumentException $error) {
                $notes[] = $this->note('warning', 'برش مورب روی «'.$host['name'].'» انجام نشد: '.$error->getMessage());

                continue;
            }

            $keep = Geometry::centroid($halves[0]['outline'])['y'] <= Geometry::centroid($halves[1]['outline'])['y'] ? 0 : 1;

            [$pieces, $length] = $this->placeHalves(
                $pieces, $index, $halves, keep: $keep, suffix: 'lower', newName: $host['name'].' — پایین برش',
            );

            $seam = max($seam, $length);
            $count++;
        }

        if ($count === 0) {
            return $this->result($pieces, $notes);
        }

        $notes[] = $this->note('info', 'برش مورب انجام شد؛ روی هر دو لبه نشانهٔ جفت هست تا دوباره درست به هم بنشیند.');
        $notes[] = $this->note('warning', 'خط مورب روی پارچه نه راستا دارد نه عرض؛ اگر پارچه نرم است لبهٔ برش را نوار بچسبانید'
            .' تا هنگام دوخت کش نیاید.');
        $notes[] = $this->seamNote($seam, $count);

        return $this->result($pieces, $notes, ['seam_added' => round($seam * $count, 2)]);
    }
}
