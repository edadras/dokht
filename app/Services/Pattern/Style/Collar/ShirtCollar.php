<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * یقه پیراهنی دوتکه: پایه و رویه.
 *
 * چرا دوتکه؟ چون یک تکه پارچه نمی‌تواند هم‌زمان دو کار متضاد بکند. پایه یقه
 * باید به بالا تنگ شود تا دور گردن بایستد (لبه بالایش کوتاه‌تر از لبه یقه)، و
 * رویه باید به بیرون باز شود تا روی پایه بخوابد (لبه بیرونی‌اش بلندتر از لبه
 * چسبیده به پایه). این دو خواسته در یک قطعه جمع نمی‌شوند، پس یقه دوتکه می‌شود
 * و درز میان پایه و رویه همان «خط خواب یقه» است.
 *
 * ترتیب درفت همان ترتیب خیاط است:
 *
 *   ۱. پایه به اندازه نیم خط یقه درفت می‌شود، با «بالا آمدن جلو» که آن را جمع کند.
 *   ۲. لبه بالای پایه اندازه گرفته می‌شود؛ این عدد، نه نیم‌یقه، اندازه رویه است.
 *   ۳. رویه به اندازه همان لبه بالا درفت می‌شود و «گشادی» به لبه بیرونی‌اش داده
 *      می‌شود تا روی پایه بخوابد و نکشد.
 *
 * پس همیشه لبه بالای پایه از لبه بیرونی رویه کوتاه‌تر است؛ اگر نبود، یقه
 * برنمی‌گردد و روی گردن می‌ماند.
 */
class ShirtCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_shirt';
    }

    public function label(): string
    {
        return 'یقه پیراهنی دوتکه';
    }

    public function description(): string
    {
        return 'پایه ایستاده و رویه برگردان با خط خواب؛ یقه پیراهن کلاسیک.';
    }

    public function paramsSchema(): array
    {
        return [
            'stand_height' => [
                'label' => 'بلندی پایه', 'min' => 2, 'max' => 6, 'step' => 0.25, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
            'stand_rise' => [
                'label' => 'بالا آمدن جلوی پایه', 'min' => 0, 'max' => 4, 'step' => 0.25, 'default' => 1.5,
                'unit' => 'سانتی‌متر', 'hint' => 'پایه را جمع می‌کند تا به گردن بچسبد.',
            ],
            'fall_depth' => [
                'label' => 'پهنای رویه', 'min' => 3, 'max' => 9, 'step' => 0.25, 'default' => 4.5,
                'unit' => 'سانتی‌متر', 'hint' => 'دست‌کم یک سانت بیشتر از پایه باشد تا درز را بپوشاند.',
            ],
            'spread' => [
                'label' => 'گشادی لبه بیرونی رویه', 'min' => 0.5, 'max' => 6, 'step' => 0.25, 'default' => 1.75,
                'unit' => 'سانتی‌متر', 'hint' => 'لبه بیرونی رویه همین اندازه از لبه چسبیده به پایه بلندتر می‌شود.',
            ],
            'point_length' => [
                'label' => 'بلندی نوک یقه', 'min' => 3, 'max' => 11, 'step' => 0.25, 'default' => 6.5,
                'unit' => 'سانتی‌متر', 'hint' => 'از مرکز جلو تا نوک یقه.',
            ],
            'point_angle' => [
                'label' => 'زاویه نوک یقه', 'min' => 35, 'max' => 78, 'step' => 1, 'default' => 55,
                'unit' => 'درجه', 'hint' => 'زاویه کمتر یعنی یقه بازتر (اسپرد)، بیشتر یعنی نوک‌ها به هم نزدیک‌تر.',
            ],
            'extension' => [
                'label' => 'اضافه جای دکمه پایه', 'min' => 0, 'max' => 4, 'step' => 0.5, 'default' => 1.5,
                'unit' => 'سانتی‌متر',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $standHeight = (float) $p['stand_height'];
        $fallDepth = max($standHeight + 0.5, (float) $p['fall_depth']);
        [$extension, $extensionNote] = $this->frontExtension($pieces, $context, (float) $p['extension']);
        $target = max(5.0, $neck['half'] + (float) $p['ease']);

        // ۱) پایه به اندازه نیم خط یقه
        [$stand, $standLength, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->stand($span, $standHeight, (float) $p['stand_rise'], $extension),
            $target,
        );

        $stand = $this->halfCollarNotches($stand, $neck, $target);
        $standTop = $this->seamOf($stand, 'hem');

        // ۲) رویه به اندازه لبه بالای پایه
        [$fall, $fallLength] = $this->fitToNeckline(
            fn (float $span) => $this->fall($span, $fallDepth, (float) $p['spread'], $p),
            $standTop,
        );

        $fall = $this->neckNotches($fall, [
            ['at' => max(0.0, min($fallLength - 0.5, $neck['back'])), 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
            ['at' => $fallLength, 'label' => 'مرکز جلو', 'pair' => 'center_front'],
        ]);

        $fallOuter = $this->seamOf($fall, 'hem');
        $roll = PieceOps::walk($stand, 'hem', $fall, 'neck', ['tolerance' => static::NECK_TOLERANCE]);

        $stand['meta']['roll_line'] = round($standHeight, 2);
        $stand['meta']['top_edge'] = round($standTop, 2);
        $fall['meta']['roll_line'] = 0.0;
        $fall['meta']['outer_edge'] = round($fallOuter, 2);
        $fall['meta']['stand_top'] = round($standTop, 2);

        $made = [$stand, $fall];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($stand, 'لایه چسب پایه یقه');
            $made[] = $this->collarInterfacing($fall, 'لایه چسب رویه یقه');
        }

        $notes[] = 'لبه بالای پایه '.Format::cm($standTop).' و لبه چسبیده رویه '
            .Format::cm($fallLength).' درآمد؛ اختلاف '.Format::cm(abs((float) $roll['difference']))
            .'، پس درز خط خواب بدون کشیدن دوخته می‌شود.';
        $notes[] = 'لبه بیرونی رویه '.Format::cm($fallOuter).' است، یعنی '
            .Format::cm(max(0, $fallOuter - $standTop)).' بلندتر از لبه بالای پایه؛ '
            .'همین اضافه است که یقه را روی پایه می‌خواباند. اگر این عدد به صفر برسد یقه برنمی‌گردد.';
        $notes[] = 'خط خواب یقه روی درز پایه و رویه است؛ لایه چسب پایه تا همان درز و لایه رویه تا لبه بیرونی می‌رود.';

        if ($extensionNote !== null) {
            $notes[] = $extensionNote;
        }

        if ($extension > 0) {
            $notes[] = 'پایه '.Format::cm($extension).' از مرکز جلو جلوتر رفت تا دکمه یقه رویش بنشیند؛ رویه تا مرکز جلو بیشتر نمی‌آید.';
        }

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $standLength,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'stand_height' => $standHeight,
                'stand_top' => round($standTop, 2),
                'fall_attach' => round($fallLength, 2),
                'fall_outer' => round($fallOuter, 2),
                'roll_difference' => $roll['difference'],
                'rolls' => $fallOuter > $standTop + 0.2,
            ],
        ];
    }

    /**
     * پایه یقه.
     *
     * @return array<string, mixed>
     */
    protected function stand(float $span, float $height, float $rise, float $extension): array
    {
        $arc = $this->collarArc($span, $height, $this->riseRadius($span, $rise), 'stand');
        $front = $this->bandFrontEnd($arc, $extension, 'round');
        $shell = $this->assembleCollar($arc, $front['points'], 'hem', $front['tags']);

        return $this->newPiece([
            'code' => 'collar-stand',
            'name' => 'پایه یقه',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
                $this->rollLineMarker($arc, $height, 'خط خواب یقه (درز رویه)'),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'stand',
                'stand_height' => round($height, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }

    /**
     * رویه یقه با نوک.
     *
     * @return array<string, mixed>
     */
    protected function fall(float $span, float $depth, float $spread, array $p): array
    {
        $arc = $this->collarArc($span, $depth, $this->arcRadiusFor($span, $depth, $spread), 'fall');
        $neck = $this->pt($arc['cf_neck']);
        $outer = $this->pt($arc['cf_outer']);
        $tangent = $this->unit($arc['cf_tangent']);
        $across = $this->unit($this->vec($neck, $outer));
        $angle = deg2rad((float) $p['point_angle']);
        $point = (float) $p['point_length'];

        $tip = $this->add(
            $this->add($neck, $tangent, $point * cos($angle)),
            $across,
            $point * sin($angle),
        );

        $shell = $this->assembleCollar(
            $arc,
            [Geometry::point($tip['x'], $tip['y']), Geometry::point($outer['x'], $outer['y'])],
            'hem',
            ['side', 'hem'],
        );

        return $this->newPiece([
            'code' => 'collar-fall',
            'name' => 'رویه یقه',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
                $this->rollLineMarker($arc, 0.0, 'خط خواب یقه (درز پایه)'),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'fall',
                'point_length' => round($point, 2),
                'point_angle' => round((float) $p['point_angle'], 1),
                'radius' => $arc['radius'],
            ],
        ]);
    }
}
