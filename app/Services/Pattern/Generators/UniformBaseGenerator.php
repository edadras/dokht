<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه لباس کار و یونیفرم.
 *
 * لباسِ کار سه چیزِ روشن دارد که لباسِ روزمره ندارد، و هر سه در همین لایه
 * یک‌بار نوشته شده‌اند:
 *
 *   ۱. باید بشود در آن کار کرد. حلقه گودتر و آزادیِ تنه بیشتر از پیراهنِ معمولی
 *      است، چون دستِ کارکننده بالای سر می‌رود و کسی نباید سرِ کار مواظبِ لباسش
 *      باشد.
 *   ۲. جیب تصمیمِ سلیقه‌ای نیست. آشپز، پرستار و تعمیرکار هرکدام جیبِ خودشان را
 *      لازم دارند؛ پس جیب بخشی از شخصیتِ مدل است، نه یک گزینهٔ خاموش.
 *   ۳. زیاد شسته می‌شود. یعنی درزها ساده و کم، و لبه‌ها همه تمیزدوزی‌شدنی.
 *
 * فرم‌ها: top (پیراهن و کت کار)، dress (روپوش و یونیفرمِ یک‌تکه)، apron (پیش‌بند).
 */
abstract class UniformBaseGenerator extends BodiceGarmentBase
{
    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'uniform';
    }

    /** کاربردهایی که یک یونیفرم می‌تواند داشته باشد. */
    protected const USES = [
        'kitchen' => 'آشپزخانه و رستوران',
        'medical' => 'درمان و بهداشت',
        'workshop' => 'کارگاه و فنی',
        'office' => 'اداری و پذیرش',
        'service' => 'خدمات و فروش',
        'school' => 'مدرسه',
    ];

    /**
     * شخصیتِ این مدل.
     *
     * کلیدها: prefix، title، form (top|dress|apron)، length، shape، grow،
     * opening، buttons، button_stand، collar، collar_height، sleeve،
     * sleeve_length، armhole، pocket، pocket_count، hem_flare، facing، belt،
     * use، schema، extra، notes.
     *
     * برای form=apron: bib_width، bib_height، skirt_width، skirt_length،
     * neck_strap، waist_tie.
     *
     * @return array<string, mixed>
     */
    abstract protected function uniform(): array;

    public function label(): string
    {
        return (string) ($this->uniform()['title'] ?? 'یونیفرم');
    }

    public function paramsSchema(): array
    {
        $u = $this->uniform();

        $use = [
            'garment_use' => [
                'label' => 'کاربرد', 'type' => 'select',
                'default' => (string) ($u['use'] ?? 'service'), 'options' => self::USES,
            ],
        ];

        if ((string) ($u['form'] ?? 'top') === 'apron') {
            return array_merge([
                'bib_width' => [
                    'label' => 'پهنای سینه‌بند', 'min' => 0, 'max' => 45, 'step' => 1,
                    'default' => (float) ($u['bib_width'] ?? 28), 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی پیش‌بندِ کمری، بی سینه‌بند.',
                ],
                'bib_height' => [
                    'label' => 'بلندی سینه‌بند', 'min' => 0, 'max' => 45, 'step' => 1,
                    'default' => (float) ($u['bib_height'] ?? 30), 'unit' => 'سانتی‌متر',
                ],
                'skirt_width' => [
                    'label' => 'پهنای دامن پیش‌بند', 'min' => 40, 'max' => 110, 'step' => 1,
                    'default' => (float) ($u['skirt_width'] ?? 60), 'unit' => 'سانتی‌متر',
                ],
                'skirt_length' => [
                    'label' => 'بلندی دامن پیش‌بند', 'min' => 25, 'max' => 100, 'step' => 1,
                    'default' => (float) ($u['skirt_length'] ?? 62), 'unit' => 'سانتی‌متر',
                ],
                'pocket_count' => [
                    'label' => 'تعداد جیب', 'min' => 0, 'max' => 3, 'step' => 1,
                    'default' => (int) ($u['pocket_count'] ?? 2),
                ],
            ], $use, (array) ($u['extra'] ?? []));
        }

        $schema = array_merge(
            $this->outerSchema(array_merge([
                'shoulder_slope' => 4,
                'neck_width_extra' => 1.5,
                'front_neck_depth_extra' => 2,
                'back_neck_depth' => 2.5,
                // حلقهٔ لباسِ کار گودتر است تا دست بالای سر برود
                'armhole_depth_extra' => (float) ($u['armhole'] ?? 4),
            ], (array) ($u['schema'] ?? []))),
            $this->garmentLengthParam(
                (float) ($u['length'] ?? 20),
                (float) ($u['length_min'] ?? 8),
                (float) ($u['length_max'] ?? 120),
            ),
            $this->sleeveParam(
                (string) ($u['sleeve'] ?? 'set_in'),
                (float) ($u['sleeve_length'] ?? 58),
                ['none' => 'بدون آستین', 'set_in' => 'آستین (کوتاه یا بلند)'],
            ),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 0.5, 'max' => 8, 'step' => 0.5,
                    'default' => (float) ($u['grow'] ?? 2.5), 'unit' => 'سانتی‌متر',
                    'hint' => 'لباس کار باید اجازهٔ حرکت بدهد؛ کمتر از دو سانتی‌متر یعنی روی شانه می‌کشد.',
                ],
                'pocket' => [
                    'label' => 'جیب رودوزی', 'type' => 'toggle', 'default' => (bool) ($u['pocket'] ?? true),
                ],
            ],
            $use,
        );

        if ((string) ($u['opening'] ?? 'button') !== 'closed') {
            $schema = array_merge($schema, $this->openingParam(
                (string) ($u['opening'] ?? 'button'),
                (float) ($u['button_stand'] ?? 2.5),
            ));
            $schema['buttons']['default'] = (int) ($u['buttons'] ?? 6);
        }

        if ((string) ($u['collar'] ?? 'none') !== 'none') {
            $schema = array_merge($schema, $this->collarParam(
                (string) $u['collar'],
                ['none' => 'بدون یقه', 'stand' => 'یقه ایستاده', 'turn' => 'یقه برگردان'],
                (float) ($u['collar_height'] ?? 5),
            ));
        }

        if (($u['hem_flare'] ?? 0) > 0) {
            $schema['hem_flare'] = [
                'label' => 'باز شدن لبه پایین', 'min' => 0, 'max' => 30, 'step' => 1,
                'default' => (float) $u['hem_flare'], 'unit' => 'سانتی‌متر',
            ];
        }

        return array_merge($schema, (array) ($u['extra'] ?? []));
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $u = $this->uniform();

        if ((string) ($u['form'] ?? 'top') === 'apron') {
            return $this->apronPieces($params, $u);
        }

        $g = $this->blockMetrics($measurements, $ease, $params);
        $prefix = (string) ($u['prefix'] ?? static::key()).'-';
        $grow = (float) $this->param($params, 'ease_extra', $u['grow'] ?? 2.5);
        $opening = (string) $this->param($params, 'front_opening', $u['opening'] ?? 'button');

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => $prefix,
            'grow' => $grow,
            'shape' => (string) ($u['shape'] ?? 'straight'),
            'length' => (float) $this->param($params, 'length', $u['length'] ?? 20),
            'opening' => $opening,
            'stand' => (float) $this->param($params, 'button_stand', $u['button_stand'] ?? 2.5),
            'hem_flare' => (float) $this->param($params, 'hem_flare', $u['hem_flare'] ?? 0),
            'collar' => (string) $this->param($params, 'collar', $u['collar'] ?? 'none'),
            'collar_height' => (float) $this->param($params, 'collar_height', $u['collar_height'] ?? 5),
            'buttons' => (int) $this->param($params, 'buttons', $u['buttons'] ?? 6),
            'facing' => (bool) ($u['facing'] ?? ($opening !== 'closed')),
            'bust_dart' => (bool) ($u['bust_dart'] ?? false),
            'panel' => ['waist_dart' => false],
        ]);

        if ($this->flag($params, 'pocket', (bool) ($u['pocket'] ?? true))) {
            $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => $prefix]));
        }

        $pieces = $this->stampUniform($pieces, $params, $u);

        return $this->finishBlock($this->uniformNoted($pieces, (array) ($u['notes'] ?? [])), $g, $grow);
    }

    /**
     * پیش‌بند: سینه‌بند، دامن، بندِ گردن و بندِ کمر.
     *
     * پیش‌بند از بدن درفت نمی‌شود — روی هر تنی می‌افتد و اندازه‌اش را بندها
     * تنظیم می‌کنند. پس این‌جا اندازه‌ها مستقیم از پارامترها می‌آیند و همین هم
     * درست است: پیش‌بندی که با دور سینه درفت شود، روی تنِ دیگر تنگ می‌شود.
     *
     * @param  array<string, mixed>  $u
     * @return array<int, array<string, mixed>>
     */
    protected function apronPieces(array $params, array $u): array
    {
        $prefix = (string) ($u['prefix'] ?? static::key()).'-';

        $bibW = (float) $this->param($params, 'bib_width', $u['bib_width'] ?? 28);
        $bibH = (float) $this->param($params, 'bib_height', $u['bib_height'] ?? 30);
        $skirtW = (float) $this->param($params, 'skirt_width', $u['skirt_width'] ?? 60);
        $skirtL = (float) $this->param($params, 'skirt_length', $u['skirt_length'] ?? 62);
        $pockets = (int) $this->param($params, 'pocket_count', $u['pocket_count'] ?? 2);

        $pieces = [];

        // دامنِ پیش‌بند: نیم‌قطعه روی تای مرکز جلو، پس پهنای بریده‌شده نصف است
        $half = $skirtW / 2;

        $pieces[] = $this->piece([
            'code' => $prefix.'skirt',
            'name' => 'دامن پیش‌بند',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($half, 0),
                Geometry::point($half, $skirtL),
                Geometry::point(0, $skirtL),
            ],
            'grainline' => $this->grainline($half * 0.5, 2, $skirtL - 2),
            'meta' => [
                'part' => 'apron_skirt',
                'edges' => ['waist', 'side', 'hem', 'default'],
                'fold_edges' => [3],
                'girth_role' => 'shell',
                'notes' => [
                    'روی تای مرکز جلو بریده می‌شود؛ پهنای تمام‌شده '.$this->fa(round($skirtW, 1)).' سانتی‌متر است.',
                ],
            ],
        ]);

        if ($bibW > 1 && $bibH > 1) {
            $pieces[] = $this->piece([
                'code' => $prefix.'bib',
                'name' => 'سینه‌بند پیش‌بند',
                'cut_quantity' => 1,
                'on_fold' => true,
                'outline' => [
                    Geometry::point(0, 0),
                    Geometry::point($bibW / 2, 0),
                    Geometry::point($bibW / 2, $bibH),
                    Geometry::point(0, $bibH),
                ],
                'grainline' => $this->grainline($bibW * 0.25, 2, $bibH - 2),
                'meta' => [
                    'part' => 'apron_bib',
                    'edges' => ['default', 'side', 'waist', 'default'],
                    'fold_edges' => [3],
                    'girth_role' => 'shell',
                    'notes' => ['لبهٔ بالا دولا و بندِ گردن روی همان دوخته می‌شود.'],
                ],
            ]);

            $pieces[] = $this->bandPiece($prefix.'neck-strap', 'بند گردن', 62, 6, [
                'cut' => 1, 'fold_line' => true, 'part' => 'strap',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['دولا دوخته می‌شود و پهنای تمام‌شده‌اش سه سانتی‌متر است؛ با سگک یا گره تنظیم می‌شود.'],
                ],
            ]);
        }

        $pieces[] = $this->bandPiece($prefix.'waist-tie', 'بند کمر', 90, 8, [
            'cut' => 2, 'fold_line' => true, 'part' => 'strap',
            'meta' => [
                'girth_role' => 'trim',
                'notes' => ['دو بند، هرکدام از یک سرِ کمر؛ پشت گره می‌خورند.'],
            ],
        ]);

        for ($i = 0; $i < $pockets; $i++) {
            $pieces[] = $this->patchPocketPiece(18, 20, ['prefix' => $prefix.($i + 1).'-']);
        }

        $pieces = $this->stampUniform($pieces, $params, $u);

        return $this->finish($this->uniformNoted($pieces, array_merge([
            'پیش‌بند از اندازهٔ بدن درفت نمی‌شود؛ بندها اندازه‌اش را روی هر تنی تنظیم می‌کنند.',
        ], (array) ($u['notes'] ?? []))));
    }

    /**
     * یادداشت‌های مدل روی قطعهٔ نخست.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function uniformNoted(array $pieces, array $notes): array
    {
        $pieces = array_values(array_filter($pieces));

        if ($pieces === [] || $notes === []) {
            return $pieces;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $pieces;
    }

    /**
     * ثبت مدل و کاربرد روی همه قطعه‌ها.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, mixed>  $u
     * @return array<int, array<string, mixed>>
     */
    protected function stampUniform(array $pieces, array $params, array $u): array
    {
        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['uniform'] = [
                'model' => (string) ($u['prefix'] ?? static::key()),
                'form' => (string) ($u['form'] ?? 'top'),
                'use' => (string) $this->param($params, 'garment_use', $u['use'] ?? 'service'),
            ];
        }

        return $pieces;
    }
}
