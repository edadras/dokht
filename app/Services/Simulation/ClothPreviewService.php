<?php

namespace App\Services\Simulation;

use App\Models\Fabric;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Support\FabricProfile;
use App\Support\Measurements;

/**
 * ساخت داده‌ی نمای سه‌بعدی.
 *
 * این سرویس هیچ محاسبه‌ی سنگینی انجام نمی‌دهد؛ فقط اندازه‌های بدن، مشخصات پارچه و
 * نتیجه‌ی تحلیل تناسب را به یک بسته‌ی JSON کوچک تبدیل می‌کند تا مرورگر مانکن و لباس
 * را بسازد. تمام شکل‌دهی و افتادگی پارچه در مرورگر انجام می‌شود.
 */
class ClothPreviewService
{
    /** کلیدهای بدن که مانکن پارامتری لازم دارد. */
    public const AVATAR_KEYS = [
        'height', 'bust', 'under_bust', 'waist', 'high_hip', 'hip', 'shoulder_width',
        'arm_length', 'neck', 'armhole', 'bicep', 'wrist', 'thigh', 'knee', 'ankle',
        'inseam', 'outseam', 'back_length', 'front_length', 'waist_to_hip',
    ];

    public function __construct(protected DrapePayloadService $drape = new DrapePayloadService) {}

    /**
     * بسته‌ی کامل نمای سه‌بعدی.
     *
     * @param  array<string, float|int|string>  $measurements  اندازه‌های بدن
     * @param  array  $fit  خروجی FitAnalysisService::analyze()
     * @return array{avatar: array, fabric: array, garment: array, drape: array, zones: array, pose: string}
     */
    public function payload(Pattern $pattern, ?Fabric $fabric, array $measurements, array $fit): array
    {
        $body = Measurements::complete($measurements);
        $profile = $fabric?->profile() ?? FabricProfile::make();
        $pieces = $pattern->relationLoaded('pieces') ? $pattern->pieces : $pattern->pieces()->get();

        return [
            'avatar' => $this->avatar($body),
            'fabric' => [
                'name' => $fabric?->displayName() ?? 'پارچه پیش‌فرض',
                'color' => $this->color($fabric),
                'physics' => $profile->physics(),
                'transparency' => round((float) $profile->get('transparency'), 3),
                'sheen' => round((float) $profile->get('sheen'), 3),
                'drape' => round((float) $profile->get('drape'), 3),
                'drape_label' => $profile->drapeLabel(),
                'stretch' => round($profile->maxStretch() / 100, 3),
            ],
            'garment' => [
                'silhouette' => $this->silhouette($pattern, $pieces, $fit),
                'lengths' => $this->lengths($pattern, $pieces, $body),
                'ease' => $this->ease($pattern, $fit),
                'pieces' => $this->outlines($pieces),
            ],
            // بستهٔ «دوخت سه‌بعدی»: قطعه‌های واقعی، درزها و چیدن اولیه. نمای
            // پارامتری بالا دست‌نخورده می‌ماند تا اگر مثلث‌بندی در مرورگر نگرفت،
            // صفحه سفید نماند؛ و اگر ساختن همین بسته هم بگیرد، باز هم چیزی از
            // نمای امروز کم نمی‌شود.
            'drape' => $this->drapePayload($pattern),
            'zones' => array_map(fn (array $zone) => [
                'key' => $zone['key'] ?? '',
                'label' => $zone['label'] ?? '',
                'level' => $zone['level'] ?? 'good',
                'color' => $zone['color'] ?? '#16a34a',
                'pressure' => (float) ($zone['pressure'] ?? 0),
                'ease_cm' => (float) ($zone['ease_cm'] ?? 0),
            ], array_values($fit['zones'] ?? [])),
            'pose' => (string) ($fit['pose'] ?? 'stand'),
        ];
    }

    /**
     * بسته‌ی دوخت سه‌بعدی، با تور ایمنی.
     *
     * ساختن این بسته از نمای پارامتری سنگین‌تر است و به هندسه‌ی هر قطعه دست
     * می‌زند؛ اگر جایی بگیرد، کلید drape خالی می‌آید و مرورگر خودش به نمای
     * پارامتری برمی‌گردد.
     */
    protected function drapePayload(Pattern $pattern): array
    {
        try {
            return $this->drape->payload($pattern);
        } catch (\Throwable $error) {
            report($error);

            return ['scale' => 0.01, 'pieces' => [], 'seams' => [], 'budget' => [
                'target_edge' => DrapePayloadService::TARGET_EDGE,
                'max_vertices' => DrapePayloadService::MAX_VERTICES,
            ], 'meta' => [
                'unmatched' => [],
                'relations' => 0,
                'notes' => ['بسته‌ی دوخت سه‌بعدی ساخته نشد: '.$error->getMessage()],
            ]];
        }
    }

    /** اندازه‌های لازم مانکن. */
    protected function avatar(array $body): array
    {
        $avatar = [];

        foreach (static::AVATAR_KEYS as $key) {
            $avatar[$key] = round((float) ($body[$key] ?? 0), 1);
        }

        return $avatar;
    }

