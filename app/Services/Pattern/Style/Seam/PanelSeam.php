<?php

namespace App\Services\Pattern\Style\Seam;

use App\Services\Pattern\Geometry;

/**
 * پنل: بریدن قطعه با یک خط عمودی از بالا تا پایین.
 *
 * روی شلوار می‌شود پنل کناری (همان درزی که در شلوار ورزشی نوار رنگی رویش
 * می‌نشیند)، روی دامن می‌شود دامن ترک‌دار، و روی بالاتنه می‌شود برش طولی.
 *
 * دو نکته که این برش را از «نصف کردن» جدا می‌کند:
 *
 *   - جای برش نسبی است (درصدی از پهنای قطعه)، پس در همهٔ سایزها همان‌جا می‌ماند.
 *   - خط برش می‌تواند کمانی باشد؛ کمان مثبت پنل را در وسط پهن‌تر می‌کند. همین
 *     کمان است که یک پنل ساده را به برشی که مال خودِ برند است تبدیل می‌کند.
 *
 * روی قطعه‌ای که روی تای پارچه بریده می‌شود این برش اجرا نمی‌شود؛ آن‌جا خط برش
 * روی مرکز جلو یا مرکز پشت می‌افتد و معنایش «باز کردن مرکز» است، نه پنل.
 */
class PanelSeam extends SeamStyle
{
    public static function key(): string
    {
        return 'seam_panel';
    }

    public function label(): string
    {
        return 'پنل (برش طولی)';
    }

    public function description(): string
    {
        return 'قطعه با یک خط عمودی — راست یا کمانی — به دو پنل تقسیم می‌شود؛ پنل کناری شلوار، ترک دامن، برش طولی تنه.';
    }

    public function paramsSchema(): array
    {
        return [
            'where' => $this->whereParam('both'),
            'position' => [
                'label' => 'جای برش', 'min' => 15, 'max' => 85, 'step' => 1, 'default' => 65,
                'unit' => 'درصد پهنا', 'hint' => 'از لبهٔ مرکز به سمت پهلو اندازه می‌شود.',
            ],
            'bow' => [
                'label' => 'کمان برش', 'min' => -8, 'max' => 8, 'step' => 0.5, 'default' => 0,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی خط راست؛ عدد مثبت پنل کناری را در وسط پهن‌تر می‌کند.',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->alreadyCut($pieces)) {
            return 'این لباس همین حالا برش طولی دارد.';
        }

        $hosts = $this->hostIndexes($pieces, $context);

        if ($hosts === []) {
            return $this->noHostMessage($context);
        }

        foreach ($hosts as $index) {
            $piece = $pieces[$index];

            if (! empty($piece['on_fold'])) {
                return 'قطعهٔ «'.$piece['name'].'» روی تای پارچه بریده می‌شود؛ برش طولی روی آن یعنی باز کردن مرکز.'
                    .' اول یک بست جلو بگذارید یا مدلی بگیرید که مرکزش باز است.';
            }

            if (Geometry::width($piece['outline']) < 12) {
                return 'قطعهٔ «'.$piece['name'].'» برای دو پنل شدن باریک است.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $position = min(85.0, max(15.0, $this->num($context, 'position', 65))) / 100;
        $bow = $this->num($context, 'bow', 0);

        $notes = [];
        $seam = 0.0;
        $count = 0;

        foreach (array_reverse($this->hostIndexes($pieces, $context)) as $index) {
            $host = $pieces[$index];
            [$minX, , $maxX] = Geometry::bounds($host['outline']);
            $x = $minX + (($maxX - $minX) * $position);

            try {
                $halves = $this->cutVertical($host, $x, [
                    'tag' => 'default',
                    'codes' => [$host['code'] ?? 'piece', ($host['code'] ?? 'piece').'-panel'],
                    'names' => [$host['name'], $host['name'].' — پنل کناری'],
                    'pair' => 'panel-'.($host['code'] ?? $index),
                ], $bow);
            } catch (\InvalidArgumentException $error) {
                $notes[] = $this->note('warning', 'برش طولی روی «'.$host['name'].'» انجام نشد: '.$error->getMessage());

                continue;
            }

            // نیمهٔ سمت مرکز (x کمتر) قطعهٔ اصلی می‌ماند، نیمهٔ پهلو پنل تازه است
            $keep = Geometry::centroid($halves[0]['outline'])['x'] <= Geometry::centroid($halves[1]['outline'])['x'] ? 0 : 1;

            [$pieces, $length] = $this->placeHalves(
                $pieces, $index, $halves, keep: $keep, suffix: 'panel', newName: $host['name'].' — پنل کناری',
            );

            $seam = max($seam, $length);
            $count++;
        }

        if ($count === 0) {
            return $this->result($pieces, $notes);
        }

        $notes[] = $this->note('info', 'برش طولی در '.round($position * 100).'٪ پهنا انجام شد؛ روی هر درز تازه نشانهٔ جفت هست.');

        if (abs($bow) > 0.05) {
            $notes[] = $this->note('warning', 'خط برش کمانی است؛ دو لبه هم‌اندازه‌اند ولی یکی کاو و دیگری کوژ است.'
                .' هنگام دوخت لبهٔ کاو را کمی چرت بزنید تا صاف بنشیند.');
        }

        $notes[] = $this->seamNote($seam, $count);

        return $this->result($pieces, $notes, ['seam_added' => round($seam * $count, 2)]);
    }
}
