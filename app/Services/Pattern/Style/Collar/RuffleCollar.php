<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Transform\FullnessRecorder;
use App\Support\Format;

/**
 * یقه چین‌دار.
 *
 * نواری که دور خط یقه چین می‌خورد و مثل یقه دلقکی یا یقه ژابو دور گردن می‌ریزد.
 * دو راه برای ساختن چین هست و هر دو این‌جا هست:
 *
 *   چین‌خورده — نوار راست و بلندتر از خط یقه بریده می‌شود و سر چرخ جمع می‌شود.
 *               پارچه‌اش بیشتر است ولی روی خط یقه به اندازه خط یقه می‌نشیند، پس
 *               مقدار چین با FullnessRecorder ثبت می‌شود تا برشکار و برگه فنی
 *               هم بدانند و طول دوخته‌شده درست حساب شود.
 *   دایره‌ای — یقه به شکل حلقه بریده می‌شود؛ لبه یقه به اندازه خط یقه است ولی
 *               لبه بیرونی خیلی بلندتر، پس بدون هیچ چینی موج می‌خورد. چون حلقه
 *               کامل روی کاغذ جا نمی‌شود، به چند تکه شکسته می‌شود.
 */
class RuffleCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_ruffle';
    }

    public function label(): string
    {
        return 'یقه چین‌دار';
    }

    public function description(): string
    {
        return 'نوار چین‌خورده یا حلقه موج‌دار دور خط یقه؛ یقه ژابو و دلقکی.';
    }

    public function paramsSchema(): array
    {
        return [
            'style' => [
                'label' => 'راه چین', 'type' => 'select', 'default' => 'gathered',
                'options' => ['gathered' => 'چین‌خورده (نوار جمع‌شده)', 'circular' => 'دایره‌ای (موج بدون چین)'],
            ],
            'width' => [
                'label' => 'پهنای یقه', 'min' => 2, 'max' => 20, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر',
            ],
            'fullness' => [
                'label' => 'ضریب چین', 'min' => 1.2, 'max' => 3.5, 'step' => 0.1, 'default' => 2,
                'unit' => 'برابر', 'hint' => 'دو یعنی پارچه دو برابر خط یقه بریده شود.',
            ],
            'ease' => $this->easeField(),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $target = max(8.0, $neck['full'] + (float) $p['ease']);
        $fullness = (float) $p['fullness'];
        $width = (float) $p['width'];

        return (string) $p['style'] === 'circular'
            ? $this->circular($neck, $target, $fullness, $width, $p)
            : $this->gathered($neck, $target, $fullness, $width, $p);
    }

    /**
     * نوار راست که جمع می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function gathered(array $neck, float $target, float $fullness, float $width, array $p): array
    {
        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->strap($span, $width, $fullness),
            $target,
        );

        $raw = (float) ($piece['meta']['raw_length'] ?? 0);
        $piece = $this->neckNotches($piece, $this->stops($neck, $target, $fullness));

        return [
            'pieces' => [$piece],
            'notes' => [
                'نوار یقه '.Format::cm($raw).' بریده می‌شود و روی خط یقه '.Format::cm($length)
                    .' جمع می‌شود؛ '.Format::cm($raw - $length).' پارچه به چین می‌رود ('.Format::ratio($fullness).').',
                'دو سر نوار در مرکز پشت به هم می‌رسد؛ نشانه‌های سرشانه و مرکز جلو روی نوار خورده تا چین یکنواخت پخش شود.',
                'نوار دولا تا می‌شود، پس لبه بیرونی درز ندارد و لازم نیست پاکدوزی شود.',
            ],
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'raw_length' => round($raw, 2),
                'gathered' => round($raw - $length, 2),
                'fullness' => $fullness,
                'style' => 'gathered',
            ],
        ];
    }

    /**
     * نوار خام با چین ثبت‌شده.
     *
     * طول خام $span است و آنچه روی خط یقه می‌نشیند span ÷ ضریب چین؛ اختلاف با
     * FullnessRecorder روی همان لبه ثبت می‌شود تا طول دوخته‌شده درست دربیاید.
     *
     * @return array<string, mixed>
     */
    protected function strap(float $span, float $width, float $fullness): array
    {
        $span = max(6.0, $span);
        $height = max(1.5, $width) * 2;
        $shell = $this->strip($span, $height);

        $piece = $this->newPiece([
            'code' => 'collar-ruffle',
            'name' => 'یقه چین‌دار',
            'cut_quantity' => 1,
            'outline' => $shell['outline'],
            'grainline' => $this->grainline($span * 0.5, 0.8, $height - 0.8),
            'markers' => [
                $this->marker('fold', 'خط تای نوار', 0, $height / 2, $span, $height / 2),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'ruffle',
                'raw_length' => round($span, 2),
                'fullness' => round($fullness, 2),
            ],
        ]);

        return FullnessRecorder::gathers($piece, 0, $span - ($span / max(1.05, $fullness)), [
            'label' => 'چین یقه',
            'replace' => true,
        ]);
    }

    /**
     * حلقه موج‌دار، شکسته به چند تکه.
     *
     * @return array<string, mixed>
     */
    protected function circular(array $neck, float $target, float $fullness, float $width, array $p): array
    {
        $radius = $this->arcRadiusFor($target, $width, $target * ($fullness - 1));
        $sections = max(1, (int) ceil(($target / $radius) / 2.2));
        $slice = $target / $sections;

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->flounce($span, $width, $radius, $sections),
            $slice,
        );

        $outer = $this->seamOf($piece, 'hem');

        return [
            'pieces' => [$piece],
            'notes' => [
                'یقه دایره‌ای در '.$sections.' تکه بریده می‌شود؛ هر تکه '.Format::cm($length)
                    .' از خط یقه را می‌گیرد و روی هم '.Format::cm($length * $sections).' می‌شود.',
                'لبه بیرونی هر تکه '.Format::cm($outer).' است در برابر لبه یقه '.Format::cm($length)
                    .'؛ همین اختلاف بدون هیچ چینی موج می‌سازد.',
                'لبه بیرونی حلقه روی مورب پارچه می‌افتد و کش می‌آید؛ پیش از پاکدوزی بگذارید یک شب آویزان بماند.',
            ],
            'meta' => [
                'target' => round($slice, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'sections' => $sections,
                'neckline_total' => round($target, 2),
                'outer_edge' => round($outer, 2),
                'style' => 'circular',
            ],
        ];
    }

    /**
     * یک تکه از حلقه.
     *
     * @return array<string, mixed>
     */
    protected function flounce(float $span, float $width, float $radius, int $sections): array
    {
        $arc = $this->collarArc($span, $width, $radius, 'fall');
        $shell = $this->assembleCollar($arc);

        return $this->newPiece([
            'code' => 'collar-ruffle-circular',
            'name' => 'یقه چین‌دار دایره‌ای',
            'cut_quantity' => $sections,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'flounce',
                'sections' => $sections,
                'radius' => $arc['radius'],
            ],
        ]);
    }

    /**
     * نشانه‌های روی نوار خام: جای هر نقطه به نسبت ضریب چین جلو می‌رود.
     *
     * @return array<int, array{at: float, label: string, pair?: string}>
     */
    protected function stops(array $neck, float $target, float $fullness): array
    {
        $scale = $target > 0.01 ? ($fullness) : 1.0;

        return [
            ['at' => $neck['back'] * $scale, 'label' => 'درز سرشانه پشت', 'pair' => 'shoulder'],
            ['at' => $neck['half'] * $scale, 'label' => 'مرکز جلو', 'pair' => 'center_front'],
            ['at' => ($neck['half'] + $neck['front']) * $scale, 'label' => 'درز سرشانه جلو', 'pair' => 'shoulder'],
        ];
    }
}
