<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;

/**
 * پایه مشترک پیراهن زنانه روزمره.
 *
 * این خانواده با لباس شب یک تفاوت روشن دارد: پیراهن روزمره باید پوشیده شود،
 * روی صندلی نشسته شود و ماشین لباس‌شویی را ببیند. پس آزادی‌اش بیشتر است، آستر
 * پیش‌فرض ندارد و بستش ساده است. ولی جای شکستنش دقیقاً همان جای لباس شب است:
 *
 *     **خط کمر.**
 *
 * بالاتنه دور کمرش را از بدن و ساسون می‌گیرد و دامن دور کمرش را از بدن و آزادی
 * خودش؛ این دو خودبه‌خود یکی نمی‌شوند. اگر کسی حواسش نباشد، الگو روی کاغذ درست
 * است و روی پارچه دوخته نمی‌شود. پس این کلاس هر بار دو عدد را اندازه می‌گیرد و
 * اختلاف را یا با چین می‌بندد یا بلند می‌گوید:
 *
 *   چین     اگر دامن پُرتر از کمر بالاتنه باشد (چین‌دار، امپایر، سارافون)
 *   مستقیم  اگر اختلاف کمتر از شش میلی‌متر باشد
 *   هشدار   اگر دامن از بالاتنه کوچک‌تر باشد؛ عدد گفته می‌شود نه پنهان
 *
 * یک نکتهٔ فنی که این خانواده را قبلاً می‌شکست: چینی که فقط در meta.fullness
 * ثبت شود را هیچ اندازه‌گیر عمومی‌ای نمی‌بیند. هر چینی که این‌جا ساخته می‌شود با
 * FullnessRecorder در meta.gathers هم می‌نشیند، وگرنه پهنای خامِ پارچه به‌جای
 * دور کمرِ تمام‌شده شمرده می‌شود.
 */
abstract class DressBaseGenerator extends BodiceGarmentBase
{
    /** کوچک‌ترین اختلافی که دیگر «اختلاف» حساب می‌شود (سانتی‌متر). */
    protected const WAIST_TOLERANCE = 0.6;

