<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * یقه برگردان یک‌تکه.
 *
 * همان یقه پیراهنی است، ولی پایه و رویه در یک قطعه. یقه‌ای که هم بسته و هم باز
 * پوشیده می‌شود: با دکمه بسته، یقه ایستاده به نظر می‌رسد و باز که باشد روی
 * سرشانه برمی‌گردد.
 *
 * این یقه ذاتاً یک سازش است و باید صادقانه گفته شود: پایه می‌خواهد لبه بالا
 * کوتاه‌تر شود و رویه می‌خواهد لبه بیرونی بلندتر شود؛ در یک تکه پارچه فقط یکی
 * از این دو ممکن است. راه‌حل خیاط این است که یقه را با کمان بسیار ملایم بِبُرد
 * (بالا آمدن کم) و کمبود طول لبه بیرونی را با نوک بلند یقه جبران کند. نتیجه:
 * یقه‌ای که خوب برمی‌گردد ولی به اندازه یقه دوتکه روی سرشانه نمی‌خوابد و در
 * مرکز پشت کمی بالاتر می‌ایستد.
 */
class ConvertibleCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_convertible';
    }

    public function label(): string
    {
        return 'یقه برگردان یک‌تکه';
    }

    public function description(): string
    {
        return 'پایه و رویه در یک قطعه، با خط خواب علامت‌خورده؛ هم بسته و هم باز پوشیده می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'stand' => [
                'label' => 'بلندی پایه', 'min' => 1.5, 'max' => 5, 'step' => 0.25, 'default' => 2.5,
                'unit' => 'سانتی‌متر', 'hint' => 'تا خط خواب یقه؛ یقه از همین خط تا می‌خورد.',
            ],
            'fall' => [
                'label' => 'پهنای برگشت', 'min' => 2.5, 'max' => 9, 'step' => 0.25, 'default' => 4.5,
                'unit' => 'سانتی‌متر', 'hint' => 'دست‌کم یک سانت بیشتر از پایه، تا درز خط یقه پنهان بماند.',
            ],
            'rise' => [
                'label' => 'بالا آمدن جلوی یقه', 'min' => 0, 'max' => 3, 'step' => 0.25, 'default' => 1,
                'unit' => 'سانتی‌متر', 'hint' => 'کم بگیرید؛ یقه یک‌تکه با کمان تند برنمی‌گردد.',
            ],
            'point_length' => [
                'label' => 'بلندی نوک یقه', 'min' => 3, 'max' => 11, 'step' => 0.25, 'default' => 6,
                'unit' => 'سانتی‌متر',
            ],
            'point_angle' => [
                'label' => 'زاویه نوک یقه', 'min' => 35, 'max' => 78, 'step' => 1, 'default' => 58, 'unit' => 'درجه',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $stand = (float) $p['stand'];
        $fall = max($stand + 0.5, (float) $p['fall']);
        $width = $stand + $fall;
        $target = max(5.0, $neck['half'] + (float) $p['ease']);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->collar($span, $width, $stand, $p),
            $target,
        );

        $piece = $this->halfCollarNotches($piece, $neck, $target);
        $outer = $this->seamOf($piece, 'hem');
        $made = [$piece];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($piece, 'لایه چسب یقه برگردان');
        }

        $notes[] = 'خط خواب یقه '.Format::cm($stand).' بالاتر از لبه یقه علامت خورد؛ یقه از همین خط برمی‌گردد.';
        $notes[] = 'لبه بیرونی با نوک‌ها '.Format::cm($outer).' درآمد در برابر لبه یقه '.Format::cm($length)
            .'؛ در یقه یک‌تکه همین نوک‌ها جای گشادی لبه بیرونی را می‌گیرند.';
        $notes[] = 'یقه یک‌تکه سازش است: به اندازه یقه دوتکه روی سرشانه نمی‌خوابد و در مرکز پشت کمی بالاتر می‌ایستد؛'
            .' اگر خواب بیشتری می‌خواهید یقه پیراهنی دوتکه بگیرید.';

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'stand' => $stand,
                'fall' => $fall,
                'outer_edge' => round($outer, 2),
                'roll_line' => $stand,
            ],
        ];
    }

    /**
     * درفت نیم‌یقه یک‌تکه.
     *
     * @return array<string, mixed>
     */
    protected function collar(float $span, float $width, float $stand, array $p): array
    {
        $arc = $this->collarArc($span, $width, $this->riseRadius($span, (float) $p['rise']), 'stand');
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
            'code' => 'collar-convertible',
            'name' => 'یقه برگردان',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
                $this->rollLineMarker($arc, $stand, 'خط خواب یقه'),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'convertible',
                'stand_height' => round($stand, 2),
                'roll_line' => round($stand, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }
}
