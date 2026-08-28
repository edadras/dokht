<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use InvalidArgumentException;

/**
 * پایه مشترک لباس‌های شب، مجلسی و عروس.
 *
 * این خانواده از بقیهٔ کاتالوگ یک تفاوت ساختاری دارد: تقریباً همه‌شان از دو
 * لباس ساخته شده‌اند که در خط کمر به هم می‌رسند — یک بالاتنه و یک دامن. پس
 * به‌جای درفت دوبارهٔ دامن، دامن از همان کاتالوگ سی‌ودو تایی قرض گرفته می‌شود
 * و این کلاس کار سختِ واقعی را می‌کند: **رساندن دو کمر به هم**.
 *
 * همان‌جا هم بیشترین خطای این لباس‌ها رخ می‌دهد. بالاتنه دور کمرش را از بدن و
 * ساسون می‌گیرد و دامن دور کمرش را از بدن و آزادی خودش؛ این دو خودبه‌خود یکی
 * نمی‌شوند. اگر کسی حواسش نباشد، لباس روی کاغذ درست است و روی پارچه دوخته
 * نمی‌شود. این‌جا اختلاف اندازه‌گیری می‌شود و یکی از سه راه انتخاب:
 *
 *   چین      اگر دامن پُرتر از کمر باشد (بیشتر لباس‌های مجلسی)
 *   ساسون    اگر اختلاف کم باشد و مدل جذب
 *   گزارش    اگر اختلاف بیش از حد باشد، عدد گفته می‌شود نه پنهان
 *
 * سه چیز دیگر هم مشترک است و در همهٔ مدل‌ها هست: آستر (لباس مجلسی بی‌آستر
 * وجود ندارد)، بستِ پشت که باید از باسن رد شود، و تیغهٔ فنر روی درزهای بالاتنهٔ
 * بی‌بند.
 */