    public static function group(): string
    {
        return 'dress';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک هر پیراهن این خانواده.
     *
     * @param  array<string, array<string, mixed>>  $extra
     * @param  array<string, mixed>  $defaults  بازنویسی پیش‌فرض هر پارامتر
     * @param  array<string, float>  $block  بازنویسی پیش‌فرض‌های درفت بلوک
     * @return array<string, array<string, mixed>>
     */
    protected function dressSchema(array $extra = [], array $defaults = [], array $block = []): array
    {
        $schema = array_merge(
            $this->outerSchema(array_merge([
                'armhole_depth_extra' => 1.5,
                'neck_width_extra' => 0.5,
                'front_neck_depth_extra' => 2,
                'back_neck_depth' => 2,
                'waist_dart_share' => 0.6,
            ], $block)),
            $this->fitParam(),
            $extra,
            [
                'back_closure' => [
                    'label' => 'بست پشت', 'type' => 'select', 'default' => 'zip',
                    'options' => [
                        'zip' => 'زیپ مخفی مرکز پشت',
                        'buttons' => 'دکمهٔ مرکز پشت',
                        'none' => 'بدون بست (از سر پوشیده می‌شود)',
                    ],
                    'hint' => 'پیراهن کمرگرفته بدون بست از باسن رد نمی‌شود.',
                ],
                'lining' => [
                    'label' => 'آستر', 'type' => 'select', 'default' => 'none',
                    'options' => [
                        'full' => 'کامل (بالاتنه و دامن)',
                        'bodice' => 'فقط بالاتنه',
                        'none' => 'ندارد',
                    ],
                ],
            ],
        );

        foreach ($defaults as $key => $value) {
            if (isset($schema[$key])) {
                $schema[$key]['default'] = $value;
            }
        }

        return $schema;
    }

    /** پارامتر بلندی دامن از خط کمر. */
    protected function skirtLengthParam(float $default = 62, float $min = 30, float $max = 120, string $label = 'بلندی دامن از خط کمر'): array
    {
        return [
            'skirt_length' => [
                'label' => $label, 'min' => $min, 'max' => $max, 'step' => 1,
                'default' => $default, 'unit' => 'سانتی‌متر',
                'hint' => 'از خط کمر تا دم لباس، روی مرکز جلو.',
            ],
        ];
    }

    /**
     * آزادی مشترک بالاتنه و دامن.
     *
     * مهم‌ترین کار این متد این است که *یک* آزادی به هر دو قطعه بدهد. اگر بالاتنه
     * و دامن هرکدام آزادی پیش‌فرض خودشان را بگیرند، دو کمر با چند سانتی‌متر
     * اختلاف درمی‌آیند و هیچ‌کس تا لحظهٔ دوخت نمی‌فهمد.
     *
     * @param  array<string, float>  $ease
     * @param  array<string, float>  $base
     * @return array<string, float>
     */
    protected function dressEase(array $ease, array $params, array $base = ['bust' => 6.0, 'waist' => 4.0, 'hip' => 5.0]): array
    {
        $extra = match ((string) $this->param($params, 'fit', 'regular')) {
            'fitted' => 0.0,
            'loose' => 5.0,
            default => 2.0,
        };

        return array_merge($ease, [
            'bust' => $base['bust'] + $extra,
            'waist' => $base['waist'] + $extra,
            'hip' => $base['hip'] + $extra,
        ]);
    }

    /* ---------------------------------------------------------------------
     |  بالاتنه
     * ------------------------------------------------------------------- */

    /**
     * بالاتنهٔ پیراهن: جلو، پشت و دور کمرِ اندازه‌گیری‌شده‌شان.
     *
     * دور کمر از روی خودِ مسیر خوانده می‌شود (پس از کم کردن ساسون و چین)، نه از
     * عددی که موقع درفت در ذهن بوده؛ همان عدد است که به دامن داده می‌شود.
     *
     * گزینه‌ها: prefix، shape، length، grow، extension (اضافه جای دکمه یا رویهم)،
     * back_seam، bust_dart، waist_dart، neck_drop، back_drop، panel، front، back.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    protected function dressBodice(array $g, array $params, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? 'dress');
        $extension = max(0.0, (float) ($o['extension'] ?? 0));
        $backSeam = (bool) ($o['back_seam'] ?? true);

        $length = (float) ($o['length'] ?? 0);

        $shared = array_merge([
            'shape' => $o['shape'] ?? 'waist',
            'length' => $length,
            'grow' => (float) ($o['grow'] ?? 0),
            'bottom_tag' => $o['bottom_tag'] ?? 'waist',
            'waist_dart' => (bool) ($o['waist_dart'] ?? true),
            'hem_flare' => (float) ($o['hem_flare'] ?? 0),
        ], $o['panel'] ?? []);

        // یقه هرچقدر هم گود باشد باید دست‌کم هشت سانتی‌متر بالای لبهٔ پایین قطعه
        // بماند. بدون این کف، یقهٔ خیلی گود روی بالاتنهٔ کوتاه (امپایر، بدن کودک)
        // از لبهٔ پایین رد می‌شود و مسیر قطعه خودش را قطع می‌کند.
        [$g, $neckDrop] = $this->clampNeck($g, 'front', $length, (float) ($o['neck_drop'] ?? 0));
        [$g, $backDrop] = $this->clampNeck($g, 'back', $length, (float) ($o['back_drop'] ?? 0));

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $extension,
            'on_fold' => $extension <= 0.01,
            'bust_dart' => (bool) ($o['bust_dart'] ?? true),
            'neck_depth_extra' => $neckDrop,
            'code' => $prefix.'-bodice-front',
            'name' => $o['front_name'] ?? 'بالاتنه جلو',
        ], $o['front'] ?? []));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'on_fold' => ! $backSeam,
            'neck_depth_extra' => $backDrop,
            'code' => $prefix.'-bodice-back',
            'name' => $o['back_name'] ?? 'بالاتنه پشت',
            'meta' => ['back_seam' => $backSeam],
        ], $o['back'] ?? []));

        [$front, $back] = $this->walkSideSeams($front, $back);

        $pieces = [$front, $back];

        return [$pieces, $this->edgeGirth($pieces, 'waist')];
    }

    /**
     * نگه داشتن گودی یقه بالای لبهٔ پایین قطعه.
     *
     * گودی یقه از دو جا می‌آید: عددی که در درفت بلوک نشسته و عددی که خودِ مدل
     * اضافه می‌کند. هیچ‌کدام نمی‌داند لبهٔ پایین این قطعه کجاست، و روی بالاتنهٔ
     * کوتاه — امپایر، یا هر بالاتنه‌ای روی بدن کودک — یقهٔ گود از لبهٔ پایین رد
     * می‌شود و مسیر قطعه خودش را قطع می‌کند. این‌جا هر دو عدد به سقف واقعی همین
     * قطعه محدود می‌شوند.
     *
     * @param  array<string, float>  $g
     * @return array{0: array<string, float>, 1: float}
     */
    protected function clampNeck(array $g, string $side, float $length, float $drop): array
    {
        $key = $side === 'front' ? 'front_neck_depth' : 'back_neck_depth';
        $ceiling = max(2.0, (float) $g[$side === 'front' ? 'front_waist_y' : 'back_waist_y'] + $length - 8.0);

        $g[$key] = round(min((float) $g[$key], $ceiling), 3);

        return [$g, round(max(0.0, min($drop, $ceiling - (float) $g[$key])), 3)];
    }

    /**
     * تبدیل ساسون کمر به چین روی همان لبه.
     *
     * پیراهن راپ و پیراهن امپایر ساسون کمر ندارند؛ همان پارچه روی درز کمر چین
     * داده می‌شود. مقدارش دقیقاً همان دهانهٔ ساسون است، پس دور کمرِ تمام‌شده هیچ
     * تغییری نمی‌کند — فقط راهِ بستنش عوض می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function dartsToGathers(array $piece, string $tag = 'waist', string $label = 'چین کمر'): array
    {
        $edges = Geometry::edgesWithTag($piece, $tag);

        if ($edges === []) {
            return $piece;
        }

        $keep = [];
        $amount = 0.0;

        foreach ($piece['darts'] ?? [] as $dart) {
            $edge = $dart['edge'] ?? null;

            if (($dart['type'] ?? '') === 'waist' && ($edge === null || in_array((int) $edge, $edges, true))) {
                $amount += (float) ($dart['intake'] ?? 0);

                continue;
            }

            $keep[] = $dart;
        }

        if ($amount <= 0.1) {
            return $piece;
        }

        $piece['darts'] = $keep;

        return FullnessRecorder::gathers($piece, (int) end($edges), $amount, [
            'label' => $label,
            'start' => 0.15,
            'end' => 0.85,
        ]);
    }

    /* ---------------------------------------------------------------------
     |  دامن
     * ------------------------------------------------------------------- */

    /**
     * دامنِ دوخته‌شده به بالاتنه، از همان اندازه‌های بلوک.
     *
     * چون هر دو پنل از یک $g درفت می‌شوند، کمرشان از پیش با کمر بالاتنه هم‌خانواده
     * است و joinWaist فقط ته‌ماندهٔ اختلاف را می‌بندد.
     *
     * گزینه‌ها: prefix، type، length، flare، gather (پارچهٔ چین در هر پنل)،
     * grow، extension، back_seam، bodice_waist، front، back.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function attachedSkirt(array $g, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? 'dress');
        $gather = max(0.0, (float) ($o['gather'] ?? 0));
        $extension = max(0.0, (float) ($o['extension'] ?? 0));
        $backSeam = (bool) ($o['back_seam'] ?? true);

        $shared = [
            'type' => $o['type'] ?? 'a_line',
            'length' => (float) ($o['length'] ?? 62),
            'flare' => (float) ($o['flare'] ?? 10),
            'gather' => $gather,
        ];

        if (isset($o['dart'])) {
            $shared['dart'] = (float) $o['dart'];
        }

        $build = function (float $grow) use ($g, $o, $shared, $prefix, $extension, $backSeam, $gather): array {
            $front = $this->skirtPanel($g, array_merge($shared, [
                'grow' => $grow,
                'side' => 'front',
                'extension' => $extension,
                'code' => $prefix.'-skirt-front',
                'name' => $o['front_name'] ?? 'دامن جلو',
            ], $o['front'] ?? []));

            $back = $this->skirtPanel($g, array_merge($shared, [
                'grow' => $grow,
                'side' => 'back',
                'on_fold' => ! $backSeam,
                'cut' => $backSeam ? 2 : 1,
                'code' => $prefix.'-skirt-back',
                'name' => $o['back_name'] ?? 'دامن پشت',
            ], $o['back'] ?? []));

            return [
                $this->recordSkirtGathers($this->snapDarts($front), $gather),
                $this->recordSkirtGathers($this->snapDarts($back), $gather),
            ];
        };

        $grow = (float) ($o['grow'] ?? 0);
        $pair = $build($grow);
        $target = (float) ($o['bodice_waist'] ?? 0);

        // کمر دامن هرگز نباید از کمر بالاتنه کوچک‌تر دربیاید؛ آن‌وقت دو لبه به هم
        // نمی‌رسند و چین هم کاری از پیش نمی‌برد. روی بدنی که اختلاف سینه تا کمرش
        // کم است، ساسون کمرِ بالاتنه آن‌قدر کم‌عمق می‌شود که اصلاً کشیده نمی‌شود و
        // کمر بالاتنه چند میلی‌متر پهن‌تر از حساب درمی‌آید. پس یک بار اندازه
        // می‌گیریم و دامن را با همان اختلاف دوباره درفت می‌کنیم.
        if ($target > 1.0) {
            $short = $target - $this->edgeGirth($pair, 'waist');

            if ($short > 0.2) {
                $pair = $build($grow + ($short / 4));
            }
        }

        return $pair;
    }

    /**
     * چسباندن هر ساسون به لبه‌ای که پاهایش واقعاً روی آن نشسته‌اند.
     *
     * پنلی که «اضافهٔ رویهم» یا «اضافهٔ جای دکمه» دارد، لبهٔ کمرش دو تکه می‌شود و
     * شمارهٔ لبه‌ها یکی جلو می‌رود؛ ساسونی که شمارهٔ قدیمش را نگه دارد به لبه‌ای
     * اشاره می‌کند که رویش نیست و در چاپ و در جفت‌کردن درزها گمراه می‌کند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function snapDarts(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return $piece;
        }

        foreach ($piece['darts'] ?? [] as $index => $dart) {
            $legs = array_values($dart['legs'] ?? []);

            if (($dart['edge'] ?? null) === null || count($legs) !== 2) {
                continue;
            }

            $best = null;
            $bestDistance = INF;

            for ($edge = 0; $edge < $count; $edge++) {
                $distance = 0.0;

                foreach ($legs as $leg) {
                    $at = ['x' => (float) $leg['x'], 'y' => (float) $leg['y']];
                    $t = Geometry::edgeParameterOf($outline, $edge, $at, 24);
                    $distance += Geometry::distance(Geometry::pointOnEdge($outline, $edge, $t), $at);
                }

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $edge;
                }
            }

            if ($best !== null) {
                $piece['darts'][$index]['edge'] = $best;
            }
        }

        return $piece;
    }

    /**
     * ثبت چین کمر دامن جایی که خوانده می‌شود.
     *
     * skirtPanel چین را در meta.fullness و در فهرست پیلی‌ها می‌نویسد؛ آن دو زبانِ
     * رندر و برگهٔ فنی‌اند. هر اندازه‌گیرِ دیگری — PieceOps::seamLength، بازرسی
     * کاتالوگ، همین joinWaist — فقط meta.gathers را می‌بیند. بدون این ثبت، پهنای
     * خام پارچه به‌جای دور کمرِ تمام‌شده شمرده می‌شود.
     *
     * خط نشانهٔ کمر هم به همان اندازهٔ تمام‌شده کوتاه می‌شود، وگرنه «اندازهٔ
     * تمام‌شدهٔ» گزارش‌شده پهنای پارچهٔ پیش از چین است.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function recordSkirtGathers(array $piece, float $gather): array
    {
        if ($gather <= 0.1) {
            return $piece;
        }

        $edges = Geometry::edgesWithTag($piece, 'waist');

        if ($edges === []) {
            return $piece;
        }

        $piece = FullnessRecorder::gathers($piece, (int) end($edges), $gather, ['label' => 'چین کمر دامن']);

        foreach ($piece['markers'] ?? [] as $index => $marker) {
            if (($marker['key'] ?? '') !== 'waist') {
                continue;
            }

            $piece['markers'][$index]['to']['x'] = round(max(
                (float) $marker['from']['x'],
                ((float) $marker['to']['x']) - $gather,
            ), 2);
        }

        return $piece;
    }

    /**
     * دامن، از همان کاتالوگ سی‌ودو تایی دامن.
     *
     * چیزی از نو درفت نمی‌شود: چین‌دار، کلوش، ترک‌دار و طبقه‌ای همه از پیش هستند و
     * آزموده شده‌اند. این‌جا فقط نامشان عوض می‌شود و کمربند خودشان برداشته می‌شود،
     * چون خط کمرِ پیراهن به بالاتنه دوخته می‌شود نه به کمربند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function catalogSkirt(string $key, array $measurements, array $ease, array $overrides = [], string $prefix = 'dress'): array
    {
        if (! GeneratorRegistry::has($key)) {
            return [];
        }

        $generator = GeneratorRegistry::make($key);
        $params = array_merge($generator->defaultParams(), array_merge([
            'waistband' => false,
            'zip' => 'none',
        ], $overrides));

        $out = [];

        foreach ($generator->generate($measurements, $ease, $params) as $piece) {
            if (($piece['meta']['part'] ?? '') === 'waistband') {
                continue;
            }

            $piece['code'] = $prefix.'-skirt-'.($piece['code'] ?? 'panel');
            $piece['name'] = 'دامن — '.($piece['name'] ?? '');
            $piece['meta']['dress_skirt'] = true;

            // کدِ قطعه عوض شد، پس همسایه‌های اعلام‌شده هم باید با همان پیشوند
            // نوشته شوند؛ وگرنه ترکِ دامن دنبالِ کدی می‌گردد که دیگر وجود ندارد
            if (($piece['meta']['seam_neighbours'] ?? []) !== []) {
                $piece['meta']['seam_neighbours'] = array_map(
                    fn (string $code) => $prefix.'-skirt-'.$code,
                    (array) $piece['meta']['seam_neighbours'],
                );
            }
            $out[] = $piece;
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  خط کمر: جایی که این خانواده می‌شکند
     * ------------------------------------------------------------------- */

    /**
     * رساندن کمر دامن به کمر بالاتنه.
     *
     * دو عدد اندازه گرفته می‌شود و اختلافشان روی همان لبهٔ کمر چین می‌خورد. سهم
     * هر پنل به نسبت پارچه‌ای است که خودش روی کمر دارد و در ضریب تکرارِ همان پنل
     * (تای پارچه و تعداد برش) هم حساب می‌شود؛ اگر اختلاف را ساده بین پنل‌ها بخش
     * کنیم، پنلی که دولا بریده می‌شود دو برابر سهمش را می‌خورد و کمر دامن از
     * بالاتنه کوچک‌تر درمی‌آید.
     *
     * @param  array<int, array<string, mixed>>  $skirt
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function joinWaist(array $skirt, float $bodiceWaist): array
    {
        $notes = [];
        $skirtWaist = $this->edgeGirth($skirt, 'waist');

        if ($skirtWaist < 1.0 || $bodiceWaist < 1.0) {
            return [$skirt, $notes];
        }

        $difference = $skirtWaist - $bodiceWaist;

        if (abs($difference) < static::WAIST_TOLERANCE) {
            $notes[] = 'کمر دامن و کمر بالاتنه هر دو '.$this->fa(round($bodiceWaist, 1))
                .' سانتی‌متر است و مستقیم به هم دوخته می‌شوند.';

            return [$skirt, $notes];
        }

        if ($difference < 0) {
            $notes[] = 'هشدار: کمر دامن '.$this->fa(round($skirtWaist, 1)).' سانتی‌متر است و از کمر بالاتنه ('
                .$this->fa(round($bodiceWaist, 1)).') کوچک‌تر. پیش از برش، آزادی دامن را زیاد کنید'
                .' یا ساسون بالاتنه را کم؛ این دو لبه همین‌طور به هم نمی‌رسند.';

            return [$skirt, $notes];
        }

        foreach ($skirt as $index => $piece) {
            $edge = $this->waistEdgeOf($piece);

            if ($edge === null) {
                continue;
            }

            // سهمِ این پنل از اختلاف، پیش از ضرب در تکرار. اگر سهم را روی عددِ
            // ضرب‌شده حساب کنیم، پنلی که دولا بریده می‌شود دو برابر سهمش را
            // می‌خورد و کمر دامن از بالاتنه کوچک‌تر درمی‌آید.
            $share = $this->waistOnPiece($piece, $edge, false) / max(0.01, $skirtWaist);

            $skirt[$index] = FullnessRecorder::gathers($piece, $edge, $difference * $share, [
                'label' => 'چین کمر روی بالاتنه',
            ]);
        }

        $notes[] = 'کمر دامن '.$this->fa(round($skirtWaist, 1)).' و کمر بالاتنه '
            .$this->fa(round($bodiceWaist, 1)).' سانتی‌متر است؛ اختلاف '
            .$this->fa(round($difference, 1)).' سانتی‌متر روی کمر چین داده می‌شود.';

        return [$skirt, $notes];
    }

    /** لبهٔ کمری که چین روی آن می‌نشیند (آخرین لبهٔ کمر، نه لبهٔ اضافهٔ رویهم). */
    protected function waistEdgeOf(array $piece): ?int
    {
        $edges = Geometry::edgesWithTag($piece, 'waist');

        return $edges === [] ? null : (int) end($edges);
    }

    /** سهم یک پنل از دور کمرِ تمام‌شده؛ با ضریب تکرار خودش یا بدون آن. */
    protected function waistOnPiece(array $piece, int $edge, bool $repeated = true): float
    {
        $repeats = ! $repeated ? 1 : (! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1)));
        $length = Geometry::edgeLength($piece['outline'], $edge);

