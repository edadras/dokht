<?php

namespace App\Services\Pattern\Style\Collar;

use App\Support\Format;

/**
 * یقه ایستاده کشی (نوار کشباف).
 *
 * یقه تی‌شرت و سویشرت: یک نوار کشباف که کوتاه‌تر از خط یقه بریده می‌شود، موقع
 * دوخت کشیده می‌شود و بعد جمع می‌شود و لبه یقه را صاف نگه می‌دارد. اگر نوار به
 * اندازه خط یقه بریده شود، یقه باز می‌ماند و بیرون می‌زند.
 *
 * پس این تنها یقه‌ای است که «آزادی» منفی دارد و باید داشته باشد. اندازه کوتاهی
 * به کشش پارچه بستگی دارد: کشباف ۱×۱ معمولاً ۸۵٪، کشباف تنگ‌تر تا ۷۵٪ و
 * کشباف نرم تا ۹۰٪ خط یقه بریده می‌شود.
 *
 * راستای پارچه هم برعکس بقیه است: کشش کشباف باید در راستای طول نوار باشد، پس
 * نوار در عرض پارچه (تارِ عرضی) بریده می‌شود.
 */
class RibCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_rib';
    }

    public function label(): string
    {
        return 'یقه ایستاده کشی';
    }

    public function description(): string
    {
        return 'نوار کشباف کوتاه‌تر از خط یقه؛ یقه تی‌شرت و سویشرت.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => [
                'label' => 'بلندی نوار پس از دوخت', 'min' => 1, 'max' => 8, 'step' => 0.25, 'default' => 2,
                'unit' => 'سانتی‌متر', 'hint' => 'نوار دولا تا می‌شود، پس دو برابر این بریده می‌شود.',
            ],
            'stretch' => [
                'label' => 'نسبت برش به خط یقه', 'min' => 0.6, 'max' => 1, 'step' => 0.01, 'default' => 0.85,
                'unit' => 'برابر', 'hint' => 'کشباف ۱×۱ حدود ۰٫۸۵؛ هرچه کشسان‌تر، عدد کمتر.',
            ],
            'seam' => [
                'label' => 'جای درز نوار', 'type' => 'select', 'default' => 'back',
                'options' => ['back' => 'مرکز پشت', 'shoulder' => 'درز سرشانه چپ'],
            ],
        ];
    }

    protected function supportsCollar(array $pieces, array $context): true|string
    {
        $fabric = is_array($context['fabric'] ?? null) ? $context['fabric'] : [];
        $stretch = $fabric['stretch'] ?? null;

        if ($stretch !== null && is_numeric($stretch) && (float) $stretch < 5.0) {
            return 'نوار کشی روی پارچه بی‌کشش («'.($fabric['name'] ?? 'پارچه انتخاب‌شده')
                .'») دوخته نمی‌شود؛ نوار باید موقع دوخت کشیده شود و دوباره جمع شود.'
                .' برای این پارچه یقه آخوندی یا سجاف بگیرید.';
        }

        if (array_key_exists('knit', $fabric) && $fabric['knit'] === false) {
            return 'این پارچه بافته (نه کشباف) است و کش نمی‌آید؛ یقه ایستاده کشی رویش جمع نمی‌شود.'
                .' یقه آخوندی یا نوار مورب مناسب‌تر است.';
        }

        return true;
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $stretch = (float) $p['stretch'];
        $height = (float) $p['height'];
        $target = max(8.0, $neck['full'] * $stretch);
        $ease = round($target - $neck['full'], 2);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->band($span, $height, $stretch, (string) $p['seam']),
            $target,
        );

        $piece = $this->neckNotches($piece, $this->stops($neck, $stretch, (string) $p['seam']));

        return [
            'pieces' => [$piece],
            'notes' => [
                'نوار '.Format::cm($length).' بریده شد در برابر خط یقه '.Format::cm($neck['full'])
                    .'؛ یعنی '.Format::cm(abs($ease)).' کوتاه‌تر ('.Format::percent($stretch * 100).' خط یقه).',
                'موقع دوخت، نوار را میان نشانه‌ها بکشید تا هم‌اندازه خط یقه شود و بعد رها کنید؛ کشیدن باید یکنواخت باشد وگرنه یقه موج می‌خورد.',
                'کشش کشباف باید در راستای طول نوار باشد، پس نوار در عرض پارچه بریده می‌شود؛ راستای پارچه روی الگو همین را نشان می‌دهد.',
                'دو سر نوار پیش از دوختن به لباس، در '.($p['seam'] === 'shoulder' ? 'درز سرشانه چپ' : 'مرکز پشت').' به هم دوخته می‌شود.',
            ],
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => $ease,
                'difference' => $difference,
                'stretch' => $stretch,
                'neckline' => $neck['full'],
                'height' => $height,
            ],
        ];
    }

    /**
     * نوار کشباف.
     *
     * @return array<string, mixed>
     */
    protected function band(float $span, float $height, float $stretch, string $seam): array
    {
        $span = max(8.0, $span);
        $full = max(1.0, $height) * 2;
        $shell = $this->strip($span, $full);

        return $this->newPiece([
            'code' => 'collar-rib',
            'name' => 'نوار یقه کشی',
            'cut_quantity' => 1,
            'outline' => $shell['outline'],
            // کشش کشباف در راستای طول نوار است، پس راستای پارچه عمود بر آن می‌افتد
            'grainline' => $this->grainlineBetween(
                ['x' => $span * 0.5, 'y' => 0.5],
                ['x' => $span * 0.5, 'y' => $full - 0.5],
                'راستای پارچه (کشش نوار در راستای طول)',
            ),
            'markers' => [
                $this->marker('fold', 'خط تای نوار', 0, $full / 2, $span, $full / 2),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'rib',
                'stretch' => round($stretch, 3),
                'seam_at' => $seam,
                'stretch_direction' => 'length',
            ],
        ]);
    }

    /**
     * نشانه‌ها روی نوار کوتاه‌شده: هر نقطه به همان نسبت کوتاهی جلو می‌آید.
     *
     * @return array<int, array{at: float, label: string, pair?: string}>
     */
    protected function stops(array $neck, float $stretch, string $seam): array
    {
        if ($seam === 'shoulder') {
            return [
                ['at' => $neck['front'] * $stretch, 'label' => 'مرکز جلو', 'pair' => 'center_front'],
                ['at' => ($neck['front'] + $neck['back']) * $stretch, 'label' => 'مرکز پشت', 'pair' => 'center_back'],
                ['at' => ($neck['front'] + $neck['back'] + $neck['back']) * $stretch, 'label' => 'درز سرشانه راست', 'pair' => 'shoulder'],
            ];
        }

        return [
            ['at' => $neck['back'] * $stretch, 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
            ['at' => $neck['half'] * $stretch, 'label' => 'مرکز جلو', 'pair' => 'center_front'],
            ['at' => ($neck['half'] + $neck['front']) * $stretch, 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
        ];
    }
}
