<?php

namespace App\Services\Vision;

use App\Support\Jalali;

/**
 * نگاشت ویژگی‌های سیلوئت روی فهرست نوع‌های لباس.
 *
 * روش کار عمداً ساده و قابل توضیح است: برای هر نوع لباس چند «انتظار» عددی ثبت
 * شده (مثلاً «دامن کلوش پهنای لبه‌اش دست‌کم ۱٫۷ برابر کمر است»). هر انتظار با
 * اندازه‌گیری واقعی مقایسه می‌شود و عددی بین ۰ و ۱ می‌دهد؛ امتیاز نهایی میانگین
 * هندسی وزنی این عددهاست، پس یک تناقض آشکار (مثل شکاف پاچه در دامن) کل امتیاز
 * را از بین می‌برد.
 *
 * هیچ مدل آماری و هیچ سرویس بیرونی در کار نیست؛ همه چیز از روی همان چند نسبتی
 * است که در SilhouetteFeatures اندازه گرفته شده، و به همین دلیل هم می‌شود برای
 * هر تصمیم یک جمله فارسی نوشت که «چرا».
 */
class GarmentClassifier
{
    /** سیلوئت کلی لباس. */
    public const SILHOUETTES = [
        'fitted' => 'قالب‌دار',
        'straight' => 'راسته',
        'a_line' => 'خط A',
        'flared' => 'کلوش',
    ];

    /** رده بلندی. */
    public const LENGTHS = [
        'crop' => 'کوتاه (کراپ)',
        'hip' => 'تا باسن',
        'thigh' => 'تا ران',
        'knee' => 'تا زانو',
        'midi' => 'میدی (تا ساق)',
        'maxi' => 'بلند (مچ پا)',
    ];

    /** رده آستین. */
    public const SLEEVES = [
        'sleeveless' => 'بدون آستین',
        'cap' => 'آستین حلقه‌ای',
        'short' => 'آستین کوتاه',
        'long' => 'آستین بلند',
    ];

    /** رده یقه. */
    public const NECKLINES = [
        'high' => 'یقه بسته',
        'round' => 'یقه گرد',
        'v' => 'یقه هفت',
        'square' => 'یقه چهارگوش',
        'boat' => 'یقه قایقی',
    ];

    /**
     * وزن و «نرمی» هر ویژگی در امتیازدهی.
     *
     * نرمی یعنی اگر اندازه‌گیری از بازه انتظار بیرون بزند، تا چه فاصله‌ای امتیاز
     * به صفر می‌رسد.
     *
     * @var array<string, array{weight: float, soft: float}>
     */
    protected const WEIGHTS = [
        'split_ratio' => ['weight' => 3.0, 'soft' => 0.22],
        'neck_depth' => ['weight' => 2.5, 'soft' => 0.02],
        'sleeve_bump' => ['weight' => 1.6, 'soft' => 0.30],
        'length_ratio' => ['weight' => 2.0, 'soft' => 0.60],
        'hem_ratio' => ['weight' => 2.0, 'soft' => 0.32],
        'waist_pinch' => ['weight' => 1.2, 'soft' => 0.16],
    ];