        foreach ($piece['darts'] ?? [] as $dart) {
            if (($dart['edge'] ?? null) === null || (int) $dart['edge'] === $edge) {
                $length -= (float) ($dart['intake'] ?? 0);
            }
        }

        foreach ($piece['meta']['gathers'] ?? [] as $gather) {
            if ((int) ($gather['edge'] ?? -1) === $edge) {
                $length -= (float) ($gather['amount'] ?? 0);
            }
        }

        return max(0.0, $length) * $repeats;
    }

    /**
     * دور یک برچسب لبه روی چند قطعه (با احتساب ساسون، چین و تای پارچه).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function edgeGirth(array $pieces, string $tag): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));

            foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
                $length = Geometry::edgeLength($piece['outline'], $edge);

                foreach ($piece['darts'] ?? [] as $dart) {
                    // ساسون کمر روی بالاتنهٔ جذب شمارهٔ لبه ندارد (پایش روی خودِ
                    // لبه نمی‌نشیند، به نوک می‌رود)، ولی دهانه‌اش از همان لبهٔ کمر
                    // خورده می‌شود.
                    $dartEdge = $dart['edge'] ?? null;

                    if ($dartEdge === null ? $tag === 'waist' : (int) $dartEdge === $edge) {
                        $length -= (float) ($dart['intake'] ?? 0);
                    }
                }

                foreach ($piece['meta']['gathers'] ?? [] as $gather) {
                    if ((int) ($gather['edge'] ?? -1) === $edge) {
                        $length -= (float) ($gather['amount'] ?? 0);
                    }
                }

                $total += max(0.0, $length) * $repeats;
            }
        }

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  آستین، بست، آستر
     * ------------------------------------------------------------------- */

    /**
     * آستین، از حلقهٔ اندازه‌گیری‌شدهٔ همین بالاتنه.
     *
     * عدد حلقه از meta.armhole_length خودِ پنل‌ها می‌آید، نه از جدول؛ اگر یقه یا
     * سرشانه عوض شده باشد، آستین هم با همان حلقه درفت می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $bodice
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function dressSleeves(array $measurements, array $ease, array $params, array $bodice, array $g, array $o = []): array
    {
        $armhole = $this->armholeOf($bodice);

        if ($armhole <= 5.0) {
            return [];
        }

        return $this->sleeveSet($measurements, $ease, $params, $armhole, $g, $o);
    }

    /**
     * بست پشت: زیپ یا دکمه، به بلندی‌ای که از باسن رد شود.
     *
     * زیپ پیراهن کمرگرفته باید از پهن‌ترین جای بدن رد شود، وگرنه لباس پوشیده
     * نمی‌شود. بالاتنه در خط کمر تمام می‌شود ولی زیپ روی دامن ادامه پیدا می‌کند،
     * پس طولش از روی بالاتنه به‌اضافهٔ فاصلهٔ کمر تا باسن حساب می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $g
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function dressClosure(array $pieces, array $g, array $params, array $o = []): array
    {
        $mode = (string) $this->param($params, 'back_closure', 'zip');
        $notes = [];

        if ($mode === 'none') {
            $notes[] = 'این پیراهن بست ندارد و از سر پوشیده می‌شود؛ اگر دور سینه یا باسن از دهانهٔ یقه بزرگ‌تر باشد، الگو در پرو جواب نمی‌دهد.';

            return [$pieces, $notes];
        }

        $below = (float) ($o['below'] ?? ($g['hip_drop'] + 4));

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') !== 'back_bodice') {
                continue;
            }

            $marked = $this->markBackZip($piece, $g, null, $below, $mode === 'buttons' ? 'خط دکمهٔ مرکز پشت' : 'زیپ مرکز پشت');
            $length = (float) ($marked['meta']['zip_length'] ?? 0);

            if ($mode === 'buttons' && $length > 0) {
                // دکمه به‌جای زیپ: نشانهٔ زیپ می‌ماند (خط بست همان است) ولی
                // چیزی که خریده می‌شود دکمه است، نه زیپ.
                $marked['meta']['notions'] = array_values(array_filter(
                    $marked['meta']['notions'] ?? [],
                    fn (array $notion) => ($notion['type'] ?? '') !== 'zip',
                ));
                $marked['meta']['notions'][] = [
                    'type' => 'button',
                    'label' => 'دکمهٔ مرکز پشت',
                    'count' => max(5, (int) round($length / 6)),
                    // طول ردیف دکمه همان طول بستِ پشت است؛ بدون این عدد معلوم
                    // نمی‌شود بست از باسن رد می‌شود یا نه
                    'length' => $length,
                ];
                $notes[] = 'مرکز پشت با '.$this->fa(max(5, (int) round($length / 6)))
                    .' دکمه بسته می‌شود؛ جادکمه روی سجاف مرکز پشت می‌افتد، نه روی خودِ درز.';
            } elseif ($length > 0) {
                $notes[] = 'زیپ مرکز پشت '.$this->fa($length).' سانتی‌متر است '
                    .(string) ($o['zip_reason'] ?? 'تا از باسن رد شود؛ زیپ کوتاه‌تر یعنی لباس پوشیده نمی‌شود.');
            }

            $pieces[$index] = $marked;

            break;
        }

        return [$pieces, $notes];
    }

    /**
     * آستر خواسته‌شده، هم‌اندازهٔ قطعهٔ رو.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function dressLining(array $pieces, array $params): array
    {
        $mode = (string) $this->param($params, 'lining', 'none');

        if ($mode === 'none') {
            return $pieces;
        }

        $out = $pieces;

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');
            $role = (string) ($piece['meta']['girth_role'] ?? '');

            if (in_array($role, ['lining', 'lining_skirt', 'trim', 'sleeve'], true)
                || in_array($part, ['lining', 'facing', 'collar', 'belt', 'pocket', 'sleeve', 'waistband'], true)) {
                continue;
            }

            $isBodice = in_array($part, ['front_bodice', 'back_bodice'], true);

            if ($mode === 'bodice' && ! $isBodice) {
                continue;
            }

            $liner = $piece;
            $liner['code'] = ($piece['code'] ?? 'piece').'-lining';
            $liner['name'] = 'آستر '.($piece['name'] ?? '');
            $liner['layer'] = 'lining';
            $liner['meta']['girth_role'] = 'lining';
            $liner['meta']['part'] = 'lining';
            // بست و متعلقات روی قطعهٔ رو خریده شده‌اند، نه دو بار
            unset($liner['meta']['notions'], $liner['meta']['zip_length']);
            $liner['meta']['notes'] = ['هم‌اندازهٔ قطعهٔ رو؛ با هم بریده و با هم دوخته می‌شوند.'];

            $out[] = $liner;
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  کمک‌های کوچک
     * ------------------------------------------------------------------- */

    /**
     * چاک مرکز پشت روی دامن.
     *
     * دامن راستهٔ بلندتر از زانو بدون چاک، قدم برداشتن را ممکن نمی‌کند؛ همین را
     * الگو باید بگوید نه اینکه خیاط سر پرو بفهمد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markBackVent(array $piece, float $length, string $label = 'چاک مرکز پشت'): array
    {
        [, , , $maxY] = Geometry::bounds($piece['outline'] ?? []);
        $length = min($length, max(0.0, $maxY - 10));

        if ($length < 1.0) {
            return $piece;
        }

        $piece['markers'][] = $this->marker('vent', $label, 0, $maxY - $length, 0, $maxY);
        $piece['meta']['vent'] = round($length, 2);
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            $label.' به بلندی '.$this->fa(round($length, 1)).' سانتی‌متر باز می‌ماند.',
        ]);

        return $piece;
    }

    /**
     * بند جدا، دولا بریده‌شده.
     *
     * بلندی بند عمداً بلندتر بریده می‌شود؛ بند چیزی است که در پرو کوتاه می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function dressStrapPiece(string $code, string $name, float $length, float $width, array $o = []): array
    {
        $piece = $this->bandPiece($code, $name, max(8.0, $length), max(1.0, $width * 2), [
            'cut' => (int) ($o['cut'] ?? 2),
            'fold_line' => true,
            'part' => 'strap',
            'meta' => array_merge([
                'strap' => true,
                'finished_width' => round($width, 2),
                'girth_role' => 'trim',
            ], $o['meta'] ?? []),
        ]);

        $piece['meta']['edges'] = ['strap', 'side', 'strap', 'side'];

        return $piece;
    }

    /**
     * برداشتن یک خط نشانهٔ دور از روی قطعه.
     *
     * خط نشانهٔ سینه، کمر و باسن یعنی «اندازهٔ تمام‌شدهٔ لباس روی این خط بدن».
     * برای لباسی که از سرشانه آزاد می‌ریزد و هیچ‌جا به تن نمی‌چسبد، چنین عددی
     * وجود ندارد: پارچه در آن ارتفاع دور بدن نمی‌پیچد، از کنار آن رد می‌شود. پس
     * همان‌طور که عبا و کافتان این کاتالوگ فقط خط سینه دارند، این‌جا هم خط باسن
     * برداشته می‌شود تا کسی لباس چادری را با دور باسنش پرو نکند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function dropMarker(array $piece, string $key): array
    {
        $piece['markers'] = array_values(array_filter(
            $piece['markers'] ?? [],
            fn (array $marker) => ($marker['key'] ?? '') !== $key,
        ));

        return $piece;
    }

    /**
     * برچسب «اریب» روی هر قطعهٔ پوسته.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function cutOnBias(array $pieces): array
    {
        foreach ($pieces as $index => $piece) {
            if (in_array($piece['meta']['girth_role'] ?? '', ['shell', 'skirt', ''], true)) {
                $pieces[$index]['meta']['bias'] = true;
            }
        }

        return $pieces;
    }

    /**
     * یادداشت‌ها روی قطعهٔ اول می‌نشینند تا در کارت فنی و دستور دوخت دیده شوند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function noted(array $pieces, array $notes): array
    {
        $pieces = array_values(array_filter($pieces));

        if ($pieces === [] || $notes === []) {
            return $pieces;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        // «بی‌آستین» یعنی این قطعه اصلاً حلقه ندارد، نه اینکه آستین رویش
        // نگذاشته‌ایم؛ پیراهن بی‌آستین حلقه دارد و حلقه‌اش باید بررسی شود.
        foreach ($pieces as $index => $piece) {
            if (in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                $pieces[$index]['meta']['sleeveless'] = Geometry::edgesWithTag($piece, 'armhole') === [];
            }
        }

        return $pieces;
    }
}
