<?php

namespace App\Services\Fabric;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Support\FabricProfile;
use App\Support\Format;
use Illuminate\Support\Collection;

/**
 * سنجش سازگاری پارچه با مدل لباس.
 *
 * پاسخ همیشه به زبان خیاط است: یک نمره کلی ۰ تا ۱۰۰، یک حکم روشن و چند معیار با
 * دلیل فارسی. اگر نوع لباس ترجیح پارچه (garment_types.fabric_preferences) داشته
 * باشد از آن استفاده می‌شود؛ در غیر این صورت پیش‌فرض‌های معقول هر دسته به‌کار می‌رود.
 *
 * قالب ترجیح‌ها:
 * ```php
 * [
 *   'drape'        => ['ideal' => 0.8, 'tolerance' => 0.25],
 *   'stiffness'    => ['ideal' => 0.2, 'tolerance' => 0.3],
 *   'weight_gsm'   => ['min' => 60, 'max' => 200],
 *   'stretch'      => ['min' => 0, 'max' => 30],   // بیشترین کشسانی به درصد (کلید stretch_weft هم پذیرفته است)
 *   'transparency' => ['max' => 0.3],
 *   'difficulty'   => ['max' => 0.7],
 *   'families'     => ['woven', 'knit'],
 *   'silhouette'   => 'a_line',
 * ]
 * ```
 */
class FabricCompatibilityService
{
    /** وزن هر معیار در نمره کلی (جمع = ۱۰۰). */
    public const WEIGHTS = [
        'drape' => 25,
        'weight' => 20,
        'structure' => 18,
        'transparency' => 13,
        'stretch' => 12,
        'care' => 12,
    ];

    public const CRITERIA_LABELS = [
        'drape' => 'لختی و ریزش',
        'weight' => 'وزن پارچه',
        'structure' => 'فرم‌پذیری و سفتی',
        'transparency' => 'پوشانندگی',
        'stretch' => 'کشسانی',
        'care' => 'سهولت کار و نگهداری',
    ];

    public const VERDICTS = [
        'suitable' => 'مناسب',
        'acceptable' => 'قابل قبول',
        'unsuitable' => 'نامناسب',
    ];

    /** ترجیح پیش‌فرض هر دسته لباس؛ تا حتی بدون تنظیم دستی هم پاسخ درست بدهد. */
    public const CATEGORY_DEFAULTS = [
        'top' => [
            'drape' => ['ideal' => 0.55, 'tolerance' => 0.3],
            'stiffness' => ['ideal' => 0.35, 'tolerance' => 0.35],
            'weight_gsm' => ['min' => 100, 'max' => 260],
            'stretch' => ['min' => 0, 'max' => 45],
            'transparency' => ['max' => 0.35],
            'difficulty' => ['max' => 0.65],
        ],
        'bottom' => [
            'drape' => ['ideal' => 0.35, 'tolerance' => 0.3],
            'stiffness' => ['ideal' => 0.55, 'tolerance' => 0.3],
            'weight_gsm' => ['min' => 170, 'max' => 400],
            'stretch' => ['min' => 0, 'max' => 35],
            'transparency' => ['max' => 0.12],
            'difficulty' => ['max' => 0.6],
        ],
        'one_piece' => [
            'drape' => ['ideal' => 0.7, 'tolerance' => 0.25],
            'stiffness' => ['ideal' => 0.25, 'tolerance' => 0.3],
            'weight_gsm' => ['min' => 80, 'max' => 240],
            'stretch' => ['min' => 0, 'max' => 40],
            'transparency' => ['max' => 0.3],
            'difficulty' => ['max' => 0.75],
        ],
        'outer' => [
            'drape' => ['ideal' => 0.3, 'tolerance' => 0.3],
            'stiffness' => ['ideal' => 0.6, 'tolerance' => 0.3],
            'weight_gsm' => ['min' => 210, 'max' => 520],
            'stretch' => ['min' => 0, 'max' => 25],
            'transparency' => ['max' => 0.1],
            'difficulty' => ['max' => 0.6],
        ],
        'formal' => [
            'drape' => ['ideal' => 0.78, 'tolerance' => 0.25],
            'stiffness' => ['ideal' => 0.22, 'tolerance' => 0.32],
            'weight_gsm' => ['min' => 60, 'max' => 220],
            'stretch' => ['min' => 0, 'max' => 30],
            'transparency' => ['max' => 0.5],
            'difficulty' => ['max' => 0.9],
        ],
    ];

