<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Generators\Concerns\DraftsBodice;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\StyleLineCutter;

/**
 * پایه مشترک پیراهن‌ها و تی‌شرت‌ها.
 *
 * همهٔ این خانواده یک اسکلت دارند — تنهٔ جلو، تنهٔ پشت، آستین و یک‌جور تمام‌کردن
 * یقه — و تفاوتشان در چهار چیز است:
 *
 *   بسته شدن   دکمهٔ سراسری، نیمه‌دکمه، یا اصلاً بی‌دکمه (سرخود)
 *   یقه        یقهٔ پیراهنی، یقهٔ ایستاده، یقهٔ باز (کمپ)، یا فقط نوار کشباف
 *   سرشانه     سر جای خودش، یا افتاده روی بازو (اورسایز و باکسی)
 *   فرم        راسته، جذب، یا جعبه‌ای
 *
 * برای همین این کلاس بلوک نمی‌سازد؛ همان bodicePiece آزمودهٔ DraftsBodice را
 * می‌گیرد و این چهار چیز را رویش سوار می‌کند. هر مدل تازه فقط می‌گوید کدام
 * ترکیب را می‌خواهد.
 *
 * یک قاعده در همهٔ این مدل‌ها رعایت شده: سرشانهٔ افتاده حلقهٔ آستین را هم عوض
 * می‌کند. اگر فقط سرشانه بلندتر شود و حلقه دست‌نخورده بماند، آستین در حلقه
 * نمی‌نشیند؛ پس هرجا سرشانه می‌افتد، حلقه هم به همان نسبت گودتر می‌شود.
 */
abstract class ShirtBaseGenerator extends BaseGenerator
{
    use BuildsSleeve, DraftsBodice;

    /** گروه فهرست مدل‌ها؛ پیراهن و تی‌شرت یک خانواده‌اند. */
    public static function group(): string
    {
        return 'shirt';
    }

    /* ---------------------------------------------------------------------
     |  پارامترها
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک این خانواده.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function shirtSchema(array $defaults = [], array $extra = []): array
    {
        $schema = [
            'fit' => [
                'label' => 'فرم لباس', 'type' => 'select', 'default' => 'regular',
                'options' => ['fitted' => 'جذب', 'regular' => 'معمولی', 'loose' => 'گشاد', 'boxy' => 'جعبه‌ای (اورسایز)'],
            ],
            'body_length' => [
                'label' => 'بلندی تنه از کمر', 'min' => -6, 'max' => 50, 'step' => 1,
                'default' => 18, 'unit' => 'سانتی‌متر',
            ],
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4.5, 'unit' => 'سانتی‌متر',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 1, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => -2, 'max' => 15, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 6, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => 0, 'max' => 10, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'drop_shoulder' => [
                'label' => 'افتادن سرشانه روی بازو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                'default' => 0, 'unit' => 'سانتی‌متر',
                'hint' => 'حلقهٔ آستین هم به همان اندازه گودتر می‌شود، وگرنه آستین در حلقه نمی‌نشیند.',
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین', 'min' => 8, 'max' => 72, 'step' => 1,
                'default' => 24, 'unit' => 'سانتی‌متر',
            ],
            'cap_ease' => [
                'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 5, 'step' => 0.25, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (isset($schema[$key])) {
                $schema[$key]['default'] = $value;
            }
        }

        return array_merge($schema, $extra);
    }

    /** پارامتر جیب سینه. */
    protected function pocketParam(bool $default = false, string $label = 'جیب سینه'): array
    {
        return ['chest_pocket' => ['label' => $label, 'type' => 'toggle', 'default' => $default]];
    }

    /* ---------------------------------------------------------------------
     |  تنه
     * ------------------------------------------------------------------- */

