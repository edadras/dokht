<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه شلوارهای مردانه.
 *
 * بلوکِ شلوارِ مردانه با بلوکِ زنانه یک درفت است، ولی چهار جای آن فرق دارد و
 * همین چهار تا این‌جا یک‌جا نوشته شده تا هر مدلِ مردانه فقط شخصیتِ خودش را
 * بنویسد:
 *
 *   ۱. کاهشِ کمر کمتر است. فاصلهٔ کمر تا باسنِ مرد کم است، پس اگر همان مقدارِ
 *      کاهشِ زنانه را به درزِ پهلو بدهی، پهلو کج و شلوار روی باسن تنگ می‌شود.
 *      برای همین side_share و lean_share هر دو پایین‌ترند و waist_balance به
 *      سمت پشت می‌رود؛ یعنی کاهشِ باقی‌مانده در ساسونِ پشت گرفته می‌شود، نه جلو.
 *   ۲. فاق گودتر است. مردانه به‌طور معمول یک تا دو سانتی‌متر گودیِ بیشتر
 *      می‌خواهد، وگرنه شلوار در نشستن جا کم می‌آورد؛ RISE_EXTRA همین است.
 *   ۳. جلو ساسون ندارد. شلوارِ مردانهٔ کلاسیک یا صاف است یا پیلی می‌خورد؛
 *      ساسونِ جلو روی پارچهٔ مردانه خودش را نشان می‌دهد.
 *   ۴. زیپِ فلای از راست روی چپ بسته می‌شود — برعکسِ زنانه. این را در متادیتای
 *      مدل ثبت می‌کنیم تا الگو در برش و دوخت درست خوانده شود.
 */
abstract class MensPantsBaseGenerator extends PantsBaseGenerator
{
    /** گودیِ بیشترِ فاقِ مردانه نسبت به درفتِ پایه، به سانتی‌متر. */
    protected const RISE_EXTRA = 1.5;

    /**
     * شخصیتِ این مدل.
     *
     * کلیدها: prefix، title، rise، rise_extra، thigh_ease، knee_ease،
     * hem_ease، hem_vs_knee، leg_length، front_waist، back_waist،
     * pleat_count، pleat_style، dart_count، side_share، lean_share، band،
     * band_stretch، use، fly، extra (پارامترهای اضافه)، shape (کلیدهای اضافهٔ
     * بلوک)، notes.
     *
     * @return array<string, mixed>
     */
    abstract protected function mens(): array;

    /** کاربردهایی که یک شلوارِ مردانه می‌تواند داشته باشد. */
    protected const USES = [
        'daily' => 'روزمره',
        'office' => 'اداری',
        'formal' => 'مجلسی',
        'sport' => 'ورزشی',
        'work' => 'کار',
    ];

    public function label(): string
    {
        return (string) ($this->mens()['title'] ?? 'شلوار مردانه');
    }

    public function paramsSchema(): array
    {
        $m = $this->mens();
        $band = (string) ($m['band'] ?? 'waistband');

        $schema = array_merge(
            $this->riseParams((string) ($m['rise'] ?? 'mid'), (float) ($m['rise_extra'] ?? self::RISE_EXTRA)),
            $this->legParams((float) ($m['knee_ease'] ?? 9), (float) ($m['hem_ease'] ?? 12)),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 4, 'max' => 26, 'step' => 0.5,
                    'default' => (float) ($m['thigh_ease'] ?? 11), 'unit' => 'سانتی‌متر',
                ],
                'garment_use' => [
                    'label' => 'کاربرد', 'type' => 'select',
                    'default' => (string) ($m['use'] ?? 'daily'),
                    'options' => self::USES,
                ],
            ],
        );

        if ((string) ($m['front_waist'] ?? 'none') === 'pleat') {
            $schema['front_pleats'] = [
                'label' => 'تعداد پیلی جلو', 'min' => 1, 'max' => 2, 'step' => 1,
                'default' => (int) ($m['pleat_count'] ?? 1),
            ];
        }

        if ((string) ($m['back_waist'] ?? 'dart') === 'dart') {
            $schema['back_darts'] = [
                'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1,
                'default' => (int) ($m['dart_count'] ?? 2),
            ];
        }

        if ($band === 'waistband') {
            $schema = array_merge($schema, $this->bandParams((float) ($m['band_height'] ?? 4)));
        } else {
            $schema['waistband_height'] = [
                'label' => 'بلندی نوار کمر', 'min' => 2, 'max' => 10, 'step' => 0.5,
                'default' => (float) ($m['band_height'] ?? 4.5), 'unit' => 'سانتی‌متر',
            ];
        }

        return array_merge($schema, (array) ($m['extra'] ?? []));
    }

    protected function shape(array $params, array $measurements): array
    {
        $m = $this->mens();

        $shape = [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', $m['thigh_ease'] ?? 11),
            'hem_vs_knee' => (float) ($m['hem_vs_knee'] ?? -3.0),
            'front_waist' => (string) ($m['front_waist'] ?? 'none'),
            'back_waist' => (string) ($m['back_waist'] ?? 'dart'),
            // کاهشِ کمرِ مردانه بیشتر پشت گرفته می‌شود تا پهلو
            'waist_balance' => (float) ($m['waist_balance'] ?? 0.35),
            'side_share' => (float) ($m['side_share'] ?? 0.28),
            'lean_share' => (float) ($m['lean_share'] ?? 0.14),
            'dart_count' => (int) $this->param($params, 'back_darts', $m['dart_count'] ?? 2),
        ];

        if (isset($m['leg_length'])) {
            $shape['leg_length'] = max(6.0, (float) $this->param($params, 'leg_length', $m['leg_length']));
        }

        if ($shape['front_waist'] === 'pleat') {
            $shape['pleat_count'] = (int) $this->param($params, 'front_pleats', $m['pleat_count'] ?? 1);
            $shape['pleat_style'] = (string) ($m['pleat_style'] ?? 'knife');
        }

        if ((string) ($m['band'] ?? 'waistband') !== 'waistband') {
            $shape['band'] = (string) $m['band'];
            $shape['band_stretch'] = (float) ($m['band_stretch'] ?? 0.85);
        }

        return array_merge($shape, (array) ($m['shape'] ?? []));
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);
        $m = $this->mens();

        foreach ($pieces as $index => $piece) {
            $notes = $piece['meta']['notes'] ?? [];

            if (($piece['meta']['part'] ?? '') === 'front_leg') {
                // فلایِ مردانه از راست روی چپ بسته می‌شود
                $pieces[$index]['meta']['fly_lap'] = 'right_over_left';
                $notes[] = 'دوختِ مردانه: لبهٔ راست روی چپ می‌افتد.';
            }

            $pieces[$index]['meta']['mens'] = [
                'model' => (string) ($m['prefix'] ?? static::key()),
                'use' => (string) $this->param($params, 'garment_use', $m['use'] ?? 'daily'),
                'front_waist' => (string) ($m['front_waist'] ?? 'none'),
            ];

            $pieces[$index]['meta']['notes'] = array_merge($notes, (array) ($m['notes'] ?? []));
        }

        return $this->finish($pieces);
    }
}