    /**
     * انتظار عددی هر نوع لباس؛ کلیدها همان code جدول garment_types است.
     *
     * @var array<string, array{family: string, rules: array<string, array{0: float, 1: float}>}>
     */
    protected const CATALOGUE = [
        'tshirt' => ['family' => 'top', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.025, 1], 'sleeve_bump' => [1.18, 2.2],
            'length_ratio' => [1.2, 2.0], 'hem_ratio' => [0.75, 1.12], 'waist_pinch' => [-1, 0.08],
        ]],
        'shirt' => ['family' => 'top', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.02, 1], 'sleeve_bump' => [1.18, 2.2],
            'length_ratio' => [1.5, 2.3], 'hem_ratio' => [0.8, 1.18], 'waist_pinch' => [-1, 0.13],
        ]],
        'blouse' => ['family' => 'top', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.03, 1], 'sleeve_bump' => [1.0, 2.0],
            'length_ratio' => [1.2, 2.0], 'hem_ratio' => [0.78, 1.2], 'waist_pinch' => [0.06, 0.4],
        ]],
        'shomiz' => ['family' => 'top', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.02, 1], 'sleeve_bump' => [1.12, 2.2],
            'length_ratio' => [1.9, 2.6], 'hem_ratio' => [1.0, 1.55], 'waist_pinch' => [-1, 0.1],
        ]],
        'top' => ['family' => 'top', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.04, 1], 'sleeve_bump' => [0.85, 1.12],
            'length_ratio' => [1.0, 1.7], 'hem_ratio' => [0.78, 1.15],
        ]],
        'blazer' => ['family' => 'outer', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.06, 1], 'sleeve_bump' => [1.18, 2.2],
            'length_ratio' => [1.5, 2.2], 'hem_ratio' => [0.88, 1.2], 'waist_pinch' => [0.05, 0.32],
        ]],
        'cardigan' => ['family' => 'outer', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.05, 1], 'sleeve_bump' => [1.12, 2.2],
            'length_ratio' => [1.4, 2.3], 'hem_ratio' => [0.85, 1.25], 'waist_pinch' => [-1, 0.12],
        ]],
        'manteau' => ['family' => 'outer', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.03, 1], 'sleeve_bump' => [1.12, 2.2],
            'length_ratio' => [2.3, 3.1], 'hem_ratio' => [0.92, 1.55], 'waist_pinch' => [-1, 0.12],
        ]],
        'coat' => ['family' => 'outer', 'rules' => [
            'split_ratio' => [0, 0.10], 'neck_depth' => [0.03, 1], 'sleeve_bump' => [1.12, 2.2],
            'length_ratio' => [2.6, 3.6], 'hem_ratio' => [0.92, 1.45], 'waist_pinch' => [-1, 0.12],
        ]],
        'skirt_straight' => ['family' => 'bottom', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0, 0.02], 'sleeve_bump' => [0.85, 1.12],
            'length_ratio' => [1.6, 4.8], 'hem_ratio' => [0.82, 1.2],
        ]],
        'skirt_gored' => ['family' => 'bottom', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0, 0.02], 'sleeve_bump' => [0.85, 1.15],
            'length_ratio' => [1.6, 4.8], 'hem_ratio' => [1.22, 1.75],
        ]],
        'skirt_circle' => ['family' => 'bottom', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0, 0.02], 'sleeve_bump' => [0.85, 1.2],
            'length_ratio' => [1.2, 4.2], 'hem_ratio' => [1.75, 6.0],
        ]],
        'pants' => ['family' => 'bottom', 'rules' => [
            'split_ratio' => [0.35, 1], 'neck_depth' => [0, 0.03], 'sleeve_bump' => [0.85, 1.15],
            'length_ratio' => [2.6, 6.5], 'hem_ratio' => [0.6, 1.45],
        ]],
        'shorts' => ['family' => 'bottom', 'rules' => [
            'split_ratio' => [0.3, 1], 'neck_depth' => [0, 0.03], 'sleeve_bump' => [0.85, 1.15],
            'length_ratio' => [0.9, 2.5], 'hem_ratio' => [0.7, 1.5],
        ]],
        'jumpsuit' => ['family' => 'one_piece', 'rules' => [
            'split_ratio' => [0.25, 1], 'neck_depth' => [0.02, 1], 'sleeve_bump' => [0.9, 2.2],
            'length_ratio' => [3.2, 7.5],
        ]],
        'dress' => ['family' => 'one_piece', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0.025, 1], 'sleeve_bump' => [0.9, 2.2],
            'length_ratio' => [2.1, 3.4], 'hem_ratio' => [0.85, 1.9],
        ]],
        'cocktail_dress' => ['family' => 'formal', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0.035, 1], 'sleeve_bump' => [0.85, 1.6],
            'length_ratio' => [2.0, 2.9], 'hem_ratio' => [0.85, 1.5], 'waist_pinch' => [0.08, 0.45],
        ]],
        'evening_dress' => ['family' => 'formal', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0.035, 1], 'sleeve_bump' => [0.85, 1.4],
            'length_ratio' => [2.9, 4.5], 'hem_ratio' => [0.95, 2.2], 'waist_pinch' => [0.05, 0.45],
        ]],
        'bridal_dress' => ['family' => 'formal', 'rules' => [
            'split_ratio' => [0, 0.12], 'neck_depth' => [0.035, 1], 'sleeve_bump' => [0.85, 1.6],
            'length_ratio' => [2.6, 4.5], 'hem_ratio' => [1.8, 6.0],
        ]],
    ];

    /**
     * نام فارسی نوع‌ها برای زمانی که فهرست پایگاه داده در دسترس نیست.
     *
     * @var array<string, string>
     */
    public const NAMES = [
        'shirt' => 'پیراهن', 'blouse' => 'بلوز', 'shomiz' => 'شومیز', 'top' => 'تاپ',
        'tshirt' => 'تی‌شرت', 'blazer' => 'کت', 'cardigan' => 'ژاکت', 'manteau' => 'مانتو',
        'coat' => 'پالتو', 'skirt_straight' => 'دامن راسته', 'skirt_gored' => 'دامن ترک',
        'skirt_circle' => 'دامن کلوش', 'pants' => 'شلوار', 'shorts' => 'شلوارک',
        'jumpsuit' => 'سرهمی', 'dress' => 'پیراهن یک‌تکه', 'evening_dress' => 'لباس شب',
        'cocktail_dress' => 'لباس مجلسی', 'bridal_dress' => 'لباس عروس',
    ];

    /** نوع‌هایی که از روی شکل بیرونی عملاً از هم جدا نمی‌شوند. */
    protected const LOOKALIKES = [
        'blazer' => ['cardigan', 'manteau'],
        'cardigan' => ['blazer', 'manteau'],
        'manteau' => ['coat', 'cardigan'],
        'coat' => ['manteau'],
        'dress' => ['cocktail_dress', 'evening_dress'],
        'cocktail_dress' => ['dress', 'evening_dress'],
        'evening_dress' => ['dress', 'bridal_dress'],
        'bridal_dress' => ['evening_dress'],
        'blouse' => ['shirt', 'shomiz'],
        'shirt' => ['blouse', 'shomiz'],
        'shomiz' => ['shirt', 'blouse'],
        'skirt_gored' => ['skirt_circle', 'skirt_straight'],
    ];

    /**
     * تشخیص کامل: نوع لباس، سه گزینه بعدی، سیلوئت، بلندی، آستین و یقه.
     *
     * @return array<string, mixed>
     */
    public function classify(SilhouetteFeatures $features): array
    {
        $scores = [];

        foreach (self::CATALOGUE as $code => $definition) {
            $scores[$code] = $this->score($features, $definition['rules']);
        }

        arsort($scores);
        $ranked = array_keys($scores);
        $best = $ranked[0];
        $second = $scores[$ranked[1] ?? $best] ?? 0.0;

        $margin = $scores[$best] > 0 ? ($scores[$best] - $second) / $scores[$best] : 0.0;
        $distinctiveness = $features->distinctiveness();
        $quality = $features->quality();

        $confidence = $scores[$best]
            * (0.62 + 0.38 * min(1.0, $margin / 0.22))
            * (0.28 + 0.72 * $distinctiveness)
            * $quality;

        $confidence = round(max(0.0, min(0.95, $confidence)), 3);

        $family = self::CATALOGUE[$best]['family'];
        $evidence = $this->evidence($features, $family);

        $candidates = [];

        foreach (array_slice($ranked, 0, 4) as $code) {
            $candidates[] = [
                'code' => $code,
                'family' => self::CATALOGUE[$code]['family'],
                'score' => round($scores[$code], 3),
                'confidence' => round(min(0.95, $scores[$code] * (0.28 + 0.72 * $distinctiveness) * $quality), 3),
                'reason' => $this->candidateReason($features, $code, $scores[$code]),
            ];
        }

        return [
            'garment' => [
                'code' => $best,
                'family' => $family,
                'score' => round($scores[$best], 3),
                'confidence' => $confidence,
            ],
            'alternatives' => array_slice($candidates, 1, 3),
            'candidates' => $candidates,
            'scores' => array_map(fn ($value) => round($value, 3), $scores),
            'silhouette' => $this->silhouette($features),
            'length' => $this->length($features, $family),
            'sleeve' => $this->sleeve($features, $family),
            'neckline' => $this->neckline($features, $family),
            'evidence' => $evidence,
            'confidence' => $confidence,
            'margin' => round($margin, 3),
            'distinctiveness' => $distinctiveness,
            'quality' => $quality,
            'warnings' => $this->warnings($features, $best, $confidence, $margin),
        ];
    }

    /** برچسب فارسی سیلوئت. */
    public static function silhouetteLabel(string $value): string
    {
        return self::SILHOUETTES[$value] ?? $value;
    }

    /** خانواده هر نوع لباس (برای انتخاب الگوی پایه). */
    public static function familyOf(string $code): ?string
    {
        return self::CATALOGUE[$code]['family'] ?? null;
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::CATALOGUE);
    }

    /** نام فارسی یک نوع لباس. */
    public static function name(string $code): string
    {
        return self::NAMES[$code] ?? $code;
    }

    /**
     * امتیاز یک نوع لباس: میانگین هندسی وزنی برازش هر انتظار.
     *
     * @param  array<string, array{0: float, 1: float}>  $rules
     */
    protected function score(SilhouetteFeatures $features, array $rules): float
    {
        $sum = 0.0;
        $weights = 0.0;

        foreach ($rules as $key => [$low, $high]) {
            $weight = self::WEIGHTS[$key]['weight'];
            $fit = max(0.02, $this->membership($this->value($features, $key), $low, $high, self::WEIGHTS[$key]['soft']));

            $sum += $weight * log($fit);
            $weights += $weight;
        }

        return $weights > 0 ? exp($sum / $weights) : 0.0;
    }

    /** برازش یک عدد با بازه انتظار: ۱ داخل بازه، افت خطی بیرون آن. */
    protected function membership(float $value, float $low, float $high, float $soft): float
    {
        if ($value >= $low && $value <= $high) {
            return 1.0;
        }

        $distance = $value < $low ? $low - $value : $value - $high;

        return max(0.0, 1 - $distance / max(1e-6, $soft));
    }

    protected function value(SilhouetteFeatures $features, string $key): float
    {
        return match ($key) {
            'split_ratio' => $features->splitRatio,
            'neck_depth' => $features->neckDepth,
            'sleeve_bump' => $features->sleeveBump,
            'length_ratio' => $features->lengthRatio,
            'hem_ratio' => $features->hemRatio,
            'waist_pinch' => $features->waistPinch,
            default => 0.0,
        };
    }

    /**
     * جمله‌های فارسی «چرا»: هر جمله یک اندازه‌گیری واقعی و نتیجه‌ای که از آن گرفته شده.
     *
     * @return array<int, array{key: string, text: string}>
     */
    protected function evidence(SilhouetteFeatures $features, string $family): array
    {
        $upper = in_array($family, ['top', 'outer', 'one_piece', 'formal'], true) ? 'سرشانه' : 'کمر';
        $evidence = [];

        $evidence[] = [
            'key' => 'hem_ratio',
            'text' => 'نسبت پهنای لبه پایین به '.$upper.' '.$this->n($features->hemRatio).' است، '
                .match (true) {
                    $features->hemRatio >= 1.75 => 'پس لباس خیلی باز می‌شود و کلوش تشخیص داده شد.',
                    $features->hemRatio >= 1.22 => 'پس شکل خط A دارد.',
                    $features->hemRatio >= 0.85 => 'پس لبه پایین تقریباً هم‌اندازه بالاست و شکل راسته است.',
                    default => 'پس لباس رو به پایین تنگ می‌شود.',
                },
        ];

        if ($features->splitRatio > 0.05) {
            $evidence[] = [
                'key' => 'split_ratio',
                'text' => $this->percent($features->splitRatio).' سطرهای پایین شکل به دو شاخه جدا تقسیم شده‌اند'
                    .($features->splitStart !== null ? ' (شروع شکاف از '.$this->percent($features->splitStart).' قد)' : '')
                    .'؛ این نشانه پاچه است، نه دامن.',
            ];
        } else {
            $evidence[] = [
                'key' => 'split_ratio',
                'text' => 'هیچ سطری به دو شاخه جدا تقسیم نشد، پس شکل یک‌پارچه است و پاچه ندارد.',
            ];
        }

        $evidence[] = [
            'key' => 'length_ratio',
            'text' => 'قد شکل '.$this->n($features->lengthRatio).' برابر پهنای بالای آن است'
                .'؛ '.match (true) {
                    $features->lengthRatio >= 2.6 => 'یعنی لباس بلند است.',
                    $features->lengthRatio >= 1.85 => 'یعنی از باسن پایین‌تر می‌آید.',
                    $features->lengthRatio >= 1.2 => 'یعنی در حد یک بالاتنه یا دامن کوتاه است.',
                    default => 'یعنی شکل کوتاه و پهن است.',
                },
        ];

        if (abs($features->waistPinch) > 0.03) {
            $evidence[] = [
                'key' => 'waist_pinch',
                'text' => $features->waistPinch > 0
                    ? 'کمر '.$this->percent($features->waistPinch).' از میانگین سینه و باسن باریک‌تر است، پس لباس قالب‌دار است.'
                    : 'کمر از میانگین سینه و باسن پهن‌تر است، پس لباس گشاد یا آزاد است.',
            ];
        }

        if (in_array($family, ['top', 'outer', 'one_piece', 'formal'], true)) {
            $evidence[] = [
                'key' => 'sleeve_bump',
                'text' => $features->sleeveBump >= 1.12
                    ? 'بالای شکل '.$this->n($features->sleeveBump).' برابر تنه پهن‌تر است، پس آستین دارد.'
                    : 'بالای شکل از تنه پهن‌تر نیست ('.$this->n($features->sleeveBump).' برابر)، پس آستینی دیده نشد.',
            ];

            $evidence[] = [
                'key' => 'neck_depth',
                'text' => $features->neckDepth >= 0.02
                    ? 'لبه بالایی در میانه به اندازه '.$this->percent($features->neckDepth).' قد گود شده؛ یعنی یقه دارد.'
                    : 'لبه بالایی صاف است و گودی یقه دیده نشد.',
            ];
        }

        $evidence[] = [
            'key' => 'symmetry',
            'text' => 'قرینگی چپ و راست '.$this->percent($features->symmetry).' است'
                .($features->symmetry >= 0.85
                    ? '، پس شکل صاف و روبه‌رو دیده شده.'
                    : '؛ چون کامل قرینه نیست، به اندازه‌ها کمتر می‌شود اعتماد کرد.'),
        ];

        return $evidence;
    }

    /** یک جمله کوتاه درباره اینکه هر گزینه چرا در فهرست است. */
    protected function candidateReason(SilhouetteFeatures $features, string $code, float $score): string
    {
        $rules = self::CATALOGUE[$code]['rules'];
        $worst = null;
        $worstFit = 1.1;

        foreach ($rules as $key => [$low, $high]) {
            $fit = $this->membership($this->value($features, $key), $low, $high, self::WEIGHTS[$key]['soft']);

            if ($fit < $worstFit) {
                $worstFit = $fit;
                $worst = $key;
            }
        }

        if ($worstFit >= 0.999) {
            return 'همه اندازه‌های سنجیده‌شده در محدوده انتظار این نوع لباس است (امتیاز '.$this->percent($score).').';
        }

        return 'امتیاز '.$this->percent($score).'؛ کم‌ترین برازش مربوط به «'.$this->featureLabel($worst).'» با اندازه '
            .$this->n($this->value($features, $worst)).' است (انتظار '.$this->n($rules[$worst][0]).' تا '
            .$this->n($rules[$worst][1]).').';
    }

    /** سیلوئت کلی از روی باز شدن لبه و فرورفتگی کمر. */
    protected function silhouette(SilhouetteFeatures $features): array
    {
        [$value, $boundary, $scale] = match (true) {
            $features->hemRatio >= 1.60 => ['flared', 1.60, 0.35],
            $features->hemRatio >= 1.22 => ['a_line', 1.22, 0.25],
            $features->waistPinch >= 0.10 => ['fitted', 0.10, 0.10],
            default => ['straight', 1.22, 0.25],
        };

        $measured = $value === 'fitted' ? $features->waistPinch : $features->hemRatio;
        $confidence = round(min(0.95, 0.55 + 0.45 * min(1.0, abs($measured - $boundary) / $scale)) * $features->quality(), 3);

        return [
            'value' => $value,
            'label' => self::SILHOUETTES[$value],
            'confidence' => $confidence,
            'reason' => match ($value) {
                'flared' => 'لبه پایین '.$this->n($features->hemRatio).' برابر بالاست، پس کلوش است.',
                'a_line' => 'لبه پایین '.$this->n($features->hemRatio).' برابر بالاست، پس خط A است.',
                'fitted' => 'کمر '.$this->percent($features->waistPinch).' باریک‌تر از سینه و باسن است، پس قالب‌دار است.',
                default => 'نه لبه پایین باز شده و نه کمر فرو رفته، پس راسته است.',
            },
        ];
    }

    /** رده بلندی؛ آستانه‌ها به خانواده لباس بستگی دارد. */
    protected function length(SilhouetteFeatures $features, string $family): array
    {
        $steps = match ($family) {
            'bottom' => [['crop', 2.0], ['thigh', 2.45], ['knee', 3.05], ['midi', 3.9], ['maxi', INF]],
            'one_piece', 'formal' => [['thigh', 2.25], ['knee', 2.75], ['midi', 3.25], ['maxi', INF]],
            default => [['crop', 1.25], ['hip', 1.75], ['thigh', 2.15], ['midi', 2.7], ['maxi', INF]],
        };

        $value = 'maxi';
        $previous = 0.0;
        $boundary = 1.0;

        foreach ($steps as [$code, $limit]) {
            if ($features->lengthRatio < $limit) {
                $value = $code;
                $boundary = min(abs($features->lengthRatio - $previous), abs($features->lengthRatio - $limit));

                break;
            }

            $previous = $limit;
        }

        return [
            'value' => $value,
            'label' => self::LENGTHS[$value],
            'confidence' => round(min(0.9, 0.5 + 0.5 * min(1.0, $boundary / 0.4)) * $features->quality(), 3),
            'reason' => 'قد شکل '.$this->n($features->lengthRatio).' برابر پهنای بالای آن است، پس بلندی «'
                .self::LENGTHS[$value].'» تخمین زده شد. این تخمین فرض می‌کند تمام لباس در کادر دیده می‌شود.',
        ];
    }

    /** رده آستین از روی برجستگی بالای شکل. */
    protected function sleeve(SilhouetteFeatures $features, string $family): array
    {
        if (in_array($family, ['bottom'], true)) {
            return [
                'value' => null,
                'label' => 'ندارد',
                'confidence' => 0.9,
                'reason' => 'این لباس پایین‌تنه است و آستین ندارد.',
            ];
        }

        $value = match (true) {
            $features->sleeveBump < 1.12 => 'sleeveless',
            $features->sleeveSpan < 0.12 => 'cap',
            $features->sleeveSpan < 0.30 => 'short',
            default => 'long',
        };

        return [
            'value' => $value,
            'label' => self::SLEEVES[$value],
            'confidence' => round(min(0.8, 0.45 + 0.45 * min(1.0, abs($features->sleeveBump - 1.12) / 0.25)) * $features->quality(), 3),
            'reason' => 'پهنای بالای شکل '.$this->n($features->sleeveBump).' برابر تنه است و این پهنا تا '
                .$this->percent($features->sleeveSpan).' قد ادامه دارد، پس «'.self::SLEEVES[$value]
                .'» حدس زده شد. اگر عکس آستین را جمع‌شده نشان دهد این حدس اشتباه می‌شود.',
        ];
    }

    /** شکل یقه از روی نیم‌رخ لبه بالایی. */
    protected function neckline(SilhouetteFeatures $features, string $family): array
    {
        if ($family === 'bottom') {
            return [
                'value' => null,
                'label' => 'ندارد',
                'confidence' => 0.9,
                'reason' => 'این لباس پایین‌تنه است و یقه ندارد.',
            ];
        }

        $value = match (true) {
            $features->neckDepth < 0.02 => 'high',
            $features->neckWidth > 0.42 && $features->neckDepth < 0.07 => 'boat',
            $features->neckFullness > 0.82 => 'square',
            $features->neckFullness < 0.62 => 'v',
            default => 'round',
        };

        return [
            'value' => $value,
            'label' => self::NECKLINES[$value],
            'confidence' => round(min(0.75, 0.4 + 0.5 * min(1.0, $features->neckDepth / 0.06)) * $features->quality(), 3),
            'reason' => 'گودی یقه '.$this->percent($features->neckDepth).' قد، پهنای آن '
                .$this->percent($features->neckWidth).' پهنا و پُری آن '.$this->n($features->neckFullness)
                .' است (هفت نزدیک ۰٫۵، گرد نزدیک ۰٫۸ و چهارگوش نزدیک ۱)، پس «'.self::NECKLINES[$value].'» تشخیص داده شد.',
        ];
    }

    /**
     * هشدارهای صادقانه‌ای که کاربر باید ببیند.
     *
     * @return array<int, string>
     */
    protected function warnings(SilhouetteFeatures $features, string $best, float $confidence, float $margin): array
    {
        $warnings = $features->notes;

        if ($confidence < 0.45) {
            $warnings[] = 'اطمینان این تشخیص پایین است؛ بهتر است نوع لباس را خودتان از فهرست انتخاب کنید.';
        }

        if ($features->distinctiveness() < 0.35) {
            $warnings[] = 'شکل ورودی تقریباً یک مستطیل ساده است: نه یقه، نه آستین، نه فرورفتگی کمر و نه باز شدن دامن دیده شد. با این شکل هر تشخیصی حدس است.';
        }

        if ($margin < 0.08) {
            $warnings[] = 'چند نوع لباس امتیاز تقریباً برابر گرفتند؛ از میان گزینه‌های پیشنهادی انتخاب کنید.';
        }

        if ($features->symmetry < 0.8 && $features->symmetry > 0) {
            $warnings[] = 'شکل کاملاً قرینه نیست (قرینگی '.$this->percent($features->symmetry).'). اگر عکس از زاویه گرفته شده، پهناهای اندازه‌گیری‌شده کمی خطا دارند.';
        }

        if (array_intersect(['start', 'end'], $features->touchedEdges) !== []) {
            $warnings[] = 'لباس به لبه کناری کادر چسبیده؛ ممکن است بخشی از پهنای آن بیرون از عکس مانده باشد.';
        }

        if (($lookalikes = self::LOOKALIKES[$best] ?? []) !== []) {
            $warnings[] = 'سیلوئت «'.self::name($best).'» با '
                .implode(' و ', array_map(fn ($code) => '«'.self::name($code).'»', $lookalikes))
                .' تقریباً یکی است؛ فرقشان در جزئیاتی مثل یقه، دکمه و پارچه است که در شکل بیرونی دیده نمی‌شود.';
        }

        return array_values(array_unique($warnings));
    }

    protected function featureLabel(string $key): string
    {
        return match ($key) {
            'split_ratio' => 'شکاف دو پاچه',
            'neck_depth' => 'گودی یقه',
            'sleeve_bump' => 'برجستگی آستین',
            'length_ratio' => 'نسبت قد به پهنا',
            'hem_ratio' => 'باز شدن لبه پایین',
            'waist_pinch' => 'فرورفتگی کمر',
            default => $key,
        };
    }

    /** عدد اعشاری با رقم فارسی و بدون صفر اضافه. */
    protected function n(float $value): string
    {
        if (is_infinite($value)) {
            return '∞';
        }

        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return Jalali::digits($formatted === '' || $formatted === '-' ? '0' : $formatted);
    }

    protected function percent(float $value): string
    {
        return Jalali::digits(number_format($value * 100, 0)).'٪';
    }
}
