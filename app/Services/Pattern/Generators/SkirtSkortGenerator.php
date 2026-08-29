<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;

/**
 * دامن‌شلواری (اسکورت).
 *
 * از بیرون دامن است و از داخل شلوارک. تفاوتش با کولوت — که در گروه پایین‌تنه
 * هست — این است که کولوت خودش یک شلوار گشاد است، ولی اسکورت دو لایهٔ جداست:
 * دامنِ رو که آزادانه می‌افتد و شلوارکِ زیر که کار پوشش را می‌کند.
 *
 * شلوارک زیر از خودِ کاتالوگِ شلوارک ساخته می‌شود، نه از نو؛ همان درفتی که
 * جای فاق و درز داخلی‌اش آزموده شده. فقط نامش و نقشش عوض می‌شود تا در چیدمان
 * و دستور دوخت معلوم باشد لایهٔ زیر است.
 *
 * دو چیز که اسکورت را خراب می‌کند و این‌جا حساب شده‌اند: شلوارک باید کوتاه‌تر
 * از دامن باشد (وگرنه از زیرش پیداست) و دامنِ رو نباید به شلوارک دوخته شود جز
 * روی خط کمر (وگرنه هنگام دویدن بالا می‌رود).
 */
class SkirtSkortGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_skort';
    }

    public function label(): string
    {
        return 'دامن‌شلواری (اسکورت)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(42, 25, 80),
            [
                'skirt_style' => [
                    'label' => 'فرم دامن رو', 'type' => 'select', 'default' => 'flare',
                    'options' => ['flare' => 'کلوش', 'pleat' => 'پیلی‌دار (تنیسی)', 'wrap' => 'رویهم (جلوباز)'],
                ],
                'flare' => [
                    'label' => 'گشادی دم دامن', 'min' => 4, 'max' => 45, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
                'pleats' => [
                    'label' => 'تعداد پیلی (اگر پیلی‌دار است)', 'min' => 4, 'max' => 24, 'step' => 2,
                    'default' => 12,
                ],
                'short_gap' => [
                    'label' => 'کوتاهی شلوارک از دامن', 'min' => 1, 'max' => 12, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                    'hint' => 'شلوارک باید کوتاه‌تر باشد وگرنه از زیر دامن پیداست.',
                ],
                /*
                 * هم‌نامِ باقیِ دامن‌ها، نه «کمر کشی».
                 *
                 * اسم مهم است چون کاتالوگ روی همین نام محورِ «پرداختِ خطِ کمر» را
                 * می‌چرخاند. تا وقتی اسکورت نامِ خودش را داشت، آن محور بی‌صدا از
                 * دستش می‌رفت و دو ردیفِ «کمربنددار» و «بی‌کمربند» یک الگو
                 * می‌دادند.
                 */
                'waistband' => [
                    'label' => 'نوار کمر جدا', 'type' => 'toggle', 'default' => false,
                    'hint' => 'خاموش یعنی کمر کشی؛ روشن یعنی نوار کمرِ دوخته با زیپ پهلو.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $elastic = ! $this->flag($params, 'waistband', false);
        $mx = $this->skirtMetrics($measurements, $ease, $params, ['dart_share' => $elastic ? 0 : 0.5]);

        $length = (float) $this->param($params, 'length', 42);
        $style = (string) $this->param($params, 'skirt_style', 'flare');
        $flare = (float) $this->param($params, 'flare', 16);
        $gap = max(1.0, (float) $this->param($params, 'short_gap', 4));

        $pieces = $style === 'pleat'
            ? $this->pleatedShell($mx, $params, $length)
            : $this->flareShell($mx, $length, $flare, $style === 'wrap');

        foreach ($this->innerShorts($measurements, $ease, max(12.0, $length - $gap)) as $short) {
            $pieces[] = $short;
        }

        if (! $elastic) {
            $pieces = array_merge($pieces, $this->bandPieces($mx, $params));
        }

        $notes = [
            'شلوارک زیر '.$this->fa($gap).' سانتی‌متر کوتاه‌تر از دامن است تا از زیرش پیدا نباشد.',
            'دامن رو فقط روی خط کمر به شلوارک دوخته می‌شود؛ اگر پایین‌تر هم بدوزید، هنگام دویدن بالا می‌رود.',
        ];

        if ($elastic) {
            $notes[] = 'کمر کشی است: هر دو لایه با هم روی یک نوار جای کش چین داده می‌شوند.';

            $pieces[] = $this->bandPiece($mx['waist_target'], [
                'code' => 'skort-casing',
                'name' => 'نوار جای کش کمر',
                'height' => 4,
                'overlap' => 1.5,
                'interfacing' => false,
            ]);

            $pieces[0]['meta']['notions'] = [[
                'type' => 'elastic',
                'label' => 'کش کمر سه سانتی‌متری',
                'length' => round($mx['waist_target'] * 0.92, 1),
            ]];
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $this->finishSkirt($pieces, ['zip' => $elastic ? 'none' : (string) $this->param($params, 'zip', 'side')]);
    }

    /**
     * دامن روی کلوش یا رویهم.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function flareShell(array $mx, float $length, float $flare, bool $wrap): array
    {
        $pieces = [];

        foreach ([['front', 'دامن رو — جلو', 'skirt_front'], ['back', 'دامن رو — پشت', 'skirt_back']] as [$side, $name, $part]) {
            $pieces[] = $this->blockPanel($mx, [
                'side' => $side,
                'length' => $length,
                'hem_delta' => $flare,
                'overlap' => $wrap && $side === 'front' ? 12 : 0,
                'code' => 'skort-'.$side,
                'name' => $name,
                'part' => $part,
            ]);
        }

        return $pieces;
    }

    /**
     * دامن روی پیلی‌دار (فرم تنیسی).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pleatedShell(array $mx, array $params, float $length): array
    {
        $count = max(4, (int) $this->param($params, 'pleats', 12));
        $depth = 4.0;
        $finished = max($mx['waist_target'], $mx['hip_target'] + 4);
        $width = $finished + ($count * 2 * $depth);

        return [$this->pleatedRectPanel([
            'width' => $width / 2,
            'length' => $length,
            'pleats' => (int) ceil($count / 2),
            'depth' => $depth,
            'finished_waist' => $mx['waist_target'] / 2,
            'style' => 'knife',
            'code' => 'skort-pleated',
            'name' => 'دامن رو — پیلی‌دار',
            'cut_quantity' => 2,
            'part' => 'skirt_panel',
        ])];
    }

    /**
     * شلوارک زیر، از روی همان درفت آزموده‌شدهٔ کاتالوگ شلوارک.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function innerShorts(array $measurements, array $ease, float $length): array
    {
        if (! GeneratorRegistry::has('shorts_cycling')) {
            return [];
        }

        $generator = GeneratorRegistry::make('shorts_cycling');

        // قد شلوارک از خط فاق اندازه می‌شود، نه از کمر؛ پس باید گودی فاق را از
        // قدِ خواسته‌شده کم کرد وگرنه شلوارک از زیر دامن بیرون می‌زند
        $rise = (float) ($measurements['rise'] ?? $measurements['crotch_depth'] ?? 26);
        $params = array_merge($generator->defaultParams(), [
            'waistband' => false,
            'leg_length' => max(8.0, min(35.0, $length - $rise)),
        ]);

        $out = [];

        foreach ($generator->generate($measurements, $ease, $params) as $piece) {
            if (($piece['meta']['part'] ?? '') === 'waistband') {
                continue;
            }

            $piece['code'] = 'skort-inner-'.($piece['code'] ?? 'short');
            $piece['name'] = 'شلوارک زیر — '.($piece['name'] ?? '');
            $piece['layer'] = 'lining';
            $piece['meta']['girth_role'] = 'lining';
            $piece['meta']['inner_short'] = true;
            $piece['meta']['notes'][] = 'لایهٔ زیر اسکورت؛ فقط روی خط کمر به دامن دوخته می‌شود.';

            $out[] = $piece;
        }

        return $out;
    }
}
