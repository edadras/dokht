<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Generators\Concerns\CutsStretchFabric;

/**
 * پایه مشترک لباس خواب.
 *
 * این خانواده دو نیمه دارد و مرزشان جنس پارچه است، نه سلیقه:
 *
 *   **بافته و گشاد** — پیژامه و روب دوشی. از فلانل، پوپلین یا حوله‌ای بریده
 *   می‌شوند، هیچ کششی ندارند و باید آزادی مثبت بگیرند تا در خواب بپیچند و
 *   نکشند. این مدل‌ها `$negativeEase` را خاموش می‌کنند و هیچ مهرِ کشسانی
 *   نمی‌گیرند؛ فرقشان با نیمهٔ دیگر باید صریح باشد.
 *
 *   **کشی و تن‌نما** — لباس خواب بلند و شلوارک خواب. از جرسی و ساتن کشی بریده
 *   می‌شوند و مثل لباس زیر کوچک‌تر از بدن‌اند، وگرنه در خواب جمع می‌شوند.
 *
 * دو ابزار مشترک این‌جاست: درفت آستین (که سرآستینش روی حلقهٔ همین درفت پیاده
 * می‌شود، نه با فرمول سرانگشتی) و قرض گرفتن پایین‌تنه از کاتالوگ شلوار — چون
 * پایین پیژامه واقعاً یک شلوار است و نامش هم باید همان باشد.
 */
abstract class SleepwearBaseGenerator extends TopBaseGenerator
{
    use BuildsSleeve;
    use CutsStretchFabric;

    public static function group(): string
    {
        return 'sleepwear';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای لباس خوابِ بافته (پیژامه، روب): آزادی مثبت.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function wovenSchema(array $extra = [], float $grow = 3.5): array
    {
        return array_merge([
            'ease_extra' => [
                'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 0, 'max' => 10, 'step' => 0.5,
                'default' => $grow, 'unit' => 'سانتی‌متر',
                'hint' => 'لباس خوابِ بافته کشش ندارد؛ آزادی است که نمی‌گذارد در خواب بکشد.',
            ],
        ], $extra);
    }

    /**
     * پارامترهای لباس خوابِ کشی (لباس خواب بلند، شلوارک): آزادی منفی.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function knitSchema(array $extra = [], float $stretch = 0.92): array
    {
        return array_merge($this->stretchSchema($stretch), $extra);
    }

    /* ---------------------------------------------------------------------
     |  آستین
     * ------------------------------------------------------------------- */

    /** طول کل حلقه آستین (جلو + پشت) از روی خودِ پنل‌ها، نه از اندازهٔ بدن. */
    protected function armholeOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $total += (float) ($piece['meta']['armhole_length'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * آستین ست‌این، پیاده‌شده روی حلقهٔ همین درفت.
     *
     * @param  array<int, array<string, mixed>>  $body
     * @return array<int, array<string, mixed>>
     */
    protected function sleeveSet(array $m, array $ease, array $params, array $body, array $o = []): array
    {
        $length = (float) ($o['length'] ?? 0);

        if ($length < 4) {
            return [];
        }

        $pieces = $this->sleevePieces($m, $ease, $params, array_merge([
            'armhole_length' => $this->armholeOf($body),
            'length' => $length,
            'no_cuff' => true,
        ], $o));

        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['girth_role'] = $piece['meta']['girth_role'] ?? 'sleeve';
        }

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  پایین‌تنه قرضی
     * ------------------------------------------------------------------- */

    /**
     * پایین‌تنه از کاتالوگ شلوار.
     *
     * پایین پیژامه و شلوارک خواب واقعاً شلوارند: درز داخل پا دارند، منحنی فاق
     * دارند و پاچه‌شان دور پا می‌پیچد. پس از همان درفت آزمودهٔ شلوار می‌آیند و
     * نام قطعه‌شان هم عوض نمی‌شود؛ «پاچهٔ شلوار» نامیدن چیزی که پاچهٔ شلوار است،
     * صادقانه‌ترین کار است.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function bottomFrom(string $key, array $m, array $ease, array $params, array $o = []): array
    {
        $generator = GeneratorRegistry::make($key);

        $built = $generator->generate($m, $ease, array_merge(
            $generator->defaultParams(),
            $o['params'] ?? [],
        ));

        $prefix = (string) ($o['prefix'] ?? 'sleep');
        $skip = $o['skip'] ?? [];
        $out = [];

        foreach ($built as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (in_array($part, $skip, true)) {
                continue;
            }

            $piece['code'] = $prefix.'-'.($piece['code'] ?? 'piece');
            $piece['meta']['borrowed_from'] = $key;

            // درفت شلوار نقش دور را اعلام نمی‌کند؛ همین‌جا اعلامش می‌کنیم تا مهرِ
            // کشسانی روی پاچه‌ها هم بنشیند و بررسی‌ها ببینندش
            if (in_array($part, ['front_leg', 'back_leg'], true)) {
                $piece['meta']['girth_role'] = $piece['meta']['girth_role'] ?? 'shell';
            }

            $out[] = $piece;
        }

        if ($out !== [] && ($o['notes'] ?? []) !== []) {
            $out[0]['meta']['notes'] = array_merge($out[0]['meta']['notes'] ?? [], $o['notes']);
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  بستن کار
     * ------------------------------------------------------------------- */

    /**
     * شماره‌گذاری نهایی، به‌همراه مهرِ ضریب کشسانی روی قطعه‌های پوسته.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finish(array $pieces): array
    {
        return parent::finish($this->stampStretch(array_values(array_filter($pieces))));
    }

    /**
     * یادداشت‌های همیشگی لباس خواب.
     *
     * @return array<int, string>
     */
    protected function sleepNotes(array $params, array $extra = []): array
    {
        if ($this->negativeEase) {
            $stretch = $this->stretchOf($params);

            $notes = [
                'الگو '.$this->fa(round((1 - $stretch) * 100)).' درصد کوچک‌تر از دور بدن بریده شده؛'
                    .' پارچهٔ کشی روی تن باز می‌شود و همین تنگی نمی‌گذارد لباس در خواب جمع شود.',
                'با نخ کشی (استرچ) و سوزن جرسی بدوزید؛ درز معمولی زیر کشیدن پارچه پاره می‌شود.',
            ];
        } else {
            $notes = [
                'این مدل از پارچهٔ بافته بریده می‌شود و آزادی مثبت دارد: کشش پارچه صفر است،'
                    .' پس هرچه لازم است در خواب بچرخد باید از آزادی بیاید نه از کش آمدن پارچه.',
                'پارچه را پیش از برش بشویید؛ فلانل و پوپلین لباس خواب در شست‌وشوی اول آب می‌روند.',
            ];
        }

        return array_merge($notes, $extra);
    }

    /**
     * یادداشت‌ها را روی قطعه‌ها می‌نشاند و کار را می‌بندد.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function finishSleepwear(array $pieces, array $notes): array
    {
        return $this->finish($this->noted(
            $pieces,
            array_map(fn (string $text) => ['type' => 'info', 'text' => $text], $notes),
        ));
    }
}
