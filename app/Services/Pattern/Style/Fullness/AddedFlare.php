<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Support\Format;

/**
 * کلوش کردن.
 *
 * پنل از دم بریده و باز می‌شود و نوک پرگار روی خط کمر می‌ماند: خط کمر یک نخ هم
 * پهن‌تر نمی‌شود و همه پارچه اضافه به دم می‌رود. این همان «برش و باز کردن» روی
 * کاغذ است و با چند برش انجام می‌شود تا موج به‌جای یک نقطه، دور تا دور پخش شود.
 *
 * چون هر برش هم درز پهلو و هم دم را بلندتر می‌کند، سبک روی همه پنل‌های پایین‌تنه
 * با هم اجرا می‌شود تا درزها باز هم به هم بخورند.
 */
class AddedFlare extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_flare';
    }

    public function label(): string
    {
        return 'کلوش کردن';
    }

    public function description(): string
    {
        return 'با برش و باز کردن، دم لباس باز می‌شود بدون اینکه خط کمر تکان بخورد.';
    }

    public function paramsSchema(): array
    {
        return [
            'sweep' => [
                'label' => 'اضافه شدن دور دم', 'min' => 4, 'max' => 160, 'step' => 2, 'default' => 40,
                'unit' => 'سانتی‌متر',
                'hint' => 'روی کل دور دم لباس حساب می‌شود، نه روی یک پنل.',
            ],
            'slashes' => [
                'label' => 'تعداد برش روی هر پنل', 'min' => 1, 'max' => 8, 'step' => 1, 'default' => 3,
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
            if ($this->edgeWithTag($pieces[$index], 'waist') === null) {
                return 'پنل «'.$pieces[$index]['name'].'» لبه کمر ندارد، پس نوک پرگار کلوش جایی برای نشستن ندارد.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $sweep = max(1.0, $this->num($context, 'sweep', 40));
        $slashes = max(1, (int) $this->num($context, 'slashes', 3));
        $hosts = $this->panelIndexes($pieces);

        $repeats = 0;

        foreach ($hosts as $index) {
            $repeats += $this->repeats($pieces[$index]);
        }

        if ($repeats === 0) {
            return $this->report($pieces, [], 0.0);
        }

        $perPanel = $sweep / $repeats;
        $before = $this->rawGirth($pieces, 'hem', $hosts);
        $waistBefore = $this->girth($pieces, 'waist', $hosts);

        foreach ($hosts as $index) {
            $piece = $this->slashSpread(
                $pieces[$index],
                $slashes,
                $perPanel / $slashes,
                'waist',
                'hem',
                'ease',
                'کلوش',
            );

            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['flare_added'] = round($perPanel, 2);
            $pieces[$index] = $piece;
        }

        $after = $this->rawGirth($pieces, 'hem', $hosts);
        $waistAfter = $this->girth($pieces, 'waist', $hosts);

        return $this->report($pieces, [
            $this->note('tip', 'دور دم لباس از '.Format::cm($before).' به '.Format::cm($after)
                .' رسید؛ روی هر پنل '.$this->fa($slashes).' برش باز شد.'),
            $this->note('info', 'خط کمر دست نخورد و هنوز '.Format::cm($waistAfter).' است.'),
            $this->note('warn', 'دم کلوش را پیش از دوخت لبه، یک شب آویزان بگذارید تا اریب پارچه بنشیند و بعد گونیا کنید.'),
        ], $after - $before, [
            'hem_before' => $before,
            'hem_after' => $after,
            'waist_before' => $waistBefore,
            'waist_after' => $waistAfter,
        ]);
    }
}
