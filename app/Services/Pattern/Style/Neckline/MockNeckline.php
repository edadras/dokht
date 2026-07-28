<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه دور گردن ایستاده.
 *
 * برعکس بقیه: خط یقه بالاتر از بلوک می‌رود و تنگ‌تر می‌شود تا دور گردن بایستد.
 * چون خط یقه بالا می‌رود، هر دو قطعه به یک اندازه بالا می‌روند تا درز سرشانه جور
 * بماند. برای پارچه بی‌کشش باید چاک و بست پشت داشته باشد؛ در یادداشت‌ها می‌آید.
 */
class MockNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_high';
    }

    public function label(): string
    {
        return 'یقه دور گردن ایستاده';
    }

    public function description(): string
    {
        return 'خط یقه بالاتر و تنگ‌تر از بلوک؛ پایه یقه ایستاده و یقه اسکی.';
    }

    public function paramsSchema(): array
    {
        return [
            'rise' => [
                'label' => 'بالا آمدن خط یقه', 'min' => 0.5, 'max' => 6, 'step' => 0.5, 'default' => 2,
                'unit' => 'سانتی‌متر', 'hint' => 'خط یقه جلو و پشت با هم بالا می‌رود.',
            ],
            'width' => $this->widthField(-1, -4, 2),
            'front_extra' => [
                'label' => 'گودی بیشتر جلو', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'back_opening' => [
                'label' => 'چاک پشت', 'type' => 'toggle', 'default' => true,
                'hint' => 'برای پارچه بی‌کشش لازم است تا سر از یقه رد شود.',
            ],
        ] + $this->finishFields(4);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->highPath($a, (float) $p['front_extra'], $p, 90.0);
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->highPath($a, 0.0, $p, $this->complement($partnerAngle));
    }

    protected function highPath(array $a, float $extra, array $p, float $alpha): array
    {
        $rise = (float) $p['rise'];
        $snp = $this->movedSnp($a, (float) $p['width']);
        $snp = Geometry::point($snp['x'], $snp['y'] - $rise);
        $center = Geometry::point($a['center_x'], max(0.5, $a['cf']['y'] - $rise + $extra));

        $notes = [];

        if ($a['side'] === 'back' && ! empty($p['back_opening'])) {
            $notes[] = 'چاک پشت از خط یقه به اندازه ۱۲ سانتی‌متر باز شود و با دکمه و حلقه بسته شود؛'
                .' یقه ایستاده روی پارچه بی‌کشش بدون چاک از سر رد نمی‌شود.';
        }

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
            'notes' => $notes,
            'markers' => $a['side'] === 'back' && ! empty($p['back_opening'])
                ? [$this->marker('back_slit', 'چاک پشت', $a['center_x'], $center['y'], $a['center_x'], $center['y'] + 12)]
                : [],
        ];
    }
}
