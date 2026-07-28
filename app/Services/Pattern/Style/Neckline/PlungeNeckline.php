<?php

namespace App\Services\Pattern\Style\Neckline;

/** یقه هفت عمیق: یقه هفتی که تا زیر خط سینه پایین می‌آید. */
class PlungeNeckline extends VNeckline
{
    public static function key(): string
    {
        return 'neck_v_deep';
    }

    public function label(): string
    {
        return 'یقه هفت عمیق';
    }

    public function description(): string
    {
        return 'یقه هفت بلند تا نزدیک خط سینه یا پایین‌تر؛ به نوار نگه‌دارنده یا سجاف لایه‌دار نیاز دارد.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(20, 10, 40),
            'width' => $this->widthField(1.5),
            'back_depth' => $this->backDepthField(1, 12),
            'stay' => [
                'label' => 'نوار نگه‌دارنده لبه', 'type' => 'toggle', 'default' => true,
                'hint' => 'نوار باریک بی‌کشش که پشت خط یقه دوخته می‌شود تا یقه باز نایستد.',
            ],
        ] + $this->finishFields(5.5);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $path = $this->vPath($a, (float) $p['depth'], (float) $p['width']);
        $bustY = (float) ($a['piece']['meta']['bust_y'] ?? 0);
        $depthY = $a['cf']['y'] + (float) $p['depth'];

        if ($bustY > 0 && $depthY > $bustY + 2) {
            $path['notes'][] = 'یقه '.round($depthY - $bustY, 1).' سانتی‌متر پایین‌تر از خط سینه می‌رسد؛'
                .' بدون نوار نگه‌دارنده یا چسب سینه باز می‌ماند.';
        }

        if (! empty($p['stay'])) {
            $path['notes'][] = 'نوار نگه‌دارنده لبه یقه: نواری بی‌کشش به طول لبه یقه، ۰٫۵ سانتی‌متر کوتاه‌تر بریده و کشیده دوخته شود.';
            $path['meta'] = array_merge($path['meta'] ?? [], ['neck_stay' => true]);
        }

        return $path;
    }
}