    /**
     * نمره سازگاری یک پارچه کارگاه با یک مدل لباس.
     *
     * @return array{overall:int, verdict:string, verdict_label:string, criteria:array<int, array{key:string,label:string,score:int,reason:string}>, notes:array<int, string>}
     */
    public function score(Fabric $fabric, GarmentType $garmentType): array
    {
        return $this->scoreProfile($fabric->profile(), $garmentType, [
            'label' => $fabric->displayName(),
            'family' => $fabric->fabricType?->family,
            'surface_pattern' => $fabric->surface_pattern,
        ]);
    }

    /** نمره سازگاری یک نوع پارچه از بانک پایه با یک مدل لباس. */
    public function scoreType(FabricType $type, GarmentType $garmentType): array
    {
        return $this->scoreProfile($type->fabricProfile(), $garmentType, [
            'label' => $type->name_fa,
            'family' => $type->family,
        ]);
    }

    /**
     * مرتب‌سازی پارچه‌های کارگاه برای یک مدل لباس، از بهترین به بدترین.
     *
     * @param  Collection<int, Fabric>  $fabrics
     * @return Collection<int, array{fabric: Fabric, score: array}>
     */
    public function rank(GarmentType $garmentType, Collection $fabrics): Collection
    {
        return $fabrics
            ->map(fn (Fabric $fabric) => ['fabric' => $fabric, 'score' => $this->score($fabric, $garmentType)])
            ->sortByDesc(fn (array $row) => $row['score']['overall'])
            ->values();
    }

    /**
     * بهترین پارچه‌های بانک پایه برای یک مدل لباس («کدام پارچه به این مدل می‌آید؟»).
     *
     * @return Collection<int, array{fabric_type: FabricType, score: array}>
     */
    public function suggestFabricTypes(GarmentType $garmentType, int $limit = 8): Collection
    {
        return FabricType::query()
            ->active()
            ->orderBy('sort')
            ->get()
            ->map(fn (FabricType $type) => ['fabric_type' => $type, 'score' => $this->scoreType($type, $garmentType)])
            ->sortByDesc(fn (array $row) => $row['score']['overall'])
            ->take($limit)
            ->values();
    }

    /**
     * بهترین مدل‌های لباس برای یک پارچه («این پارچه به چه لباسی می‌آید؟»).
     *
     * @return Collection<int, array{garment_type: GarmentType, score: array}>
     */
    public function suggestGarmentTypes(Fabric $fabric, int $limit = 6): Collection
    {
        return GarmentType::query()
            ->active()
            ->orderBy('sort')
            ->get()
            ->map(fn (GarmentType $garmentType) => [
                'garment_type' => $garmentType,
                'score' => $this->score($fabric, $garmentType),
            ])
            ->sortByDesc(fn (array $row) => $row['score']['overall'])
            ->take($limit)
            ->values();
    }

    /** هسته سنجش؛ روی شناسنامه فیزیکی کار می‌کند تا برای پارچه و نوع پارچه یکسان باشد. */
    public function scoreProfile(FabricProfile $profile, GarmentType $garmentType, array $context = []): array
    {
        $prefs = $this->preferences($garmentType);
        $garmentName = $garmentType->name_fa;

        $criteria = [
            $this->drapeCriterion($profile, $prefs, $garmentName),
            $this->weightCriterion($profile, $prefs, $garmentName),
            $this->structureCriterion($profile, $prefs, $garmentName),
            $this->transparencyCriterion($profile, $prefs, $garmentName),
            $this->stretchCriterion($profile, $prefs, $garmentName),
            $this->careCriterion($profile, $prefs),
        ];

        $total = 0.0;

        foreach ($criteria as $criterion) {
            $total += $criterion['score'] * (static::WEIGHTS[$criterion['key']] ?? 0);
        }

        $overall = $total / max(1, array_sum(static::WEIGHTS));

        // خانواده ناسازگار (مثلاً کشباف برای کت ساختارمند) نمره را پایین می‌آورد
        $notes = $this->notes($profile, $prefs, $garmentType, $context);
        $family = $context['family'] ?? null;
        $families = $prefs['families'] ?? null;

        if (is_array($families) && $families !== [] && $family !== null && ! in_array($family, $families, true)) {
            $overall *= 0.85;
            $notes[] = 'خانواده این پارچه ('.(FabricType::FAMILIES[$family] ?? $family).') انتخاب رایجی برای '.$garmentName.' نیست.';
        }

        $overall = (int) max(0, min(100, round($overall)));

        return [
            'overall' => $overall,
            'verdict' => $verdict = $this->verdict($overall),
            'verdict_label' => static::VERDICTS[$verdict],
            'criteria' => $criteria,
            'notes' => array_values(array_unique($notes)),
        ];
    }

