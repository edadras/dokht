<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه پرنسسی از حلقه آستین.
 *
 * ساسون سینه و ساسون کمر در یک درز بلند حل می‌شوند: درز از حلقه آستین شروع
 * می‌شود، از کنار نوک سینه رد می‌شود و روی کمر (و در صورت بلندی، روی باسن)
 * پایین می‌رود. دو لبه این درز عیناً از یک خط ساخته و بعد «راه برده» می‌شوند،
 * پس طولشان برابر است و بدون کش آمدن روی هم می‌نشینند.
 */
class BodicePrincessArmholeGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_princess_armhole';
    }

    public function label(): string
    {
        return 'بالاتنه پرنسسی از حلقه';
    }

    /** محل شروع درز پرنسسی روی پنل بالا. */
    protected function origin(): string
    {
        return 'armhole';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(['waist_dart_share' => 0.7]),
            $this->bodyLengthParam(0, 0, 40),
            [
                'back_style' => [
                    'label' => 'پشت لباس', 'type' => 'select', 'default' => 'princess',
                    'options' => ['princess' => 'پرنسسی مثل جلو', 'dart' => 'ساده با ساسون کمر'],
                ],
                'bust_dart' => [
                    'label' => 'باز کردن درز روی نوک سینه', 'type' => 'toggle', 'default' => true,
                    'hint' => 'درز روی نوک سینه کمی باز می‌شود تا جای برجستگی سینه بیاید.',
                ],
                'hem_flare' => [
                    'label' => 'گشادی لبه پایین', 'min' => 0, 'max' => 20, 'step' => 0.5, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'body_length', 0);
        $shape = $length > 0.5 ? 'fitted' : 'waist';

        $shared = [
            'shape' => $shape,
            'length' => $length,
            'origin' => $this->origin(),
            'hem_flare' => (float) $this->param($params, 'hem_flare', 0),
            'bottom_tag' => $length > 0.5 ? 'hem' : 'waist',
        ];

        $pieces = $this->princessPanels($g, array_merge($shared, [
            'side' => 'front',
            'bust_dart' => $this->flag($params, 'bust_dart', true),
        ]));

        $pieces = array_merge($pieces, $this->param($params, 'back_style', 'princess') === 'princess'
            ? $this->princessPanels($g, array_merge($shared, ['side' => 'back']))
            : [$this->bodyPanel($g, array_merge($shared, [
                'side' => 'back',
                'code' => 'bodice-back',
                'name' => 'بالاتنه پشت',
            ]))]);

        return $this->finishBlock($pieces, $g);
    }
}
