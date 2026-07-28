<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه مشترک لباس‌های کامل که از بلوک بالاتنه ساخته می‌شوند.
 *
 * هر لباس کامل از قطعه‌های واقعی سرِ هم می‌شود: تنه (بالاتنه یا بالاتنه + دامن)،
 * آستین، یقه، سجاف، آستر و متعلقات. این کلاس همان کارهایی را انجام می‌دهد که یک
 * خیاط پیش از برش تکرار می‌کند:
 *
 *   ۱. حلقه آستین را از روی خودِ پنل‌های تنه اندازه می‌گیرد و آستین را با همان
 *      عدد درفت می‌کند (نه با عددی حدسی).
 *   ۲. طول درز پهلوی جلو و پشت را «راه می‌برد» و اگر اختلاف داشت گزارش می‌دهد.
 *   ۳. سجاف جلو، سجاف یقه پشت و آستر را فقط جایی می‌سازد که خیاط واقعاً می‌سازد.
 *   ۴. هر گشادی اضافه (چین، پیلی، هم‌پوشانی) روی meta.fullness ثبت می‌شود.
 */
abstract class BodiceGarmentBase extends BodiceBaseGenerator
{
    use BodiceGarmentSupport;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'garment';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * بلندی لباس از خط کمر به پایین.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function garmentLengthParam(float $default = 60, float $min = 10, float $max = 130, string $label = 'بلندی از خط کمر'): array
    {
        return [
            'length' => [
                'label' => $label, 'min' => $min, 'max' => $max, 'step' => 1,
                'default' => $default, 'unit' => 'سانتی‌متر',
                'hint' => 'از خط کمر تا لبه پایین، روی درز پهلو.',
            ],
        ];
    }

    /**
     * پارامترهای درفت تنه برای لباس‌های رویی (با آزادی بیشتر روی حلقه و یقه).
     *
     * @param  array<string, float>  $defaults
     * @return array<string, array<string, mixed>>
     */
    protected function outerSchema(array $defaults = []): array
    {
        return $this->baseSchema(array_merge([
            'shoulder_slope' => 4,
            'neck_width_extra' => 1.5,
            'front_neck_depth_extra' => 2,
            'back_neck_depth' => 2.5,
            'armhole_depth_extra' => 3,
            'waist_dart_share' => 0.5,
        ], $defaults), [
            'shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra',
            'back_neck_depth', 'armhole_depth_extra', 'waist_dart_share',
        ]);
    }