    /** ترجیح‌های مؤثر یک نوع لباس: تنظیم دستی روی پیش‌فرض دسته. */
    public function preferences(GarmentType $garmentType): array
    {
        $defaults = static::CATEGORY_DEFAULTS[$garmentType->category] ?? static::CATEGORY_DEFAULTS['top'];

        return array_replace_recursive($defaults, array_filter(
            $garmentType->fabric_preferences ?? [],
            fn ($value) => $value !== null && $value !== '' && $value !== [],
        ));
    }

    public function verdict(int $overall): string
    {
        return match (true) {
            $overall >= 70 => 'suitable',
            $overall >= 45 => 'acceptable',
            default => 'unsuitable',
        };
    }

    protected function drapeCriterion(FabricProfile $profile, array $prefs, string $garmentName): array
    {
        $value = (float) $profile->get('drape');
        $ideal = (float) ($prefs['drape']['ideal'] ?? 0.5);
        $tolerance = (float) ($prefs['drape']['tolerance'] ?? 0.3);
        $score = $this->idealScore($value, $ideal, $tolerance);

        $reason = match (true) {
            $score >= 80 => 'لختی این پارچه («'.$profile->drapeLabel().'») برای '.$garmentName.' درست است.',
            $value > $ideal => 'این پارچه برای '.$garmentName.' لخت‌تر از حد لازم است و فرم را نگه نمی‌دارد.',
            default => 'این پارچه برای '.$garmentName.' ایستاتر از حد لازم است و ریزش نرمی ندارد.',
        };

        return $this->criterion('drape', $score, $reason);
    }

    protected function weightCriterion(FabricProfile $profile, array $prefs, string $garmentName): array
    {
        $value = (float) $profile->get('weight_gsm');
        $min = (float) ($prefs['weight_gsm']['min'] ?? 80);
        $max = (float) ($prefs['weight_gsm']['max'] ?? 320);
        $score = $this->rangeScore($value, $min, $max);

        $reason = match (true) {
            $score >= 95 => 'وزن پارچه («'.$profile->weightLabel().'») در بازه مناسب '.$garmentName.' است.',
            $value > $max => 'این پارچه برای '.$garmentName.' سنگین است؛ بازه مناسب '.Format::number($min).' تا '.Format::number($max).' گرم است.',
            default => 'این پارچه برای '.$garmentName.' سبک است؛ بازه مناسب '.Format::number($min).' تا '.Format::number($max).' گرم است.',
        };

        return $this->criterion('weight', $score, $reason);
    }

    protected function structureCriterion(FabricProfile $profile, array $prefs, string $garmentName): array
    {
        $value = (float) $profile->get('stiffness');
        $ideal = (float) ($prefs['stiffness']['ideal'] ?? 0.4);
        $tolerance = (float) ($prefs['stiffness']['tolerance'] ?? 0.32);
        $score = $this->idealScore($value, $ideal, $tolerance);

        $reason = match (true) {
            $score >= 80 => 'سفتی پارچه برای فرم‌گیری '.$garmentName.' اندازه است.',
            $value > $ideal => 'پارچه سفت‌تر از حد لازم است؛ در '.$garmentName.' حالت جعبه‌ای می‌گیرد.',
            default => 'پارچه نرم‌تر از حد لازم است؛ '.$garmentName.' بدون لایی فرم نمی‌گیرد.',
        };

        return $this->criterion('structure', $score, $reason);
    }

    protected function transparencyCriterion(FabricProfile $profile, array $prefs, string $garmentName): array
    {
        $value = (float) $profile->get('transparency');
        $max = (float) ($prefs['transparency']['max'] ?? 0.3);
        $over = max(0, $value - $max);
        $score = (int) round(max(0, 100 - ($over / 0.5) * 100));

        $reason = match (true) {
            $over <= 0 => 'پوشانندگی پارچه برای '.$garmentName.' کافی است.',
            $value >= 0.6 => 'پارچه شفاف است و بدون آستر برای '.$garmentName.' کار نمی‌کند.',
            default => 'پارچه کمی نازک و نیمه‌شفاف است؛ آستر یا زیرلایه لازم می‌شود.',
        };

        return $this->criterion('transparency', $score, $reason);
    }

