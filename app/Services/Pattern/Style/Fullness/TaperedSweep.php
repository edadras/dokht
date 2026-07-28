<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Support\Format;

/**
 * باریک کردن.
 *
 * دم لباس از درز پهلو تو برده می‌شود و بقیه پنل سر جایش می‌ماند. چون سرِ پهلوی
 * لبه دم روی خودِ لبه به سمت مرکز می‌لغزد، درز پهلو کمی کوتاه‌تر می‌شود — و چون
 * روی جلو و پشت یک‌اندازه اجرا می‌شود، باز هم دو درز به هم می‌خورند.
 *
 * این سبک هرگز از خط باسن بالاتر نمی‌رود، پس اندازه باسن و کمر دست‌نخورده است.
 */
class TaperedSweep extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_taper';
    }

    public function label(): string
    {
        return 'باریک کردن';
    }

    public function description(): string
    {
        return 'دم لباس از هر درز پهلو تو برده می‌شود؛ باسن و کمر دست نمی‌خورند.';
    }

    public function paramsSchema(): array
    {
        return [
            'take_in' => [
                'label' => 'تو رفتن هر درز پهلو', 'min' => 0.5, 'max' => 12, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        $hosts = $this->panelIndexes($pieces);

        if ($hosts === []) {
            return $this->noPanelMessage();
        }

        $takeIn = $this->num($context, 'take_in', 3);

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');
            $hem = $edge === null ? 0.0 : $this->edgeLength($piece, $edge);

            if ($hem <= $takeIn + 6) {
                return 'دم پنل «'.$piece['name'].'» فقط '.Format::cm($hem)
                    .' است و این‌قدر جا برای باریک شدن ندارد.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $takeIn = max(0.1, $this->num($context, 'take_in', 3));
        $hosts = $this->panelIndexes($pieces);

        $before = $this->rawGirth($pieces, 'hem', $hosts);

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');

            if ($edge === null) {
                continue;
            }

            $piece = $this->moveHemCorner($piece, $edge, $takeIn);
            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['taper'] = round($takeIn, 2);
            $pieces[$index] = $piece;
        }

        $after = $this->rawGirth($pieces, 'hem', $hosts);

        return $this->report($pieces, [
            $this->note('tip', 'دور دم لباس از '.Format::cm($before).' به '.Format::cm($after)
                .' رسید؛ هر درز پهلو '.Format::cm($takeIn).' تو رفت.'),
            $this->note('info', 'باریک شدن از خط باسن پایین‌تر شروع می‌شود، پس نشستن و راه رفتن تنگ نمی‌شود؛ '
                .'اگر بیشتر از این تو ببرید چاک لازم می‌شود.'),
        ], $after - $before, [
            'hem_before' => $before,
            'hem_after' => $after,
            'take_in' => round($takeIn, 2),
        ]);
    }
}