abstract class EveningBaseGenerator extends TopBaseGenerator
{
    public static function group(): string
    {
        return 'evening';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک این خانواده.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function eveningSchema(array $extra = [], array $defaults = []): array
    {
        $schema = array_merge([
            'neckline' => [
                'label' => 'خط بالای بالاتنه', 'type' => 'select', 'default' => 'sweetheart',
                'options' => [
                    'sweetheart' => 'قلبی (بی‌بند)',
                    'straight' => 'صاف (بی‌بند)',
                    'v' => 'هفت',
                    'scoop' => 'گرد',
                    'strap' => 'بنددار',
                    'halter' => 'هالتر',
                ],
            ],
            'strap_width' => [
                'label' => 'پهنای بند (اگر بنددار است)', 'min' => 1, 'max' => 8, 'step' => 0.5,
                'default' => 3, 'unit' => 'سانتی‌متر',
            ],
            'bodice_length' => [
                'label' => 'جای خط کمر لباس', 'type' => 'select', 'default' => 'natural',
                'options' => [
                    'empire' => 'زیر سینه (امپایر)',
                    'natural' => 'خط کمر طبیعی',
                    'dropped' => 'کمر افتاده (روی باسن)',
                ],
            ],
            'closure' => [
                'label' => 'بست پشت', 'type' => 'select', 'default' => 'zip',
                'options' => [
                    'zip' => 'زیپ مخفی مرکز پشت',
                    'lacing' => 'بند کشی (لِیس)',
                    'buttons' => 'دکمهٔ مروارید روی زیپ',
                ],
            ],
            'lining' => [
                'label' => 'آستر', 'type' => 'select', 'default' => 'full',
                'options' => ['full' => 'کامل', 'bodice' => 'فقط بالاتنه', 'none' => 'ندارد'],
            ],
            'boning' => [
                'label' => 'تیغهٔ فنر روی درزهای بالاتنه', 'type' => 'toggle', 'default' => true,
            ],
            'bust_cups' => [
                'label' => 'جای فنجان سینه', 'type' => 'toggle', 'default' => true,
            ],
        ], $extra);

        foreach ($defaults as $key => $value) {
            if (isset($schema[$key])) {
                $schema[$key]['default'] = $value;
            }
        }

        return $schema;
    }

    /**
     * آزادی مشترک بالاتنه و دامن.
     *
     * این مهم‌ترین کار این کلاس است و اگر نباشد، لباس دوخته نمی‌شود: بالاتنه و
     * دامن باید با *یک* آزادی کمر درفت شوند. اگر هرکدام آزادی پیش‌فرض خودش را
     * بگیرد، دو کمر با چند سانتی‌متر اختلاف درمی‌آیند و هیچ‌کس تا لحظهٔ دوخت
     * نمی‌فهمد.
     *
     * آزادی لباس مجلسی هم عمداً کم است؛ این لباس‌ها اندام‌نما هستند و آزادی
     * زیاد یعنی چروک روی کمر.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function gownEase(array $ease, array $params): array
    {
        $extra = match ((string) $this->param($params, 'fit', 'regular')) {
            'fitted' => 0.0,
            'loose' => 4.0,
            default => 2.0,
        };

        return array_merge($ease, [
            'bust' => 4.0 + $extra,
            'waist' => 2.0 + $extra,
            'hip' => 3.0 + $extra,
        ]);
    }

    /** پارامتر قد دامن لباس. */
    protected function gownLengthParam(float $default = 110, string $label = 'بلندی دامن از خط کمر'): array
    {
        return [
            'skirt_length' => [
                'label' => $label, 'min' => 40, 'max' => 145, 'step' => 1,
                'default' => $default, 'unit' => 'سانتی‌متر',
                'hint' => 'از خط کمر تا دم؛ برای لباس بلند تا کف، قد کمر تا زمین را اندازه بگیرید.',
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  بالاتنه
     * ------------------------------------------------------------------- */

    /**
     * بالاتنهٔ لباس مجلسی: جلو، پشت و آنچه با آن‌ها می‌آید.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float} قطعه‌ها و دور کمرِ بالاتنه
     */
    protected function eveningBodice(array $measurements, array $ease, array $params, array $o = []): array
    {
        // «فرم لباس» یک بار در gownEase حساب شده؛ اگر این‌جا دوباره grow اضافه
        // شود، بالاتنه از دامن پهن‌تر درمی‌آید و دو کمر دیگر به هم نمی‌رسند.
        $grow = 0.0;
        $g = $this->blockMetrics($measurements, $ease, $params);

        $neckline = (string) $this->param($params, 'neckline', 'sweetheart');
        $strapless = in_array($neckline, ['sweetheart', 'straight'], true);
        $strap = (float) $this->param($params, 'strap_width', 3);
        $prefix = (string) ($o['prefix'] ?? 'gown');

        // جای خط کمر لباس: امپایر، طبیعی، یا افتاده
        $waistPlace = (string) $this->param($params, 'bodice_length', 'natural');
        $length = match ($waistPlace) {
            'empire' => -max(4.0, ((float) ($g['side_waist_y'] ?? 40) - (float) ($g['bust_y'] ?? 20)) * 0.55),
            'dropped' => (float) ($g['hip_drop'] ?? 20) * 0.6,
            default => 0.0,
        };

        $shared = [
            'shape' => 'fitted',
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'waist',
            'waist_dart' => true,
        ];

        if (! $strapless) {
            $shared['shoulder_extra'] = ($g['neck_width'] + $strap) - $g['shoulder_half'];
            $shared['across_extra'] = -min(4.0, $strap * 0.5);
            $shared['armhole_drop'] = 2.0;
        }

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => $prefix.'-bodice-front',
            'name' => 'بالاتنه جلو',
            'bust_dart' => true,
            'neck_depth_extra' => $strapless ? 0 : (float) ($o['neck_drop'] ?? 6),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => $prefix.'-bodice-back',
            'name' => 'بالاتنه پشت',
            'neck_depth_extra' => $strapless ? 0 : (float) ($o['back_drop'] ?? 8),
        ]));

        if ($strapless) {
            // خط بالای بی‌بند: هر دو نیمه در یک ارتفاع به درز پهلو می‌رسند،
            // وگرنه دو درز پهلو هم‌اندازه نمی‌مانند
            $bustY = (float) ($front['meta']['bust_y'] ?? 20);
            $top = max(2.0, $bustY - (float) ($o['top_drop'] ?? 1.5));

            $front = $this->cutTop($front, [
                'center' => $top,
                'side' => $top,
                'shape' => $neckline === 'sweetheart' ? 'sweetheart' : 'straight',
                'apex' => 0.6,
            ]);

            $back = $this->cutTop($back, ['center' => $top, 'side' => $top, 'shape' => 'straight']);
        }

        $waist = $this->edgeGirth([$front, $back], 'waist');

        $pieces = [$front, $back];

        if ($this->flag($params, 'boning', true) && $strapless) {
            foreach ($pieces as $index => $piece) {
                $pieces[$index]['meta']['boning'] = true;
                $pieces[$index]['meta']['interfacing'] = true;
            }
        }

        if ($this->flag($params, 'bust_cups', true)) {
            // «آستر» نیست: جیب فنجان یک نوار روی آستر است. اگر نقشش را آستر
            // بگذاریم، لباسِ بی‌آستر هم به نظر آستردار می‌آید.
            $pieces[] = $this->bandPiece($prefix.'-cup-pocket', 'جیب فنجان سینه', 34, 8, [
                'cut' => 1, 'part' => 'cup_pocket',
                'meta' => [
                    'girth_role' => 'lining',
                    'notes' => ['روی آستر بالاتنه دوخته می‌شود؛ یک طرفش باز می‌ماند تا فنجان درآید.'],
                ],
            ]);
        }

        return [$pieces, $waist];
    }

    /* ---------------------------------------------------------------------
     |  دامن
     * ------------------------------------------------------------------- */

    /**
     * دامن لباس، از همان کاتالوگ دامن.
     *
     * چیزی از نو درفت نمی‌شود: کلوش، ترک‌دار، ماهی، طبقه‌ای و پرنسسی همه از
     * پیش هستند و آزموده شده‌اند. این‌جا فقط نامشان عوض می‌شود و در meta معلوم
     * می‌شود که بخشی از یک لباس‌اند، نه دامن مستقل.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function gownSkirt(string $key, array $measurements, array $ease, array $overrides = [], string $prefix = 'gown'): array
    {
        if (! GeneratorRegistry::has($key)) {
            return [];
        }

        $generator = GeneratorRegistry::make($key);
        $defaults = $generator->defaultParams();

        // نامِ پارامتری که این دامن نمی‌شناسد در array_merge بی‌صدا گم می‌شود و
        // لباس با تنظیمات پیش‌فرض دامن ساخته می‌شود — بی آنکه کسی خبردار شود.
        $unknown = array_diff(array_keys($overrides), array_keys($defaults));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'دامن «'.$key.'» پارامتر «'.implode('، ', $unknown).'» را نمی‌شناسد.'
            );
        }

        $params = array_merge($defaults, array_merge([
            // کمربند دامن لازم نیست: خط کمرِ لباس به بالاتنه دوخته می‌شود
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
            $piece['meta']['gown_skirt'] = true;

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

    /**
     * رساندن کمر دامن به کمر بالاتنه.
     *
     * دو عدد اندازه گرفته می‌شود و اختلافشان یا با چین حل می‌شود یا گزارش.
     * پنهان کردن این اختلاف یعنی لباسی که روی کاغذ درست است و دوخته نمی‌شود.
     *
     * فقط لایهٔ رو در این حساب می‌آید. زیردامنی و آستر از نوار کمرِ خودشان
     * آویزان‌اند و چین خودشان را دارند؛ اگر دور کمرشان جزو کمر دامن شمرده شود،
     * اختلاف چند برابر واقعی درمی‌آید و لایهٔ رو تا نصف جمع می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $skirt
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function joinWaist(array $skirt, float $bodiceWaist): array
    {
        $shell = array_filter($skirt, fn (array $piece) => $this->joinsTheBodice($piece));
        $skirtWaist = $this->edgeGirth($shell, 'waist');
        $notes = [];

        if ($skirtWaist < 1.0 || $bodiceWaist < 1.0) {
            return [$skirt, $notes];
        }

        $difference = $skirtWaist - $bodiceWaist;

        if (abs($difference) < 1.0) {
            $notes[] = 'کمر دامن و کمر بالاتنه هر دو '.$this->fa(round($bodiceWaist, 1))
                .' سانتی‌متر است و مستقیم به هم دوخته می‌شوند.';

            return [$skirt, $notes];
        }

        if ($difference > 0) {
            // دامن پُرتر است: همان اضافه چین می‌خورد.
            //
            // سهم هر قطعه به نسبت سهمش از دور کمر است، نه «تقسیم بر تعداد قطعه‌ها»:
            // یک پنل روی تای پارچه دو برابر خودش کمر می‌دهد و اگر همان‌قدر چین
            // بگیرد که یک پنل تک‌لا، دو برابر لازم جمع می‌شود و کمر دامن از کمر
            // بالاتنه کوچک‌تر درمی‌آید. مقدارِ ثبت‌شده روی هر قطعه هم برای یک برش
            // است، پس ضریب تکرار در آن ضرب نمی‌شود.
            $shares = [];
            $total = 0.0;

            foreach ($shell as $index => $piece) {
                $edges = Geometry::edgesWithTag($piece, 'waist');

                if ($edges === []) {
                    continue;
                }

                $length = 0.0;

                foreach ($edges as $edge) {
                    $length += Geometry::edgeLength($piece['outline'], $edge);
                }

                $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));

                $shares[$index] = ['edge' => $edges[0], 'length' => $length];
                $total += $length * $repeats;
            }

            if ($total < 0.01) {
                return [$skirt, $notes];
            }

            foreach ($shares as $index => $share) {
                $skirt[$index]['meta']['gathers'][] = [
                    'edge' => $share['edge'],
                    'amount' => round($difference * ($share['length'] / $total), 2),
                    'label' => 'چین کمر روی بالاتنه',
                ];
            }

            $notes[] = 'کمر دامن '.$this->fa(round($skirtWaist, 1)).' و کمر بالاتنه '
                .$this->fa(round($bodiceWaist, 1)).' سانتی‌متر است؛ اختلاف '
                .$this->fa(round($difference, 1)).' سانتی‌متر روی کمر چین داده می‌شود.';

            return [$skirt, $notes];
        }

        $notes[] = 'هشدار: کمر دامن '.$this->fa(round($skirtWaist, 1)).' سانتی‌متر است و از کمر بالاتنه ('
            .$this->fa(round($bodiceWaist, 1)).') کوچک‌تر. پیش از دوخت، آزادی دامن را زیاد کنید'
            .' یا ساسون بالاتنه را کم؛ این دو لبه همین‌طور به هم نمی‌رسند.';

        return [$skirt, $notes];
    }

    /**
     * آیا این قطعه همان لایه‌ای است که به کمر بالاتنه دوخته می‌شود؟
     *
     * آستر، زیردامنی و نوارها کمر خودشان را دارند و در این حساب نمی‌آیند.
     *
     * @param  array<string, mixed>  $piece
     */
    protected function joinsTheBodice(array $piece): bool
    {
        $part = (string) ($piece['meta']['part'] ?? '');
        $role = (string) ($piece['meta']['girth_role'] ?? 'shell');

        return $role === 'shell'
            && ! in_array($part, ['lining', 'binding', 'waistband', 'petticoat'], true)
            && ($piece['layer'] ?? 'outer') !== 'lining';
    }

    /**
     * دور یک برچسب لبه روی چند قطعه (با احتساب تای پارچه و تعداد برش).
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
                    // خورده می‌شود. اگر فقط ساسون‌های شماره‌دار کم شوند، کمرِ
                    // اندازه‌گیری‌شده به‌اندازهٔ کل ساسون‌ها بزرگ‌تر درمی‌آید.
                    $dartEdge = $dart['edge'] ?? null;

                    if ($dartEdge === null ? $tag === 'waist' : (int) $dartEdge === $edge) {
                        $length -= (float) ($dart['intake'] ?? 0);
                    }
                }

                // چینی که خودِ دامن روی کمرش ثبت کرده هم پیش از دوخت بسته می‌شود؛
                // بدون کم کردنش، کمرِ دامن به‌اندازهٔ پارچهٔ چین بزرگ‌تر شمرده
                // می‌شود و مقایسه با بالاتنه بی‌معنا می‌شود.
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
     |  آستر، بست و دنباله
     * ------------------------------------------------------------------- */

    /**
     * آستر خواسته‌شده.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function gownLining(array $pieces, array $params): array
    {
        $mode = (string) $this->param($params, 'lining', 'full');

        if ($mode === 'none') {
            return $pieces;
        }

        $out = $pieces;

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');
            $role = (string) ($piece['meta']['girth_role'] ?? '');

            if (in_array($role, ['lining', 'trim'], true) || in_array($part, ['lining', 'facing'], true)) {
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
            $liner['meta']['boning'] = false;
            $liner['meta']['notes'] = ['هم‌اندازهٔ قطعهٔ رو؛ با هم بریده و با هم دوخته می‌شوند.'];

            $out[] = $liner;
        }

        return $out;
    }

    /**
     * بست پشت: زیپ، بند کشی، یا دکمهٔ مروارید روی زیپ.
     *
     * زیپ لباس مجلسی باید از باسن رد شود، وگرنه لباس پوشیده نمی‌شود؛ طولش از
     * روی خودِ قطعه‌ها حساب می‌شود، نه از عددی ثابت.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function gownClosure(array $pieces, array $params, array $measurements): array
    {
        $mode = (string) $this->param($params, 'closure', 'zip');
        $notes = [];

        $bodice = 0.0;

        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') === 'back_bodice') {
                $bodice = max($bodice, Geometry::height($piece['outline']));
            }
        }

        $hipDrop = (float) ($measurements['waist_to_hip'] ?? 21);
        $length = round($bodice + $hipDrop + 4, 1);

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') !== 'back_bodice') {
                continue;
            }

            $pieces[$index]['on_fold'] = false;
            $pieces[$index]['cut_quantity'] = 2;
            $pieces[$index]['mirror'] = true;
            $pieces[$index]['meta']['fold_edges'] = [];

            $pieces[$index]['meta']['notions'][] = match ($mode) {
                'lacing' => ['type' => 'eyelet', 'label' => 'حلقهٔ بند کشی مرکز پشت', 'count' => 24],
                default => ['type' => 'zip', 'label' => 'زیپ مخفی مرکز پشت', 'length' => $length],
            };

            if ($mode === 'buttons') {
                $pieces[$index]['meta']['notions'][] = [
                    'type' => 'button',
                    'label' => 'دکمهٔ مروارید روی زیپ',
                    'count' => max(8, (int) round($length / 3)),
                ];
            }

            break;
        }

        $notes[] = match ($mode) {
            'lacing' => 'مرکز پشت با بند کشی بسته می‌شود؛ دو تا سه سانتی‌متر فاصله بین دو لبه طبیعی است و باید پشت‌بند داشته باشد.',
            'buttons' => 'دکمه‌های مروارید روی زیپ می‌نشینند و کارشان تزیینی است؛ خودِ لباس با زیپ بسته می‌شود.',
            default => 'زیپ مرکز پشت '.$this->fa($length).' سانتی‌متر است تا از باسن رد شود؛ زیپ کوتاه‌تر یعنی لباس پوشیده نمی‌شود.',
        };

        return [$pieces, $notes];
    }

    /** یادداشت‌های همیشگی این خانواده. */
    protected function gownNotes(array $params): array
    {
        $notes = [];

        if ($this->flag($params, 'boning', true)) {
            $notes[] = 'روی درزهای بالاتنه تیغهٔ فنر می‌نشیند؛ بلندی هر تیغه دو سانتی‌متر کمتر از خود درز بریده می‌شود تا سرش بیرون نزند.';
        }

        if ((string) $this->param($params, 'lining', 'full') === 'none') {
            $notes[] = 'هشدار: این لباس آستر ندارد. لباس مجلسی بی‌آستر روی تن می‌چسبد و درزهایش از رو دیده می‌شود.';
        }

        return $notes;
    }
}