    protected function stretchCriterion(FabricProfile $profile, array $prefs, string $garmentName): array
    {
        // هم کلید stretch و هم stretch_weft پذیرفته می‌شود تا با تنظیم انواع لباس بخواند
        $range = $prefs['stretch'] ?? $prefs['stretch_weft'] ?? [];
        $value = $profile->maxStretch();
        $min = (float) ($range['min'] ?? 0);
        $max = (float) ($range['max'] ?? 40);

        $score = match (true) {
            $value < $min => (int) round(max(0, 100 - (($min - $value) / max(1, $min)) * 60)),
            $value > $max => (int) round(max(0, 100 - (($value - $max) / max(10, $max)) * 80)),
            default => 100,
        };

        // برگشت‌پذیری کم، کشسانی زیاد را بی‌ارزش می‌کند
        if ($value >= 20 && (float) $profile->get('recovery') < 75) {
            $score = (int) round($score * 0.7);
        }

        $reason = match (true) {
            $value < $min => 'این پارچه کشسانی ندارد؛ '.$garmentName.' به کشسانی نیاز دارد.',
            $value > $max => 'کشسانی '.Format::percent($value).' برای '.$garmentName.' زیاد است و لباس شل می‌شود.',
            $value >= 5 => 'کشسانی '.Format::percent($value).' برای '.$garmentName.' مناسب است.',
            default => 'پارچه بدون کشسانی است؛ برای '.$garmentName.' اشکالی ندارد.',
        };

        return $this->criterion('stretch', $score, $reason);
    }

    protected function careCriterion(FabricProfile $profile, array $prefs): array
    {
        $difficulty = $profile->difficulty();
        $max = (float) ($prefs['difficulty']['max'] ?? 0.7);
        $score = (int) round(max(0, 100 - $difficulty * 70));

        if ($difficulty > $max) {
            $score = (int) round($score * 0.8);
        }

        $reason = match (true) {
            $difficulty >= 0.65 => 'کار با این پارچه دشوار است؛ سر می‌خورد یا لبه‌اش ریش می‌شود.',
            $difficulty >= 0.4 => 'کار با این پارچه کمی دقت می‌خواهد.',
            default => 'این پارچه رام است و راحت بریده و دوخته می‌شود.',
        };

        return $this->criterion('care', $score, $reason);
    }

    /** یادداشت‌های عملی که مستقل از نمره باید به کاربر گفته شود. */
    protected function notes(FabricProfile $profile, array $prefs, GarmentType $garmentType, array $context): array
    {
        $notes = [];

        if ($profile->isSheer()) {
            $notes[] = 'این پارچه شفاف است؛ آستر یا زیرلایه بگذارید.';
        }

        if ((float) $profile->get('slippage') >= 0.7) {
            $notes[] = 'زیر پارچه کاغذ بگذارید تا هنگام برش سر نخورد.';
        }

        if ((float) $profile->get('fraying') >= 0.6) {
            $notes[] = 'لبه‌ها ریش می‌شود؛ جای دوخت را بیشتر بگیرید و درزها را سردوز کنید.';
        }

        if ((float) $profile->get('shrinkage') > 4) {
            $notes[] = 'آب‌رفت این پارچه '.Format::percent((float) $profile->get('shrinkage')).' است؛ پیش از برش بشویید.';
        }

        if ((bool) $profile->get('has_nap')) {
            $notes[] = 'پارچه خواب‌دار است؛ همه قطعات را یک‌جهت بچینید.';
        }

        if ((float) $profile->get('heat_sensitivity') >= 0.6) {
            $notes[] = 'به حرارت حساس است؛ اتو را کم بگذارید و پارچه محافظ بگذارید.';
        }

        if ($profile->maxStretch() >= 20) {
            $notes[] = 'کشسانی بالا؛ با کوک کشی یا سردوز بدوزید و آزادی را کمتر بگیرید.';
        }

        if (($context['surface_pattern'] ?? null) === 'stripe' || ($context['surface_pattern'] ?? null) === 'plaid') {
            $notes[] = 'طرح پارچه باید در درزهای پهلو روی هم بیفتد؛ پارچه بیشتری لازم است.';
        }

        return $notes;
    }

    protected function criterion(string $key, int|float $score, string $reason): array
    {
        return [
            'key' => $key,
            'label' => static::CRITERIA_LABELS[$key] ?? $key,
            'score' => (int) max(0, min(100, round($score))),
            'reason' => $reason,
        ];
    }

    /** نمره نزدیکی به یک مقدار آرمانی؛ داخل تحمل ۸۰ تا ۱۰۰ و دو برابر تحمل صفر. */
    protected function idealScore(float $value, float $ideal, float $tolerance): int
    {
        $tolerance = max(0.05, $tolerance);
        $diff = abs($value - $ideal);

        if ($diff <= $tolerance) {
            return (int) round(100 - ($diff / $tolerance) * 20);
        }

        return (int) round(max(0, 80 - (($diff - $tolerance) / $tolerance) * 80));
    }

    /** نمره قرار گرفتن در یک بازه؛ خارج از بازه به نسبت نصف پهنای بازه افت می‌کند. */
    protected function rangeScore(float $value, float $min, float $max): int
    {
        if ($value >= $min && $value <= $max) {
            return 100;
        }

        $span = max(1.0, ($max - $min) * 0.5);
        $over = $value > $max ? $value - $max : $min - $value;

        return (int) round(max(0, 100 - ($over / $span) * 100));
    }
}
