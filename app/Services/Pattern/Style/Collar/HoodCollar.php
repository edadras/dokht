<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * کلاه (یقه کلاه‌دار).
 *
 * کلاه هم یقه است: لبه پایینش روی همان خط یقه دوخته می‌شود و باید دقیقاً به
 * اندازه آن باشد. تفاوتش با بقیه یقه‌ها این است که باید دور سر هم جا بدهد، پس
 * دو اندازه دارد که هر دو باید بخوانند: لبه گردن (که به خط یقه می‌خورد) و
 * دهانه صورت با بلندی و پهنای کلاه (که باید از سر رد شود).
 *
 * دو ساخت رایج:
 *
 *   دوتکه — دو نیمه قرینه که از لبه گردن تا جلوی صورت به هم دوخته می‌شوند. درز
 *           از پشت گردن تا بالای پیشانی می‌رود و ساده‌ترین کلاه است.
 *   سه‌تکه — همان دو نیمه، ولی میانشان یک نوار (ترک) می‌افتد. کلاه گردتر و
 *           جادارتر می‌شود و روی سر صاف می‌ایستد؛ نوار باید دقیقاً هم‌اندازه درز
 *           نیمه‌ها باشد، وگرنه کلاه کج می‌نشیند. این‌جا با پیاده‌کردن سنجیده می‌شود.
 *
 * اگر لباس چاک جلو نداشته باشد، سر باید از خود خط یقه رد شود؛ در آن حالت اگر
 * دور خط یقه از دور سر کمتر باشد، کلاه پذیرفته نمی‌شود.
 */
class HoodCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_hood';
    }

    public function label(): string
    {
        return 'کلاه';
    }

    public function description(): string
    {
        return 'کلاه دوتکه یا سه‌تکه با ترک میانی، با بندکش دلخواه دور صورت.';
    }

    public function paramsSchema(): array
    {
        return [
            'panels' => [
                'label' => 'تعداد تکه', 'type' => 'select', 'default' => '2',
                'options' => ['2' => 'دوتکه (درز میانی)', '3' => 'سه‌تکه (با ترک میانی)'],
            ],
            'height' => [
                'label' => 'بلندی کلاه', 'min' => 24, 'max' => 46, 'step' => 0.5, 'default' => 34,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه تا بالای سر، با آزادی.',
            ],
            'width' => [
                'label' => 'پهنای کلاه', 'min' => 18, 'max' => 38, 'step' => 0.5, 'default' => 26,
                'unit' => 'سانتی‌متر', 'hint' => 'از جلوی صورت تا پشت سر.',
            ],
            'gusset' => [
                'label' => 'پهنای ترک میانی', 'min' => 4, 'max' => 16, 'step' => 0.5, 'default' => 9,
                'unit' => 'سانتی‌متر', 'hint' => 'فقط در کلاه سه‌تکه.',
            ],
            'face_finish' => [
                'label' => 'تمام‌کردن دهانه صورت', 'type' => 'select', 'default' => 'facing',
                'options' => ['none' => 'بدون قطعه', 'facing' => 'سجاف', 'binding' => 'نوار مورب'],
            ],
            'face_width' => [
                'label' => 'پهنای سجاف یا نوار دهانه', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4.5,
                'unit' => 'سانتی‌متر',
            ],
            'drawstring' => [
                'label' => 'بندکش دور صورت', 'type' => 'toggle', 'default' => false,
                'hint' => 'دو سوراخ مته روی هر نیمه، برای گذراندن بند.',
            ],
            'ease' => $this->easeField(),
        ];
    }

    protected function noNeckMessage(): string
    {
        return 'کلاه روی خط یقه دوخته می‌شود و این لباس خط یقه ندارد؛ روی دامن یا پایین‌تنه جایی برای دوختن کلاه نیست.';
    }

    protected function supportsCollar(array $pieces, array $context): true|string
    {
        if ($this->frontOpening($pieces, $context)) {
            return true;
        }

        $neckline = $this->measureNeckline($pieces)['full'];
        $head = $this->headGirth($context);

        if ($neckline + 1.0 < $head) {
            return 'این لباس چاک جلو ندارد و باید سر از خود خط یقه رد شود، ولی دور خط یقه '
                .Format::cm($neckline).' است در برابر دور سر '.Format::cm($head)
                .'؛ کلاه دوخته می‌شود ولی پوشیده نمی‌شود. اول خط یقه را بازتر کنید یا چاک جلو بگذارید.';
        }

        return true;
    }

    /** دور سر: از اندازه‌ها اگر باشد، وگرنه از دور گردن تخمین زده می‌شود. */
    protected function headGirth(array $context): float
    {
        $measurements = is_array($context['measurements'] ?? null) ? $context['measurements'] : [];

        foreach (['head', 'head_girth'] as $key) {
            if (is_numeric($measurements[$key] ?? null) && (float) $measurements[$key] > 30) {
                return round((float) $measurements[$key], 1);
            }
        }

        $neck = (float) ($measurements['neck'] ?? 0);

        return round($neck > 20 ? $neck * 1.55 : 56.0, 1);
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $panels = (string) $p['panels'] === '3' ? 3 : 2;
        $gusset = $panels === 3 ? (float) $p['gusset'] : 0.0;
        $height = (float) $p['height'];
        $width = (float) $p['width'];
        $head = $this->headGirth($context);

        // در کلاه سه‌تکه، نیمی از پهنای ترک از سهم هر نیمه کم می‌شود
        $target = max(6.0, $neck['half'] + (float) $p['ease'] - ($gusset / 2));
        $panelWidth = max(10.0, $width - ($gusset / 2));

        [$panel, $length, $difference] = $this->fitToNeckline(
            fn (float $run) => $this->side($run, $panelWidth, $height, $panels, ! empty($p['drawstring'])),
            $target,
        );

        $panel = $this->neckNotches($panel, [
            ['at' => max(0.0, min($length - 0.5, $neck['back'] - ($gusset / 2))), 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
            ['at' => $length, 'label' => 'مرکز جلو', 'pair' => 'center_front'],
        ]);

        $face = $this->seamOf($panel, 'hem');
        $seam = $this->seamOf($panel, 'default');
        $panel['meta']['front_opening'] = round($face, 2);
        $panel['meta']['centre_seam'] = round($seam, 2);

        $made = [$panel];
        $notes = [];
        $meta = [
            'target' => round($target, 2),
            'measured' => $length,
            'ease' => round((float) $p['ease'], 2),
            'difference' => $difference,
            'panels' => $panels,
            'height' => $height,
            'width' => $width,
            'face_opening' => round($face, 2),
            'face_opening_total' => round(($face * 2) + $gusset, 2),
            'centre_seam' => round($seam, 2),
            'head' => $head,
        ];

        if ($panels === 3) {
            $strip = $this->gussetPiece($gusset, $seam);
            $walk = PieceOps::walk($panel, 'default', $strip, 'side', ['tolerance' => static::NECK_TOLERANCE]);
            $made[] = $strip;
            $meta['gusset'] = $gusset;
            $meta['gusset_length'] = round($this->seamOf($strip, 'side'), 2);
            $meta['gusset_difference'] = $walk['difference'];

            $notes[] = 'ترک میانی '.Format::cm($gusset).' پهنا و '.Format::cm((float) $meta['gusset_length'])
                .' بلندی دارد و با درز نیمه‌ها '.Format::cm(abs((float) $walk['difference']))
                .' اختلاف دارد؛ اگر بیشتر از یک‌دهم شود کلاه کج می‌نشیند.';
            $notes[] = 'ترک از پشت گردن شروع می‌شود، از روی سر رد می‌شود و به جلوی پیشانی می‌رسد؛'
                .' سر پایینی ترک روی خط یقه دوخته می‌شود، پس '.Format::cm($gusset).' از دور خط یقه سهم اوست.';
        } else {
            $notes[] = 'دو نیمه از پشت گردن تا جلوی پیشانی به هم دوخته می‌شوند؛ درز میانی هر نیمه '
                .Format::cm($seam).' است.';
        }

        $notes[] = 'دهانه صورت هر نیمه '.Format::cm($face).' است، پس دور کامل دهانه '
            .Format::cm((float) $meta['face_opening_total']).' می‌شود؛ لبه گردن همان '.Format::cm($length)
            .' است که روی خط یقه می‌نشیند.';
        $notes[] = 'کلاه '.Format::cm($height).' بلندی و '.Format::cm($width)
            .' پهنا دارد در برابر دور سر '.Format::cm($head)
            .'؛ پهنای کلاه باید دست‌کم به اندازه نصف دور سر باشد وگرنه روی سر می‌کشد.';

        if (! empty($p['drawstring'])) {
            $notes[] = 'دو سوراخ بندکش روی هر نیمه، کمی بالاتر از خط یقه و کنار دهانه صورت علامت خورد؛'
                .' پیش از دوختن سجاف، پشت سوراخ‌ها را لایه بچسبانید و پرچ بزنید.';
        }

        $finish = (string) $p['face_finish'];

        if ($finish === 'facing') {
            $facing = $this->faceFacing($panel, (float) $p['face_width']);

            if ($facing === null) {
                $notes[] = 'سجاف دهانه صورت با این پهنا روی خودش می‌افتد و ساخته نشد؛ پهنا را کمتر بگیرید.';
            } else {
                $made[] = $facing;
                $notes[] = 'سجاف دهانه صورت به پهنای '.Format::cm((float) $p['face_width'])
                    .' ساخته شد؛ اگر بندکش دارید، کانال بند از همین سجاف درست می‌شود.';
            }
        } elseif ($finish === 'binding') {
            $made[] = $this->faceBinding($face * 2, (float) $p['face_width']);
            $notes[] = 'دهانه صورت با نوار مورب تمام می‌شود؛ نوار ۳٪ کوتاه‌تر از دهانه بریده شد تا لبه جمع بنشیند.';
        }

        return ['pieces' => $made, 'notes' => $notes, 'meta' => $meta];
    }

    /**
     * یک نیمه کلاه.
     *
     * $run دستگیره طول لبه گردن است: هرچه بیشتر، لبه گردن بلندتر.
     *
     * @return array<string, mixed>
     */
    protected function side(float $run, float $width, float $height, int $panels, bool $drawstring): array
    {
        $run = max(6.0, min($width * 1.9, $run));
        $frontX = $width - $run;
        $faceTop = $height * 0.22;

        $backNeck = Geometry::point($width, $height - 1.2);
        $frontNeck = Geometry::curve($frontX, $height, ($width + $frontX) / 2, $height + 0.9);
        $faceTopPoint = Geometry::curve($frontX * 0.12, $faceTop, $frontX * 0.05, $height * 0.58);
        $crown = Geometry::curve($width, 0, $width * 0.42, -$height * 0.13);

        $outline = [$backNeck, $frontNeck, $faceTopPoint, $crown];
        $edges = ['neck', 'hem', 'default', 'default'];

        $drills = [];

        if ($drawstring) {
            $on = Geometry::pointOnEdge($outline, 1, 0.12);
            $drills[] = $this->drill($on['x'] + 2.2, $on['y'] - 1.0, 'سوراخ بندکش بالا', ['type' => 'eyelet']);
            $on = Geometry::pointOnEdge($outline, 1, 0.04);
            $drills[] = $this->drill($on['x'] + 2.2, $on['y'] - 1.0, 'سوراخ بندکش پایین', ['type' => 'eyelet']);
        }

        return $this->newPiece([
            'code' => 'hood-side',
            'name' => $panels === 3 ? 'نیمه کلاه (سه‌تکه)' : 'نیمه کلاه',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.7, $height * 0.2, $height * 0.8),
            'drills' => $drills,
            'markers' => [
                $this->marker('face', 'دهانه صورت', $frontX, $height, $frontX * 0.12, $faceTop),
            ],
            'meta' => [
                'part' => 'hood',
                'edges' => $edges,
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'hood',
                'panels' => $panels,
                'hood_height' => round($height, 2),
                'hood_width' => round($width, 2),
            ],
        ]);
    }

    /**
     * ترک میانی کلاه سه‌تکه.
     *
     * @return array<string, mixed>
     */
    protected function gussetPiece(float $width, float $length): array
    {
        $width = max(2.0, $width);
        $length = max(10.0, $length);

        return $this->newPiece([
            'code' => 'hood-gusset',
            'name' => 'ترک میانی کلاه',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $length),
                Geometry::point(0, $length),
            ],
            'grainline' => $this->grainline($width / 2, 1.0, $length - 1.0),
            'notches' => [
                $this->notch($width / 2, 0, 0, 'سر گردن ترک', 'hood_neck'),
                $this->notch($width / 2, $length, 2, 'سر پیشانی ترک', 'hood_face'),
            ],
            'meta' => [
                'part' => 'hood',
                // یک پهلو برچسب «side» دارد و پهلوی روبه‌رو «default»، تا اندازه‌گیری
                // درز ترک یک بار شمرده شود نه دو بار
                'edges' => ['neck', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'hood_gusset',
                'seam_length' => round($length, 2),
            ],
        ]);
    }

    /**
     * سجاف دهانه صورت: کپی لبه دهانه که به اندازه پهنا به داخل کلاه رفته.
     *
     * @param  array<string, mixed>  $panel
     * @return array<string, mixed>|null
     */
    protected function faceFacing(array $panel, float $width): ?array
    {
        $edge = $this->edgeWithTag($panel, 'hem');

        if ($edge === null || $width < 1.5) {
            return null;
        }

        $outline = array_values($panel['outline']);
        $path = [];

        for ($step = 0; $step <= 10; $step++) {
            $on = Geometry::pointOnEdge($outline, $edge, $step / 10);

            if ($path === [] || Geometry::distance($path[count($path) - 1], $on) > 0.1) {
                $path[] = ['x' => $on['x'], 'y' => $on['y']];
            }
        }

        if (count($path) < 3) {
            return null;
        }

        $inner = $this->cleanOffset($path, $this->offsetPath($path, -$width, Geometry::centroid($outline)));
        $points = [];
        $tags = [];

        foreach ($path as $point) {
            $points[] = Geometry::point((float) $point['x'], (float) $point['y']);
            $tags[] = 'hem';
        }

        $tags[count($tags) - 1] = 'side';

        foreach (array_reverse($inner) as $point) {
            $points[] = Geometry::point((float) $point['x'], (float) $point['y']);
            $tags[] = 'default';
        }

        $facing = $this->newPiece([
            'code' => 'hood-face-facing',
            'name' => 'سجاف دهانه کلاه',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $points,
            'meta' => [
                'part' => 'facing',
                'facing_for' => $panel['code'],
                'edges' => $tags,
                'fold_edges' => [],
                'interfacing' => false,
                'width' => round($width, 2),
                'collar_kind' => 'hood',
            ],
        ]);

        if (Geometry::validatePiece($facing, 6.0) !== []) {
            return null;
        }

        $bounds = Geometry::bounds($facing['outline']);
        $facing['grainline'] = $this->grainline(
            $bounds[0] + (($bounds[2] - $bounds[0]) * 0.5),
            $bounds[1] + 1.0,
            $bounds[3] - 1.0,
        );

        return $facing;
    }

    /**
     * نوار مورب دهانه صورت.
     *
     * @return array<string, mixed>
     */
    protected function faceBinding(float $opening, float $width): array
    {
        $length = round(max(20.0, $opening * 0.97), 2);
        $height = round(max(1.5, $width) * 2, 2);
        $shell = $this->strip($length, $height);
        $shell['edges'] = ['hem', 'side', 'default', 'side'];

        $piece = $this->newPiece([
            'code' => 'hood-face-binding',
            'name' => 'نوار مورب دهانه کلاه',
            'cut_quantity' => 1,
            'outline' => $shell['outline'],
            'markers' => [
                $this->marker('fold', 'خط تای نوار', 0, $height / 2, $length, $height / 2),
            ],
            'meta' => [
                'part' => 'binding',
                'edges' => $shell['edges'],
                'fold_edges' => [],
                'bias' => true,
                'collar_kind' => 'hood',
                'opening_length' => round($opening, 2),
                'note' => 'نوار روی مورب پارچه (۴۵ درجه) بریده شود.',
            ],
        ]);

        $piece['grainline'] = $this->grainlineBetween(
            ['x' => 1.0, 'y' => $height - 1.0],
            ['x' => 1.0 + $height - 2.0, 'y' => 1.0],
            'مورب ۴۵ درجه',
        );

        return $piece;
    }
}
