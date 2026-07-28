<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * یقه کاپشنی (ایستاده با خانه زیپ).
 *
 * یقه بلند ایستاده‌ای که زیپ سرتاسری تا بالای آن می‌آید. دو نکته آن را از یقه
 * آخوندی جدا می‌کند:
 *
 *   ۱. سر جلوی یقه راست است و اضافه جای دکمه ندارد؛ دو سر یقه در مرکز جلو به هم
 *      می‌رسند و زیپ همان‌جا بسته می‌شود.
 *   ۲. بالای زیپ یک «خانه زیپ» (چانه‌گیر) دوخته می‌شود: تکه کوچکی پارچه که سر
 *      زیپ داخلش پنهان می‌شود تا به چانه و گردن نخورد. هر کاپشنی که این تکه را
 *      نداشته باشد، سر زیپش زیر چانه می‌خورد.
 *
 * چون یقه بلند است و باید بایستد، لایه چسب روی لای رو و بالا آمدن جلو کم گرفته
 * می‌شود تا زیپ در بسته‌شدن گردن را نفشارد.
 */
class ZipStandCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_zip_stand';
    }

    public function label(): string
    {
        return 'یقه کاپشنی (زیپ‌دار)';
    }

    public function description(): string
    {
        return 'یقه ایستاده بلند با زیپ سرتاسری و خانه زیپ برای پنهان کردن سر زیپ.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => [
                'label' => 'بلندی یقه', 'min' => 3, 'max' => 14, 'step' => 0.5, 'default' => 7,
                'unit' => 'سانتی‌متر',
            ],
            'rise' => [
                'label' => 'بالا آمدن جلوی یقه', 'min' => 0, 'max' => 3, 'step' => 0.25, 'default' => 0.75,
                'unit' => 'سانتی‌متر', 'hint' => 'کم بگیرید؛ یقه بلندِ جمع، با زیپ بسته گردن را می‌فشارد.',
            ],
            'garage' => [
                'label' => 'بلندی خانه زیپ', 'min' => 0, 'max' => 5, 'step' => 0.5, 'default' => 2,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی بدون خانه زیپ.',
            ],
            'zip_width' => [
                'label' => 'پهنای نوار زیپ', 'min' => 0.4, 'max' => 2, 'step' => 0.1, 'default' => 0.6,
                'unit' => 'سانتی‌متر',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }

    protected function supportsCollar(array $pieces, array $context): true|string
    {
        if (! $this->frontOpening($pieces, $context)) {
            return 'یقه کاپشنی روی زیپ سرتاسری جلو بسته می‌شود، ولی این لباس چاک جلو ندارد؛'
                .' اول بست جلو (زیپ یا دکمه) را اضافه کنید یا یقه آخوندی بگیرید.';
        }

        return true;
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $height = (float) $p['height'];
        $garage = (float) $p['garage'];
        $zip = (float) $p['zip_width'];
        $target = max(5.0, $neck['half'] + (float) $p['ease']);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->stand($span, $height, (float) $p['rise'], $zip),
            $target,
        );

        $piece = $this->halfCollarNotches($piece, $neck, $target);
        $top = $this->seamOf($piece, 'hem');
        $made = [$piece];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($piece, 'لایه چسب یقه کاپشنی');
        }

        if ($garage > 0.3) {
            $made[] = $this->garagePiece($garage, $zip);
            $notes[] = 'خانه زیپ '.Format::cm($garage).' بلندی دارد و در بالای زیپ، لای دو لای یقه دوخته می‌شود؛'
                .' سر زیپ که تا ته بالا بیاید داخل آن پنهان می‌شود و به چانه نمی‌خورد.';
        } else {
            $notes[] = 'خانه زیپ گرفته نشد؛ سر زیپ در بسته‌شدن کامل به چانه می‌خورد.';
        }

        $notes[] = 'سر جلوی یقه راست و بدون اضافه است؛ دو سر یقه در مرکز جلو به هم می‌رسند و نوار زیپ '
            .Format::cm($zip).' لای درز می‌رود.';
        $notes[] = 'لبه بالای یقه '.Format::cm($top).' درآمد، یعنی '.Format::cm(max(0, $length - $top))
            .' کوتاه‌تر از لبه یقه؛ بیش از این کوتاه شود، یقه با زیپ بسته گردن را می‌فشارد.';

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'height' => $height,
                'top_edge' => round($top, 2),
                'garage' => $garage,
                'zip_width' => $zip,
            ],
        ];
    }

    /**
     * تنه یقه ایستاده با سر جلوی راست.
     *
     * @return array<string, mixed>
     */
    protected function stand(float $span, float $height, float $rise, float $zip): array
    {
        $arc = $this->collarArc($span, $height, $this->riseRadius($span, $rise), 'stand');
        $shell = $this->assembleCollar($arc);

        return $this->newPiece([
            'code' => 'collar-zip-stand',
            'name' => 'یقه کاپشنی',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
                $this->marker(
                    'zip',
                    'نوار زیپ در درز مرکز جلو',
                    $arc['cf_neck']['x'],
                    $arc['cf_neck']['y'],
                    $arc['cf_outer']['x'],
                    $arc['cf_outer']['y'],
                ),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'stand',
                'zip' => true,
                'zip_width' => round($zip, 2),
                'height' => round($height, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }

    /**
     * خانه زیپ (چانه‌گیر).
     *
     * @return array<string, mixed>
     */
    protected function garagePiece(float $length, float $zip): array
    {
        $width = round(($zip * 2) + 1.6, 2);
        $height = round($length + 1.5, 2);

        return $this->newPiece([
            'code' => 'collar-zip-garage',
            'name' => 'خانه زیپ (چانه‌گیر)',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height),
                Geometry::point(0, $height),
            ],
            'grainline' => $this->grainline($width / 2, 0.4, $height - 0.4),
            'markers' => [
                $this->marker('fold', 'خط تای خانه زیپ', 0, $height / 2, $width, $height / 2),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => ['default', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'zip_garage',
                'note' => 'دولا تا شود و بالای زیپ، لای دو لای یقه دوخته شود.',
            ],
        ]);
    }
}
