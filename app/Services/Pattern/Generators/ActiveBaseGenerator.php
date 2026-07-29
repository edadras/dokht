<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Geometry;

/**
 * پایه مشترک بالاتنه‌های ورزشی.
 *
 * همهٔ بالاتنه‌های این دسته از همان bodyPanel آزمودهٔ کاتالوگ ساخته می‌شوند و
 * تفاوتشان در سه چیز است: علامت آزادی (منفی برای لایهٔ چسبان، مثبت برای گرمکن)،
 * شکل خط بالا (بند باریک یا سرشانهٔ کامل)، و اینکه لبه‌های باز با کش تمام می‌شوند
 * یا با نوار کشباف.
 *
 * قرارداد پارچه در ActiveFabric نوشته شده و این کلاس فقط آن را روی قطعه‌ها
 * می‌نشاند: هر مدل می‌گوید در کدام نیمه است ($negativeEase) و بستن کار،
 * meta.stretch_ratio را روی پنل‌های پوسته مهر می‌زند.
 */
abstract class ActiveBaseGenerator extends BodiceBaseGenerator
{
    use ActiveFabric, BuildsSleeve;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'active';
    }

    /** آیا این مدل کوچک‌تر از بدن بریده می‌شود؟ گرمکن‌ها نه. */
    protected bool $negativeEase = true;

    /** آخرین ضریب کشسانی خوانده‌شده؛ برای مهر زدن روی قطعه‌ها. */
    protected ?float $stretchRatio = null;

    /**
     * شماره‌گذاری نهایی، به‌همراه مهر ضریب کشسانی.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finish(array $pieces): array
    {
        if ($this->negativeEase && $this->stretchRatio !== null) {
            $pieces = $this->stampStretch($pieces, $this->stretchRatio, ['front_bodice', 'back_bodice']);
        }

        return parent::finish($pieces);
    }

    /**
     * ضریب کشسانی این ساخت را می‌خواند و نگه می‌دارد.
     */
    protected function readStretch(array $params, float $default = 0.88): float
    {
        return $this->stretchRatio = $this->activeStretch($params, $default);
    }

    /**
     * پارامترهای مشترک بالاتنهٔ ورزشی.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function activeSchema(array $defaults = [], array $extra = []): array
    {
        return array_merge(
            $this->baseSchema(array_merge([
                'shoulder_slope' => 4.5,
                'neck_width_extra' => 1,
                'front_neck_depth_extra' => 0,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 0,
            ], $defaults), [
                'shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra',
                'back_neck_depth', 'armhole_depth_extra',
            ]),
            $extra,
        );
    }

    /* ---------------------------------------------------------------------
     |  خط بالا و بند
     * ------------------------------------------------------------------- */

    /**
     * پهنای بندی که به‌جای سرشانهٔ کامل می‌نشیند.
     *
     * bodyPanel نوک سرشانه را از shoulder_half می‌گیرد؛ برای بند باریک باید همان
     * نوک به «عرض یقه + پهنای بند» کشیده شود، پس اختلاف را برمی‌گردانیم.
     *
     * @param  array<string, float>  $g
     */
    protected function strapShoulder(array $g, float $strap): float
    {
        return ($g['neck_width'] + max(1.5, $strap)) - $g['shoulder_half'];
    }

    /**
     * بلندی پنل طوری که لبهٔ پایین دقیقاً روی ارتفاع خواسته‌شده بنشیند.
     *
     * bodyPanel بلندی را از خط کمر می‌شمارد و عدد منفی یعنی بالاتر از کمر. لباس
     * ورزشیِ کوتاه (سوتین، کراپ) همیشه بالای کمر تمام می‌شود، ولی هرگز نباید آن‌قدر
     * بالا برود که به خط زیر بغل بچسبد؛ کف را از خودِ اندازه‌ها می‌گیریم.
     *
     * @param  array<string, float>  $g
     */
    protected function lengthToBottom(array $g, float $belowArmhole, float $clearance = 8.0): float
    {
        $bottom = (float) $g['bust_y'] + max($clearance, $belowArmhole);

        return min(0.0, $bottom - (float) $g['side_waist_y']);
    }

    /**
     * بلندتر کردن دم یک قطعه، فقط روی خط مرکز.
     *
     * «پشت بلندتر» را نباید با بلندکردن کل قطعه ساخت: آن‌وقت درز پهلوی پشت از درز
     * پهلوی جلو بلندتر می‌شود و دو لبه‌ای که به هم دوخته می‌شوند دیگر هم‌اندازه
     * نیستند. اضافه فقط روی نقطهٔ مرکز می‌نشیند و لبهٔ دم شیب می‌گیرد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function lengthenCenterHem(array $piece, float $extra): array
    {
        if ($extra < 0.1) {
            return $piece;
        }

        $outline = array_values($piece['outline']);
        $tags = Geometry::edgeTags($piece);
        $count = count($outline);
        $centre = null;
        $centreX = INF;

        for ($i = 0; $i < $count; $i++) {
            if (($tags[$i] ?? '') !== 'hem') {
                continue;
            }

            foreach ([$i, ($i + 1) % $count] as $index) {
                if ((float) $outline[$index]['x'] < $centreX) {
                    $centreX = (float) $outline[$index]['x'];
                    $centre = $index;
                }
            }
        }

        if ($centre === null) {
            return $piece;
        }

        $outline[$centre]['y'] = round(((float) $outline[$centre]['y']) + $extra, 2);

        if (isset($outline[$centre]['cy'])) {
            $outline[$centre]['cy'] = round(((float) $outline[$centre]['cy']) + ($extra * 0.55), 2);
        }

        $piece['outline'] = $outline;
        $piece['meta']['hem_step'] = round($extra, 2);

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  نوار و کش
     * ------------------------------------------------------------------- */

    /**
     * نوار زیرسینه یا کمر: کوتاه‌تر از دور تمام‌شده بریده می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function compressionBand(string $code, string $name, float $girth, float $height, float $ratio, array $o = []): array
    {
        $cut = max(10.0, $girth * $ratio);

        return $this->bandPiece($code, $name, $cut, $height * 2, array_merge([
            'cut' => 1,
            'fold_line' => true,
            'part' => 'band',
            'meta' => [
                'girth_role' => 'trim',
                'band_girth' => round($cut, 2),
                'band_target' => round($girth, 2),
                'band_stretch' => round($ratio, 3),
                'finished_height' => round($height, 2),
                'notions' => [[
                    'type' => 'elastic',
                    'label' => $name,
                    'count' => 1,
                    'length' => round($cut, 1),
                ]],
                'notes' => [
                    'نوار '.$this->fa(round($cut, 1)).' سانتی‌متر بریده و روی '.$this->fa(round($girth, 1))
                        .' سانتی‌متر کشیده و دوخته می‌شود؛ همهٔ وزن لباس روی همین نوار می‌افتد.',
                ],
            ],
        ], $o));
    }

    /**
     * نوار اریب یا کشباف لبه (یقه و حلقه).
     *
     * @return array<string, mixed>
     */
    protected function edgeBinding(string $code, string $name, float $target, float $height, float $ratio, int $cut = 1): array
    {
        return $this->bandPiece($code, $name, max(8.0, $target * $ratio), $height * 2, [
            'cut' => $cut,
            'fold_line' => true,
            'part' => 'binding',
            'meta' => [
                'girth_role' => 'trim',
                'rib' => true,
                'stretch' => round($ratio, 3),
                'target_length' => round($target, 2),
                'notes' => [
                    'نوار '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از لبه بریده و کشیده دوخته می‌شود.',
                ],
            ],
        ]);
    }

    /**
     * طول یک برچسب لبه روی چند قطعه، با احتساب تای پارچه و تعداد برش.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function edgeTotal(array $pieces, string $tag, array $parts = ['front_bodice', 'back_bodice']): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if ($parts !== [] && ! in_array((string) ($piece['meta']['part'] ?? ''), $parts, true)) {
                continue;
            }

            $length = 0.0;

            foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
                $length += Geometry::edgeLength($piece['outline'], $edge);
            }

            $total += $length * (empty($piece['on_fold']) ? 1 : 2) * max(1, (int) ($piece['cut_quantity'] ?? 1));
        }

        return round($total, 2);
    }

    /**
     * آستر یک قطعه (لایهٔ دوم سوتین ورزشی).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function innerLayer(array $piece, string $name): array
    {
        $piece['code'] = ($piece['code'] ?? 'piece').'-inner';
        $piece['name'] = $name;
        $piece['layer'] = 'lining';
        $piece['meta']['part'] = 'lining';
        $piece['meta']['girth_role'] = 'lining';
        $piece['meta']['notes'] = ['هم‌اندازهٔ لایهٔ رو؛ دو لایه با هم بریده و در لبه‌ها با هم دوخته می‌شوند.'];
        unset($piece['meta']['stretch_ratio'], $piece['meta']['notions']);

        return $piece;
    }
}