    /**
     * یقه.
     *
     * @param  array<string, string>  $options
     * @return array<string, array<string, mixed>>
     */
    protected function collarParam(string $default = 'stand', array $options = [], float $height = 6): array
    {
        $options = $options !== [] ? $options : [
            'none' => 'بدون یقه (لبه تمیزدوزی)',
            'stand' => 'یقه ایستاده',
            'turn' => 'یقه برگردان',
            'shawl' => 'یقه شالی',
        ];

        return [
            'collar' => [
                'label' => 'یقه', 'type' => 'select', 'default' => $default, 'options' => $options,
            ],
            'collar_height' => [
                'label' => 'بلندی یقه', 'min' => 2, 'max' => 14, 'step' => 0.5,
                'default' => $height, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /**
     * سبک آستین.
     *
     * @param  array<string, string>  $options
     * @return array<string, array<string, mixed>>
     */
    protected function sleeveParam(string $default = 'set_in', float $lengthDefault = 58, array $options = []): array
    {
        $options = $options !== [] ? $options : [
            'none' => 'بدون آستین',
            'set_in' => 'آستین معمولی (حلقه‌ای)',
            'two_piece' => 'آستین دوتکه خیاطی',
            'raglan' => 'آستین رگلان',
        ];

        return [
            'sleeve_style' => [
                'label' => 'آستین', 'type' => 'select', 'default' => $default, 'options' => $options,
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین', 'min' => 0, 'max' => 75, 'step' => 1,
                'default' => $lengthDefault, 'unit' => 'سانتی‌متر',
                'hint' => 'از نوک سرشانه تا دم آستین.',
            ],
            'cap_ease' => [
                'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 6, 'step' => 0.25,
                'default' => 2, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /**
     * جلوی لباس: باز با اضافه دکمه یا بسته.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function openingParam(string $default = 'button', float $stand = 2.5, array $options = []): array
    {
        $options = $options !== [] ? $options : [
            'closed' => 'جلو بسته (روی تای پارچه)',
            'button' => 'جلوباز با دکمه',
            'zip' => 'جلوباز با زیپ',
            'open' => 'جلوباز بدون بست',
        ];

        return [
            'front_opening' => [
                'label' => 'جلوی لباس', 'type' => 'select', 'default' => $default, 'options' => $options,
            ],
            'button_stand' => [
                'label' => 'اضافه جای دکمه', 'min' => 0, 'max' => 12, 'step' => 0.5,
                'default' => $stand, 'unit' => 'سانتی‌متر',
                'hint' => 'پهنای نواری که از خط مرکز جلو بیرون می‌زند.',
            ],
            'buttons' => [
                'label' => 'تعداد دکمه', 'min' => 0, 'max' => 14, 'step' => 1, 'default' => 5,
            ],
        ];
    }

    /**
     * آستر و لایی.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function liningParam(bool $default = false): array
    {
        return [
            'lining' => [
                'label' => 'آستر داشته باشد', 'type' => 'toggle', 'default' => $default,
                'hint' => 'قطعه‌های آستر با کمی آزادی بیشتر و پیلی راحتی مرکز پشت ساخته می‌شود.',
            ],
        ];
    }

    /**
     * جیب.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function pocketParam(bool $default = true, float $width = 14, float $height = 16): array
    {
        return [
            'pocket' => [
                'label' => 'جیب رودوزی', 'type' => 'toggle', 'default' => $default,
            ],
            'pocket_width' => [
                'label' => 'پهنای جیب', 'min' => 8, 'max' => 24, 'step' => 0.5,
                'default' => $width, 'unit' => 'سانتی‌متر',
            ],
            'pocket_height' => [
                'label' => 'بلندی جیب', 'min' => 8, 'max' => 26, 'step' => 0.5,
                'default' => $height, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  ساخت تنه
     * ------------------------------------------------------------------- */

    /**
     * جفت پنل تنه (جلو و پشت) با گزینه‌های مشترک.
     *
     * جلو اگر «اضافه جای دکمه» داشته باشد روی تای پارچه نمی‌رود و دوتایی بریده
     * می‌شود؛ پشت به‌جز وقتی زیپ یا درز مرکزی دارد روی تا می‌ماند.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $shared
     * @return array<int, array<string, mixed>>
     */
    protected function shellPair(array $g, array $shared, array $front = [], array $back = []): array
    {
        return [
            $this->bodyPanel($g, array_merge($shared, ['side' => 'front'], $front)),
            $this->bodyPanel($g, array_merge($shared, ['side' => 'back'], $back)),
        ];
    }

    /** طول کل حلقه آستین (جلو + پشت) از روی خودِ پنل‌ها. */
    protected function armholeOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $total += (float) ($piece['meta']['armhole_length'] ?? 0);
        }

        return round($total, 2);
    }

    /** طول یقه نیم‌قطعه (جلو + پشت) از روی خودِ پنل‌ها. */
    protected function neckOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $total += (float) ($piece['meta']['neck_length'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * درز پهلوی جلو و پشت را می‌سنجد و اختلاف را روی هر دو قطعه ثبت می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function walkSideSeams(array $front, array $back): array
    {
        $a = (float) ($front['meta']['side_seam_length'] ?? 0);
        $b = (float) ($back['meta']['side_seam_length'] ?? 0);
        $delta = round($a - $b, 2);

        $front['meta']['side_seam_match'] = $delta;
        $back['meta']['side_seam_match'] = -$delta;

        if (abs($delta) > 0.1) {
            $note = 'اختلاف درز پهلوی جلو و پشت '.$this->fa(abs($delta)).' سانتی‌متر است؛ هنگام دوخت روی همین درز پخش می‌شود.';
            $front['meta']['notes'] = array_merge($front['meta']['notes'] ?? [], [$note]);
            $back['meta']['notes'] = array_merge($back['meta']['notes'] ?? [], [$note]);
        }

        return [$front, $back];
    }

    /* ---------------------------------------------------------------------
     |  آستین
     * ------------------------------------------------------------------- */

    /**
     * قطعه‌های آستین بر پایه سبک انتخابی کاربر.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function sleeveSet(array $m, array $ease, array $params, float $armhole, array $g, array $o = []): array
    {
        $style = (string) ($o['style'] ?? $this->param($params, 'sleeve_style', 'set_in'));
        $length = (float) ($o['length'] ?? $this->param($params, 'sleeve_length', 58));

        if ($style === 'none' || $length < 4) {
            return [];
        }

        $pieces = match ($style) {
            'two_piece' => $this->tailoredSleevePieces($m, $ease, $params, $armhole, array_merge([
                'length_extra' => $length - $this->m($m, 'arm_length', 58),
            ], $o)),
            'raglan' => $this->raglanSleevePieces($m, $ease, $params, $g, array_merge([
                'length' => $length,
            ], $o)),
            default => $this->sleevePieces($m, $ease, $params, array_merge([
                'armhole_length' => $armhole,
                'length' => $length,
                'no_cuff' => ! $this->flag($params, 'cuff', false),
            ], $o)),
        };

        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['girth_role'] = $piece['meta']['girth_role'] ?? 'sleeve';
            $pieces[$index]['meta']['sleeve_style'] = $style;
        }

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  بست جلو، سجاف و آستر
     * ------------------------------------------------------------------- */

    /** اضافه جای دکمه؛ برای جلوی بسته صفر است. */
    protected function buttonStand(array $params, float $default = 2.5): float
    {
        $opening = (string) $this->param($params, 'front_opening', 'button');

        if ($opening === 'closed') {
            return 0.0;
        }

        return max(0.0, (float) $this->param($params, 'button_stand', $default));
    }

    /**
     * نشانه‌های دکمه روی خط مرکز جلو.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markButtons(array $piece, float $centerX, float $fromY, float $toY, int $count, string $label = 'جای دکمه'): array
    {
        if ($count < 1 || $toY - $fromY < 2) {
            return $piece;
        }

        $step = ($toY - $fromY) / max(1, $count - 1 ?: 1);

        for ($i = 0; $i < $count; $i++) {
            $y = $count === 1 ? $fromY : $fromY + ($step * $i);
            $piece['drills'][] = [
                'key' => 'button_'.($i + 1),
                'label' => $label.' '.$this->fa($i + 1),
                'x' => round($centerX, 2),
                'y' => round($y, 2),
            ];
        }

        $piece['meta']['buttons'] = $count;

        return $piece;
    }

    /**
     * سجاف جلو و سجاف یقه پشت برای لباس جلوباز.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function facingSet(array $g, float $stand, float $bottom, array $o = []): array
    {
        return [
            $this->frontFacingPiece($g, $stand, $bottom, $o),
            $this->backNeckFacingPiece($g, $o),
        ];
    }

    /**
     * نوار حلقه آستین (جلیقه و تاپ): نواری اریب هم‌اندازه حلقه.
     *
     * @return array<string, mixed>
     */
    protected function armholeBindingPiece(float $armhole, array $o = []): array
    {
        return $this->bandPiece(
            ($o['prefix'] ?? '').'armhole-binding',
            $o['name'] ?? 'نوار حلقه آستین',
            max(12.0, $armhole),
            (float) ($o['height'] ?? 3.5),
            ['cut' => 2, 'part' => 'facing', 'fold_line' => true, 'meta' => [
                'bias' => true,
                'target_armhole' => round($armhole, 2),
                'notes' => ['این نوار اریب بریده می‌شود تا روی منحنی حلقه بخوابد.'],
            ]],
        );
    }

    /**
     * نوار یقه (پارچه کشی): کوتاه‌تر از دور یقه تا روی گردن بخوابد.
     *
     * @return array<string, mixed>
     */
    protected function neckBandPiece(float $halfNeck, array $o = []): array
    {
        $ratio = max(0.7, min(1.0, (float) ($o['ratio'] ?? 0.85)));

        return $this->bandPiece(
            ($o['prefix'] ?? '').'neck-band',
            $o['name'] ?? 'نوار یقه',
            max(8.0, $halfNeck * $ratio),
            (float) ($o['height'] ?? 4),
            ['cut' => 1, 'on_fold' => true, 'part' => 'collar', 'fold_line' => true, 'meta' => [
                'stretch_ratio' => $ratio,
                'target_neck' => round($halfNeck, 2),
                'notes' => ['نوار یقه '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از دور یقه است و کشیده دوخته می‌شود.'],
            ]],
        );
    }

    /**
     * نوار کشباف دم لباس یا مچ (بمبر، هودی، سویشرت).
     *
     * @return array<string, mixed>
     */
    protected function ribBandPiece(string $code, string $name, float $bodyWidth, array $o = []): array
    {
        $ratio = max(0.6, min(1.0, (float) ($o['ratio'] ?? 0.85)));
        $height = (float) ($o['height'] ?? 6);

        $band = $this->bandPiece(
            $code,
            $name,
            max(6.0, $bodyWidth * $ratio),
            $height * 2,
            [
                'cut' => (int) ($o['cut'] ?? 1),
                'on_fold' => (bool) ($o['on_fold'] ?? true),
                'part' => $o['part'] ?? 'waistband',
                'fold_line' => true,
                'meta' => [
                    'rib' => true,
                    'stretch_ratio' => $ratio,
                    'target_width' => round($bodyWidth, 2),
                    'notes' => ['نوار کشباف '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از لبه لباس است و کشیده دوخته می‌شود.'],
                ],
            ],
        );

        $band['meta']['fullness'] = ['stretch' => round($bodyWidth - ($bodyWidth * $ratio), 2)];

        return $band;
    }

    /**
     * کمربند پارچه‌ای با دو سر گره‌شونده.
     *
     * @return array<string, mixed>
     */
    protected function beltPiece(array $m, array $params, array $o = []): array
    {
        $waist = $this->m($m, 'waist', 74);
        $width = (float) ($o['width'] ?? $this->param($params, 'belt_width', 4));
        $tie = (float) ($o['tie'] ?? 55);

        $belt = $this->bandPiece(
            ($o['prefix'] ?? '').'belt',
            $o['name'] ?? 'کمربند',
            ($waist / 2) + $tie,
            $width * 2,
            ['cut' => 2, 'part' => 'belt', 'fold_line' => true, 'meta' => [
                'belt_width' => round($width, 2),
                'notes' => ['دو تکه کمربند از وسط پشت به هم دوخته می‌شود.'],
            ]],
        );

        return $belt;
    }

    /**
     * حلقه کمربند.
     *
     * @return array<string, mixed>
     */
    protected function beltLoopPiece(float $width, array $o = []): array
    {
        return $this->bandPiece(
            ($o['prefix'] ?? '').'belt-loop',
            'حلقه کمربند',
            $width + 3,
            3.0,
            ['cut' => (int) ($o['cut'] ?? 2), 'part' => 'belt', 'meta' => [
                'notes' => ['روی درز پهلو، هم‌تراز خط کمر دوخته می‌شود.'],
            ]],
        );
    }

    /**
     * جیب کانگورویی هودی: یک قطعه با دو دهانه اریب.
     *
     * @return array<string, mixed>
     */
    protected function kangarooPocketPiece(float $width, float $height, array $o = []): array
    {
        $opening = (float) ($o['opening'] ?? 12);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($width, 0),
            Geometry::point($width, $height - $opening),
            Geometry::curve($width - $opening, $height, $width - ($opening * 0.35), $height - ($opening * 0.3)),
            Geometry::point(0, $height),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'kangaroo-pocket',
            'name' => $o['name'] ?? 'جیب کانگورویی',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 2, $height - 2),
            'markers' => [
                $this->marker('opening', 'دهانه جیب', $width, $height - $opening, $width - $opening, $height),
            ],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'default', 'hem', 'default'],
                'fold_edges' => [4],
                'girth_role' => 'trim',
                'notes' => ['روی تای پارچه بریده می‌شود تا جیب یک‌تکه از یک پهلو تا پهلوی دیگر برود.'],
            ],
        ]);
    }

    /**
     * قطعه‌های آستر تنه، اگر کاربر خواسته باشد.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function liningSet(array $g, array $params, array $o = []): array
    {
        if (! $this->flag($params, 'lining', (bool) ($o['default'] ?? false))) {
            return [];
        }

        return $this->bodiceLining($g, $o);
    }

    /**
     * جیب رودوزی، اگر کاربر خواسته باشد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pocketSet(array $params, array $o = []): array
    {
        if (! $this->flag($params, 'pocket', true)) {
            return [];
        }

        return [$this->patchPocketPiece(
            (float) $this->param($params, 'pocket_width', 14),
            (float) $this->param($params, 'pocket_height', 16),
            $o,
        )];
    }

    /**
     * تنه کامل یک لباس رویی: جلو، پشت، آستین، یقه، سجاف و آستر.
     *
     * همان ترتیبی که خیاط الگو می‌زند: اول دو پنل تنه، بعد اندازه‌گیری حلقه از
     * روی همان دو پنل و درفت آستین با همان عدد، بعد یقه از روی طول یقه تنه و در
     * آخر سجاف و آستر. هر لباس رویی کاتالوگ فقط گزینه‌هایش را عوض می‌کند.
     *
     * گزینه‌ها: prefix، grow، shape، length، opening، stand، hem_flare،
     * back_seam، bust_dart، panel (گزینه‌های مشترک دو پنل)، front، back،
     * sleeve، collar، collar_height، facing، lining، front_name، back_name.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function outerGarment(array $m, array $ease, array $params, array $g, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $grow = (float) ($o['grow'] ?? $this->fitGrow($params));
        $shape = (string) ($o['shape'] ?? $this->fitShape($params));
        $length = (float) ($o['length'] ?? $this->param($params, 'length', 60));
        $opening = (string) ($o['opening'] ?? $this->param($params, 'front_opening', 'button'));
        $stand = $opening === 'button' ? (float) ($o['stand'] ?? $this->param($params, 'button_stand', 2.5)) : 0.0;
        $flare = (float) ($o['hem_flare'] ?? $this->param($params, 'hem_flare', 0));
        $backSeam = (bool) ($o['back_seam'] ?? false);
        $closed = $opening === 'closed';

        $shared = array_merge([
            'shape' => $shape,
            'length' => $length,
            'grow' => $grow,
            'hem_flare' => $flare,
            'waist_dart' => $shape === 'fitted',
            'bottom_tag' => 'hem',
        ], $o['panel'] ?? []);

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => $closed,
            'cut' => $closed ? 1 : 2,
            'mirror' => ! $closed,
            'bust_dart' => (bool) ($o['bust_dart'] ?? false),
            'code' => $prefix.'front',
            'name' => $o['front_name'] ?? 'تنه جلو',
            'meta' => array_merge([
                'front_opening' => $opening,
                'button_stand' => round($stand, 2),
            ], $o['front_meta'] ?? []),
        ], $o['front'] ?? []));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'on_fold' => ! $backSeam,
            'cut' => $backSeam ? 2 : 1,
            'mirror' => $backSeam,
            'code' => $prefix.'back',
            'name' => $o['back_name'] ?? 'تنه پشت',
            'meta' => $o['back_meta'] ?? [],
        ], $o['back'] ?? []));

        [$front, $back] = $this->walkSideSeams($front, $back);

        if ($opening === 'button') {
            $buttons = (int) ($o['buttons'] ?? $this->param($params, 'buttons', 5));
            $front = $this->markButtons($front, $stand, $g['front_neck_depth'] + 2, $g['front_waist_y'] + min($length - 4, 18), $buttons);
        }

        $pieces = [$front, $back];
        $armhole = $this->armholeOf([$front, $back]);
        $halfNeck = $this->neckOf([$front, $back]);

        $pieces = array_merge($pieces, $this->sleeveSet($m, $ease, $params, $armhole, $g, array_merge(
            ['prefix' => $prefix],
            $o['sleeve'] ?? [],
        )));

        $pieces = array_merge($pieces, $this->collarSet($g, $halfNeck, $params, $o));

        if (($o['facing'] ?? ! $closed) && ($o['collar'] ?? 'none') !== 'shawl') {
            $pieces = array_merge($pieces, $this->facingSet($g, $stand, $g['front_waist_y'] + $length, array_merge([
                'prefix' => $prefix,
                'width' => (float) ($o['facing_width'] ?? 8),
            ], $o['facing_options'] ?? [])));
        }

        $pieces = array_merge($pieces, $this->liningSet($g, $params, array_merge([
            'prefix' => $prefix,
            'shape' => $shape,
            'length' => max(0.0, $length - 2),
            'default' => (bool) ($o['lining'] ?? false),
        ], $o['lining_options'] ?? [])));

        return $pieces;
    }

    /**
     * یقه لباس بر پایه گزینه انتخابی.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function collarSet(array $g, float $halfNeck, array $params, array $o = []): array
    {
        $collar = (string) ($o['collar'] ?? $this->param($params, 'collar', 'none'));
        $height = (float) ($o['collar_height'] ?? $this->param($params, 'collar_height', 6));
        $prefix = (string) ($o['prefix'] ?? '');

        return match ($collar) {
            'stand' => [$this->standCollarPiece($halfNeck, $height, ['prefix' => $prefix])],
            'turn' => [$this->turnCollarPiece($halfNeck, $height, ['prefix' => $prefix])],
            'shawl' => [
                $this->shawlFacingPiece($g, 1.5, $g['front_waist_y'] + (float) ($o['length'] ?? 40), [
                    'prefix' => $prefix,
                    'width' => $height + 2,
                    'break_y' => $g['bust_y'] + (float) ($o['collar_break'] ?? 8),
                ]),
                $this->backNeckFacingPiece($g, ['prefix' => $prefix]),
            ],
            'hood' => [$this->hoodPiece($g, $halfNeck, array_merge(['prefix' => $prefix], $o['hood'] ?? []))],
            default => [],
        };
    }

    /**
     * چاک پهلو روی لبه پایین (تونیک، مانتو راسته، کافتان).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markSideVent(array $piece, float $length, string $label = 'چاک پهلو'): array
    {
        if ($length < 1) {
            return $piece;
        }

        $sideEdges = $piece['meta']['side_edges'] ?? [];
        $edge = is_array($sideEdges) && $sideEdges !== [] ? (int) end($sideEdges) : null;

        if ($edge === null) {
            return $piece;
        }

        $point = Geometry::pointOnEdge($piece['outline'], $edge, 1.0);
        $piece['notches'][] = $this->notch($point['x'], $point['y'] - $length, $edge, 'سر چاک پهلو', 'vent');
        $piece['meta']['vent'] = round($length, 2);
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            $label.' به بلندی '.$this->fa(round($length, 1)).' سانتی‌متر از لبه پایین باز می‌ماند.',
        ]);

        return $piece;
    }
}