    /** رنگ پارچه؛ اگر ثبت نشده باشد یک خاکی ملایم. */
    protected function color(?Fabric $fabric): string
    {
        $hex = trim((string) ($fabric?->color_hex ?? ''));

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#b9a48c';
    }

    /**
     * فرم کلی لباس.
     *
     * از اختلاف آزادی پایین لباس با باسن به دست می‌آید: هرچه پایین لباس از باسن
     * گشادتر باشد، فرم به «کلوش» نزدیک‌تر است و اگر آزادی سینه و کمر کم باشد لباس
     * «تنگ» است.
     */
    protected function silhouette(Pattern $pattern, $pieces, array $fit): string
    {
        $ease = $this->ease($pattern, $fit);
        $flare = ($ease['hem'] ?? 0) - ($ease['hip'] ?? 0);

        return match (true) {
            $flare >= 24 => 'flared',
            $flare >= 8 => 'a_line',
            ($ease['bust'] ?? 6) <= 3.5 && ($ease['waist'] ?? 4) <= 3 => 'fitted',
            default => 'straight',
        };
    }

    /** آزادی هر ناحیه: از تحلیل تناسب، وگرنه از خود الگو. */
    protected function ease(Pattern $pattern, array $fit): array
    {
        $ease = [];

        foreach (array_values($fit['zones'] ?? []) as $zone) {
            if (isset($zone['key'])) {
                $ease[$zone['key']] = round((float) ($zone['ease_cm'] ?? 0), 1);
            }
        }

        foreach (($pattern->ease ?? []) as $key => $value) {
            if (! isset($ease[$key]) && is_numeric($value)) {
                $ease[$key] = round((float) $value, 1);
            }
        }

        return $ease;
    }

    /**
     * قدهای لباس به سانتی‌متر.
     *
     * از بلندی قطعه‌ها خوانده می‌شود و اگر قطعه‌ای نبود از اندازه‌ی بدن تخمین می‌خورد.
     */
    protected function lengths(Pattern $pattern, $pieces, array $body): array
    {
        $heights = ['torso' => 0.0, 'skirt' => 0.0, 'sleeve' => 0.0, 'leg' => 0.0];

        foreach ($pieces as $piece) {
            $role = $this->role($piece);

            if (isset($heights[$role])) {
                $heights[$role] = max($heights[$role], $piece->height());
            }
        }

        $bodice = $heights['torso'] > 5 ? $heights['torso'] : (float) ($body['back_length'] ?? 40);
        $skirt = max($heights['skirt'], $heights['leg']);
        $sleeve = $heights['sleeve'] > 5 ? $heights['sleeve'] : 0.0;

        if ($sleeve === 0.0 && in_array('sleeve', $pattern->garmentType?->parts ?? [], true)) {
            $sleeve = (float) ($body['arm_length'] ?? 58);
        }

        return [
            'bodice' => round(min(140, max(20, $bodice)), 1),
            'skirt' => round(min(140, max(0, $skirt)), 1),
            'sleeve' => round(min(90, max(0, $sleeve)), 1),
        ];
    }

    /**
     * خط دور قطعه‌ها برای پیش‌نمای درزها.
     *
     * هر قطعه به حداکثر ۲۴ نقطه ساده می‌شود تا بسته سبک بماند.
     */
    protected function outlines($pieces): array
    {
        $out = [];

        foreach ($pieces as $piece) {
            $points = $piece->points();

            if (count($points) < 3) {
                continue;
            }

            $out[] = [
                'code' => (string) $piece->code,
                'name' => (string) $piece->name,
                'role' => $this->role($piece),
                'quantity' => max(1, (int) $piece->cut_quantity),
                'on_fold' => (bool) $piece->on_fold,
                'width' => $piece->width(),
                'height' => $piece->height(),
                'points' => $this->simplify($points, 24),
            ];
        }

        return $out;
    }

    /** کاهش تعداد نقاط با نمونه‌برداری یکنواخت. */
    protected function simplify(array $points, int $max): array
    {
        $count = count($points);

        if ($count <= $max) {
            return array_map(fn ($p) => [round($p['x'], 1), round($p['y'], 1)], $points);
        }

        $step = $count / $max;
        $out = [];

        for ($i = 0; $i < $max; $i++) {
            $point = $points[(int) floor($i * $step)];
            $out[] = [round($point['x'], 1), round($point['y'], 1)];
        }

        return $out;
    }

    /** نقش قطعه از روی کد و نام آن. */
    protected function role(PatternPiece $piece): string
    {
        $haystack = mb_strtolower($piece->code.' '.$piece->name);

        foreach ([
            'sleeve' => ['sleeve', 'آستین'],
            'skirt' => ['skirt', 'دامن'],
            'leg' => ['leg', 'pant', 'trouser', 'شلوار', 'پاچه'],
            'collar' => ['collar', 'یقه'],
            'detail' => ['cuff', 'pocket', 'facing', 'waistband', 'belt', 'placket', 'مچ', 'جیب', 'سجاف', 'کمربند'],
        ] as $role => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $role;
                }
            }
        }

        return 'torso';
    }
}