    /**
     * تنهٔ جلو و پشت، با همهٔ تصمیم‌های مشترک این خانواده.
     *
     * $o: extension (اضافهٔ جای دکمه)، yoke (بلندی یوک پشت یا صفر)، prefix،
     * front_name، back_name، bust_dart.
     *
     * @param  array<string, float>  $g
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<int, array<string, mixed>>}
     */
    protected function shirtBody(array $g, array $params, array $o = []): array
    {
        $fit = (string) $this->param($params, 'fit', 'regular');
        $fitted = $fit === 'fitted';
        $length = (float) $this->param($params, 'body_length', 18);
        $bottom = max($g['front_waist_y'], $g['back_waist_y']) + $length;
        $extension = (float) ($o['extension'] ?? 0);
        $prefix = (string) ($o['prefix'] ?? 'shirt');
        $yokeDepth = (float) ($o['yoke'] ?? 0);

        $shared = [
            'shape' => $fitted ? 'dress' : 'straight',
            'darts' => $fitted,
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
        ];

        $front = $this->bodicePiece($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $extension,
            'on_fold' => $extension <= 0.01,
            'cut' => $extension > 0.01 ? 2 : 1,
            'mirror' => $extension > 0.01,
            'code' => $prefix.'-front',
            'name' => $o['front_name'] ?? 'تنه جلو',
            'meta' => $extension > 0.01 ? ['button_stand' => $extension] : [],
        ]));

        $back = $this->bodicePiece($g, array_merge($shared, [
            'side' => 'back',
            'code' => $prefix.'-back',
            'name' => $o['back_name'] ?? 'تنه پشت',
        ]));

        $extras = [];

        if ($yokeDepth > 0.5) {
            [$back, $yoke] = $this->splitYoke($back, $yokeDepth, (bool) ($o['pointed_yoke'] ?? false), $prefix);
            $extras[] = $yoke;
        }

        return [$front, $back, $extras];
    }

    /**
     * بریدن یوک از بالای تنهٔ پشت.
     *
     * یوک با برشِ همان موتور سبک‌ها جدا می‌شود، پس برچسب لبه‌ها، نشانه‌ها و تای
     * پارچه خودشان درست می‌مانند و لازم نیست دوباره درفت شود.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function splitYoke(array $back, float $depth, bool $pointed, string $prefix): array
    {
        [$minX, $minY, $maxX] = Geometry::bounds($back['outline']);

        $path = [['x' => $minX - 1.5, 'y' => $minY + $depth]];

        if ($pointed) {
            // یوک وسترن: خط یوک از مرکز پشت به سمت پهلو نوک پیدا می‌کند
            $path[] = ['x' => $minX + (($maxX - $minX) * 0.55), 'y' => $minY + $depth + 5.0];
        }

        $path[] = ['x' => $maxX + 1.5, 'y' => $minY + $depth];

        try {
            $halves = StyleLineCutter::cut($back, $path, [
                'tag' => 'default',
                'codes' => [$prefix.'-yoke', $back['code']],
                'names' => ['یوک پشت', $back['name']],
                'pair' => 'yoke',
            ]);
        } catch (\InvalidArgumentException) {
            return [$back, []];
        }

        usort($halves, fn ($a, $b) => Geometry::centroid($a['outline'])['y'] <=> Geometry::centroid($b['outline'])['y']);

        $yoke = $halves[0];
        $body = $halves[1];

        $yoke['code'] = $prefix.'-yoke';
        $yoke['name'] = 'یوک پشت';
        $yoke['cut_quantity'] = 2;
        $yoke['meta']['part'] = 'yoke';
        $yoke['meta']['notes'][] = 'یوک دو لا بریده می‌شود؛ لایهٔ داخلی سرشانه‌ها را می‌پوشاند.';

        $body['code'] = $back['code'];
        $body['name'] = $back['name'];
        $body['meta']['part'] = 'back_bodice';

        // هر دو نیمه هنوز meta قطعهٔ کامل را با خود دارند: طول حلقهٔ آستین و
        // خط‌های نشانه مال قطعه‌ای‌اند که دیگر وجود ندارد. اگر پاک نشوند، حلقه
        // دو بار شمرده می‌شود و آستین به اندازهٔ یک حلقهٔ اضافه بزرگ درفت می‌شود.
        return [$this->refreshAfterCut($body), $this->refreshAfterCut($yoke)];
    }

    /**
     * تازه‌کردن meta یک قطعه پس از بریده شدن.
     *
     * سه کار: طول حلقهٔ آستین از روی لبه‌های واقعی همین قطعه دوباره حساب
     * می‌شود، طول یقه هم همین‌طور، و هر خط نشانه‌ای که دیگر روی قطعه نیست
     * برداشته می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function refreshAfterCut(array $piece): array
    {
        $outline = $piece['outline'] ?? [];

        if (count($outline) < 3) {
            return $piece;
        }

        foreach (['armhole' => 'armhole_length', 'neck' => 'neck_length'] as $tag => $key) {
            $edges = Geometry::edgesWithTag($piece, $tag);
            $length = 0.0;

            foreach ($edges as $edge) {
                $length += Geometry::edgeLength($outline, $edge);
            }

            $piece['meta'][$key] = round($length, 2);

            if ($tag === 'armhole') {
                $piece['meta']['armhole_edge'] = $edges === [] ? null : $edges[count($edges) - 1];
            }
        }

        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);
        $inside = fn (?array $point) => $point !== null
            && isset($point['x'], $point['y'])
            && $point['x'] >= $minX - 0.1 && $point['x'] <= $maxX + 0.1
            && $point['y'] >= $minY - 0.1 && $point['y'] <= $maxY + 0.1;

        $piece['markers'] = array_values(array_filter(
            $piece['markers'] ?? [],
            fn (array $marker) => $inside($marker['from'] ?? null) && $inside($marker['to'] ?? null),
        ));

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  یقه‌ها
     * ------------------------------------------------------------------- */

    /** یقهٔ پیراهنی با پایه (سرِ یقه روی پایه می‌خوابد). */
    protected function shirtCollar(float $halfNeck, float $height, string $code = 'collar', string $name = 'یقه'): array
    {
        $length = max(10.0, $halfNeck);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($length, 0.8),
            Geometry::point($length + 2.0, $height),
            Geometry::curve(0, $height, $length * 0.5, $height - 1.2),
        ];

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 1.5, $height - 1),
            'notches' => [$this->notch(0, $height, 2, 'مرکز پشت یقه', 'collar_center')],
            'markers' => [$this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $height)],
            'meta' => [
                'part' => 'collar',
                /*
                 * لبهٔ ۰ همان خطی است که به یقهٔ لباس دوخته می‌شود و لبهٔ ۲ لبهٔ
                 * بیرونیِ آزادِ یقه. جای این دو عوض بود، پس نمای سه‌بعدی یقه را
                 * از لبهٔ بیرونی به خط یقه می‌دوخت: ۲۷٫۸ سانتی‌متر روی ۲۴، یقه
                 * وارونه و چین‌خورده دور گردن. روی مانکن مثل پارچهٔ پاره دیده می‌شد.
                 */
                'edges' => ['neck', 'side', 'default', 'default'],
                'fold_edges' => [3],
                'neck_length' => Geometry::edgeLength($outline, 0),
                'interfacing' => true,
            ],
        ]);
    }

    /**
     * یقهٔ ایستاده (گِرِدداد / مائو).
     *
     * لبهٔ بالای یقهٔ ایستاده باید کمی کوتاه‌تر از لبهٔ پایین باشد، وگرنه یقه از
     * گردن فاصله می‌گیرد و باز می‌ماند. همان اختلاف کوچک است که یقه را دور گردن
     * جمع می‌کند.
     */
    protected function bandCollar(float $halfNeck, float $height, float $overlap = 1.75): array
    {
        $length = max(8.0, $halfNeck) + $overlap;

        $outline = [
            Geometry::point(0, 0),
            Geometry::curve($length, -1.0, $length * 0.6, -0.5),
            Geometry::point($length - 0.6, -$height),
            Geometry::curve(0, -$height, $length * 0.5, -$height - 0.5),
        ];

        return $this->piece([
            'code' => 'band-collar',
            'name' => 'یقه ایستاده',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, -$height + 0.8, -0.8),
            'notches' => [$this->notch(0, 0, 3, 'مرکز پشت یقه', 'collar_center')],
            'meta' => [
                'part' => 'collar',
                'edges' => ['neck', 'side', 'default', 'default'],
                'fold_edges' => [3],
                'neck_length' => Geometry::edgeLength($outline, 0),
                'interfacing' => true,
                'notes' => ['لبهٔ بالای یقه کمی کوتاه‌تر است تا یقه دور گردن جمع شود و باز نماند.'],
            ],
        ]);
    }

    /**
     * یقهٔ باز و تخت (کمپ‌کالر / هاوایی).
     *
     * این یقه پایه ندارد و روی شانه می‌خوابد؛ برای همین لبهٔ گردنی‌اش باید
     * منحنی‌تر از یقهٔ پیراهنی باشد، وگرنه پشت گردن بالا می‌زند.
     */
    protected function campCollar(float $halfNeck, float $height, float $point = 3.0): array
    {
        $length = max(10.0, $halfNeck);

        $outline = [
            Geometry::point(0, 0),
            Geometry::curve($length, 1.6, $length * 0.55, 0.2),
            Geometry::point($length + $point, $height * 0.75),
            Geometry::curve(0, $height, $length * 0.55, $height + 0.8),
        ];

        return $this->piece([
            'code' => 'camp-collar',
            'name' => 'یقه باز (کمپ)',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 1.5, $height - 1),
            'notches' => [$this->notch(0, 0, 3, 'مرکز پشت یقه', 'collar_center')],
            'meta' => [
                'part' => 'collar',
                'edges' => ['neck', 'side', 'default', 'default'],
                'fold_edges' => [3],
                'neck_length' => Geometry::edgeLength($outline, 0),
                'interfacing' => true,
                'notes' => ['یقهٔ باز پایه ندارد و روی شانه می‌خوابد؛ سرِ یقه با تنه یک‌سره دوخته می‌شود.'],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  نوار، پاتلت و جیب
     * ------------------------------------------------------------------- */

    /** نوار کشباف (یقه، مچ، دم لباس). */
    protected function ribBand(float $target, float $height, string $code, string $name, float $stretch = 0.85, int $cut = 1): array
    {
        $length = max(8.0, $target * $stretch);

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => $cut,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(round($length, 2), 0),
                Geometry::point(round($length, 2), $height * 2),
                Geometry::point(0, $height * 2),
            ],
            'grainline' => $this->grainline($length * 0.5, 0.8, ($height * 2) - 0.8),
            'markers' => [$this->marker('fold', 'خط تای نوار', 0, $height, round($length, 2))],
            'meta' => [
                'part' => 'collar',
                'edges' => ['neck', 'side', 'default', 'side'],
                'fold_edges' => [],
                'rib' => true,
                'stretch' => $stretch,
                'target_length' => round($target, 2),
                'notes' => ['نوار '.$this->fa(round((1 - $stretch) * 100)).' درصد کوتاه‌تر از لبه بریده شده و کشیده دوخته می‌شود.'],
            ],
        ]);
    }

    /**
     * پاتلت جای دکمه، با جای دکمه‌های شمرده‌شده.
     *
     * فاصلهٔ دکمه‌ها روی پیراهن حدود هشت سانتی‌متر است؛ تعداد از همین درمی‌آید
     * تا صورت مواد عدد واقعی همین الگو را بدهد، نه عددی ثابت.
     */
    protected function placket(float $stand, float $length, string $code = 'placket', string $name = 'پاتلت جای دکمه', float $spacing = 8.0): array
    {
        $width = max(1.5, $stand * 2);
        $buttons = max(2, (int) round(($length - 6) / max(3.0, $spacing)));
        $drills = [];
        $step = ($length - 6) / max(1, $buttons - 1);

        for ($i = 0; $i < $buttons; $i++) {
            $drills[] = [
                'key' => 'button_'.($i + 1),
                'label' => 'جای دکمه '.($i + 1),
                'x' => round($width / 2, 2),
                'y' => round(3 + ($step * $i), 2),
            ];
        }

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $length),
                Geometry::point(0, $length),
            ],
            'grainline' => $this->grainline($width * 0.5, 2, $length - 2),
            'drills' => $drills,
            'meta' => [
                'part' => 'placket',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'button_count' => $buttons,
                'notions' => [['type' => 'button', 'label' => 'دکمهٔ جلو', 'count' => $buttons]],
            ],
        ]);
    }

    /** جیب رودوزی ساده یا با درپوش. */
    protected function patchPocket(float $width, float $height, array $o = []): array
    {
        $radius = (float) ($o['radius'] ?? 0);
        $outline = $radius > 0.5
            ? [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height - $radius),
                Geometry::curve($width - $radius, $height, $width, $height),
                Geometry::point($radius, $height),
                Geometry::curve(0, $height - $radius, 0, $height),
            ]
            : [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height),
                Geometry::point(0, $height),
            ];

        return $this->piece([
            'code' => $o['code'] ?? 'chest-pocket',
            'name' => $o['name'] ?? 'جیب سینه',
            'cut_quantity' => (int) ($o['cut'] ?? 1),
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 1.5, $height - 1.5),
            'meta' => [
                'part' => 'pocket',
                'edges' => array_fill(0, count($outline), 'default'),
                'fold_edges' => [],
                'notes' => array_merge(['لبهٔ بالای جیب سه سانتی‌متر برگردان دارد.'], $o['notes'] ?? []),
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  آستین
     * ------------------------------------------------------------------- */

    /**
     * آستین این پیراهن، با حلقهٔ واقعی همین قطعه‌ها.
     *
     * @param  array<int, array<string, mixed>>  $bodyPieces
     * @return array<int, array<string, mixed>>
     */
    protected function shirtSleeves(array $measurements, array $ease, array $params, array $bodyPieces, array $o = []): array
    {
        $armhole = 0.0;

        foreach ($bodyPieces as $piece) {
            $armhole += (float) ($piece['meta']['armhole_length'] ?? 0);
        }

        return $this->sleevePieces($measurements, $ease, $params, array_merge([
            'armhole_length' => max(30.0, $armhole),
            'length' => (float) $this->param($params, 'sleeve_length', 24),
            'sleeve_name' => 'آستین',
        ], $o));
    }

    /* ---------------------------------------------------------------------
     |  کمک‌های کوچک
     * ------------------------------------------------------------------- */

    /** آزادی بر پایهٔ فرم انتخابی. */
    protected function shirtEase(array $ease, array $params): array
    {
        $extra = match ((string) $this->param($params, 'fit', 'regular')) {
            'fitted' => 0.0,
            'loose' => 6.0,
            'boxy' => 14.0,
            default => 3.0,
        };

        return array_merge($ease, [
            'bust' => $this->ease($ease, 'bust', 8) + $extra,
            'waist' => $this->ease($ease, 'waist', 8) + $extra,
            'hip' => $this->ease($ease, 'hip', 8) + $extra,
        ]);
    }

    /**
     * افتادن سرشانه: حلقه هم به همان نسبت گودتر می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function withDropShoulder(array $params): array
    {
        $drop = (float) $this->param($params, 'drop_shoulder', 0);

        if ($drop < 0.1) {
            return $params;
        }

        $params['armhole_depth_extra'] = (float) $this->param($params, 'armhole_depth_extra', 2.5) + ($drop * 0.8);

        return $params;
    }

    /** یادداشت روی قطعهٔ اول. */
    protected function noteOn(array $pieces, array $notes): array
    {
        $pieces = array_values($pieces);

        if ($pieces === []) {
            return $pieces;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $pieces;
    }

    protected function fa(float|int|string $value): string
    {
        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫',
        ]);
    }
}
