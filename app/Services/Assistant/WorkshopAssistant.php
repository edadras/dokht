<?php

namespace App\Services\Assistant;

use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\Project;
use App\Services\Fabric\FabricCompatibilityService;
use App\Services\Rules\RuleEngine;
use App\Support\Format;

/**
 * دستیار کارگاه؛ کاملاً محلی و بدون سرویس بیرونی.
 *
 * پرسش کاربر با چند کلیدواژه به یک «موضوع» نگاشت می‌شود و پاسخ از ترکیب سه منبع
 * ساخته می‌شود: نمره سازگاری پارچه، موتور قواعد و آخرین شبیه‌سازی پروژه. همیشه
 * استدلال (اینکه کدام قاعده یا کدام معیار پاسخ را ساخته) هم برگردانده می‌شود تا
 * کاربر پاسخ را باور کند و یاد بگیرد.
 */
class WorkshopAssistant
{
    /** پرسش‌های نمونه که به‌شکل تراشه روی صفحه نشان داده می‌شود. */
    public const EXAMPLES = [
        'این پارچه برای این مدل مناسب است؟',
        'چقدر آزادی بدهم؟',
        'آستر لازم دارد؟',
        'چند متر پارچه لازم است؟',
        'با کدام سوزن و کوک بدوزم؟',
        'هنگام برش مراقب چه چیزی باشم؟',
    ];

    public const TOPICS = [
        'consumption' => ['label' => 'مصرف پارچه', 'keywords' => ['چند متر', 'مصرف', 'متراژ', 'چقدر پارچه', 'متر پارچه']],
        'sewing' => ['label' => 'دوخت و درزبندی', 'keywords' => ['سوزن', 'کوک', 'نخ', 'سردوز', 'درزبندی', 'بدوزم', 'دوخت', 'چرخ']],
        'pressing' => ['label' => 'اتو و نگهداری', 'keywords' => ['اتو', 'بخار', 'پرس', 'شست', 'بشورم', 'خشک‌کن', 'نگهداری']],
        'cutting' => ['label' => 'برش و چیدمان', 'keywords' => ['برش', 'ببرم', 'قیچی', 'چیدمان', 'بریدن', 'کاتر']],
        'lining' => ['label' => 'آستر و لایی', 'keywords' => ['آستر', 'لایی', 'زیرلایه', 'شفاف', 'بدن‌نما']],
        'stretch' => ['label' => 'کشسانی پارچه', 'keywords' => ['کشش', 'کشسان', 'کشی', 'کش می‌آید', 'جرسی', 'تریکو']],
        'ease' => ['label' => 'آزادی لباس', 'keywords' => ['آزادی', 'گشاد', 'تنگ', 'جا بده', 'راحتی', 'سایز']],
        'fabric_fit' => ['label' => 'تناسب پارچه با مدل', 'keywords' => ['مناسب', 'می‌آید', 'بیاد', 'خوب است', 'انتخاب', 'پارچه']],
    ];

    public function __construct(
        protected FabricCompatibilityService $compatibility,
        protected RuleEngine $engine,
    ) {}

    /**
     * پاسخ به یک پرسش.
     *
     * @return array{question:string, topic:string, topic_label:string, headline:string, points:array<int,string>, reasons:array<int,array{label:string,text:string}>}
     */
    public function ask(
        string $question,
        ?Fabric $fabric = null,
        ?GarmentType $garmentType = null,
        ?Project $project = null,
    ): array {
        $fabric ??= $project?->fabric;
        $garmentType ??= $project?->garmentType;

        $topic = $this->detectTopic($question);

        $answer = match ($topic) {
            'fabric_fit' => $this->answerFabricFit($fabric, $garmentType),
            'ease' => $this->answerEase($fabric, $garmentType, $project),
            'lining' => $this->answerLining($fabric, $garmentType),
            'stretch' => $this->answerStretch($fabric),
            'cutting' => $this->answerCutting($fabric, $garmentType),
            'sewing' => $this->answerSewing($fabric, $garmentType),
            'pressing' => $this->answerPressing($fabric),
            'consumption' => $this->answerConsumption($fabric, $garmentType),
            default => $this->answerOverview($fabric, $garmentType),
        };

        $answer = array_merge([
            'question' => $question,
            'topic' => $topic,
            'topic_label' => static::TOPICS[$topic]['label'] ?? 'پاسخ کلی',
            'headline' => '',
            'points' => [],
            'reasons' => [],
        ], $answer);

        foreach ($this->simulationReasons($project) as $reason) {
            $answer['reasons'][] = $reason;
        }

        return $answer;
    }

    /** موضوع پرسش را از کلیدواژه‌ها حدس می‌زند. */
    public function detectTopic(string $question): string
    {
        $question = trim($question);

        foreach (static::TOPICS as $topic => $definition) {
            foreach ($definition['keywords'] as $keyword) {
                if (mb_strpos($question, $keyword) !== false) {
                    return $topic;
                }
            }
        }

        return 'overview';
    }

    /** جمله معرفی پارچه که در چند پاسخ تکرار می‌شود. */
    public function fabricSummary(Fabric $fabric): string
    {
        $profile = $fabric->profile();

        return sprintf(
            'پارچه «%s» %s کشسانی عرضی و لختی %s دارد؛ وزن آن %s گرم بر متر مربع (%s) است.',
            $fabric->displayName(),
            Format::percent((float) $profile->get('stretch_weft')),
            $profile->drapeLabel(),
            Format::number((float) $profile->get('weight_gsm')),
            $profile->weightLabel(),
        );
    }

    protected function answerFabricFit(?Fabric $fabric, ?GarmentType $garmentType): array
    {
        if ($fabric === null) {
            return $this->needsFabric();
        }

        if ($garmentType === null) {
            $suggestions = $this->compatibility->suggestGarmentTypes($fabric, 4);

            return [
                'headline' => $this->fabricSummary($fabric).' برای انتخاب مدل، یکی از این‌ها بیشترین تناسب را دارد.',
                'points' => $suggestions
                    ->map(fn (array $row) => $row['garment_type']->name_fa.' — نمره '.Format::number($row['score']['overall']).' از ۱۰۰ ('.$row['score']['verdict_label'].')')
                    ->all(),
                'reasons' => [[
                    'label' => 'معیار سنجش',
                    'text' => 'نمره‌ها از مقایسه لختی، وزن، سفتی، پوشانندگی و کشسانی پارچه با ترجیح هر مدل لباس می‌آید.',
                ]],
            ];
        }

        $score = $this->compatibility->score($fabric, $garmentType);

        $points = collect($score['criteria'])
            ->sortBy('score')
            ->take(3)
            ->map(fn (array $criterion) => $criterion['label'].': '.$criterion['reason'])
            ->values()
            ->all();

        return [
            'headline' => sprintf(
                '%s این پارچه برای «%s» %s است (نمره %s از ۱۰۰).',
                $this->fabricSummary($fabric),
                $garmentType->name_fa,
                $score['verdict_label'],
                Format::number($score['overall']),
            ),
            'points' => array_merge($points, $score['notes']),
            'reasons' => collect($score['criteria'])->map(fn (array $criterion) => [
                'label' => 'معیار «'.$criterion['label'].'»: '.Format::number($criterion['score']).' از ۱۰۰',
                'text' => $criterion['reason'],
            ])->all(),
        ];
    }

    protected function answerEase(?Fabric $fabric, ?GarmentType $garmentType, ?Project $project): array
    {
        if ($garmentType === null) {
            return [
                'headline' => 'برای پیشنهاد آزادی، اول مدل لباس را انتخاب کنید.',
                'points' => ['آزادی متوسط برای بالاتنه معمولاً ۴ تا ۶ سانتی‌متر روی دور سینه است.'],
            ];
        }

        $base = $project?->effectiveEase() ?? $garmentType->ease();
        $results = $this->engine->evaluate('pattern', $this->engine->factsFor($fabric, $garmentType, $project?->pattern));
        $applied = $this->engine->applyEase($base, $results);

        $labels = ['bust' => 'دور سینه', 'waist' => 'دور کمر', 'hip' => 'دور باسن', 'bicep' => 'دور بازو'];
        $points = [];

        foreach ($labels as $key => $label) {
            if (! isset($applied[$key])) {
                continue;
            }

            $points[] = $label.': '.Format::cm((float) $applied[$key]).
                (isset($base[$key]) && abs((float) $base[$key] - (float) $applied[$key]) > 0.01
                    ? ' (پیش‌فرض مدل '.Format::cm((float) $base[$key]).' بود)'
                    : '');
        }

        $headline = $fabric
            ? $this->fabricSummary($fabric).' با توجه به این پارچه، آزادی پیشنهادی زیر است.'
            : 'آزادی پیشنهادی برای «'.$garmentType->name_fa.'» به شرح زیر است.';

        return [
            'headline' => $headline,
            'points' => $points,
            'reasons' => $this->ruleReasons($results, ['set_ease', 'no_darts', 'note']),
        ];
    }

    protected function answerLining(?Fabric $fabric, ?GarmentType $garmentType): array
    {
        if ($fabric === null) {
            return $this->needsFabric();
        }

        $profile = $fabric->profile();
        $results = $this->engine->evaluateScopes(['fabric', 'pattern'], $this->engine->factsFor($fabric, $garmentType));
        $needsLining = $this->engine->hasAction($results, 'suggest_lining');
        $needsUnder = $this->engine->hasAction($results, 'suggest_underlining');

        $headline = match (true) {
            $needsLining => 'بله، این پارچه آستر لازم دارد؛ شفافیت آن '.Format::ratio((float) $profile->get('transparency')).' است.',
            $needsUnder => 'آستر اجباری نیست، اما یک زیرلایه کار را بهتر می‌کند؛ شفافیت پارچه '.Format::ratio((float) $profile->get('transparency')).' است.',
            default => 'نه، این پارچه از نظر پوشانندگی آستر لازم ندارد (شفافیت '.Format::ratio((float) $profile->get('transparency')).').',
        };

        $points = [];

        if ($needsLining || $needsUnder) {
            $points[] = 'پارچه آستری سبک (حدود ۷۰ گرم) هم‌رنگ یا یک درجه روشن‌تر انتخاب کنید.';
            $points[] = 'آستر را کمی گشادتر از رویه ببرید تا لباس را نکشد.';
        }

        if ((float) $profile->get('weight_gsm') < 120) {
            $points[] = 'برای یقه، سرآستین و جای دکمه لایی چسب نازک بگذارید.';
        }

        return [
            'headline' => $headline,
            'points' => $points,
            'reasons' => $this->ruleReasons($results, ['suggest_lining', 'suggest_underlining', 'suggest_interfacing']),
        ];
    }

    protected function answerStretch(?Fabric $fabric): array
    {
        if ($fabric === null) {
            return $this->needsFabric();
        }

        $profile = $fabric->profile();

        $points = [
            'کشسانی عرض (پود): '.Format::percent((float) $profile->get('stretch_weft')),
            'کشسانی طول (تار): '.Format::percent((float) $profile->get('stretch_warp')),
            'کشسانی اریب: '.Format::percent((float) $profile->get('stretch_bias')),
            'برگشت‌پذیری: '.Format::percent((float) $profile->get('recovery')),
        ];

        if ($profile->maxStretch() >= 20) {
            $points[] = 'آزادی را ۲ تا ۳ سانتی‌متر کمتر بگیرید (آزادی منفی) و درزها را سردوز کنید.';
        } elseif ($profile->maxStretch() <= 5) {
            $points[] = 'این پارچه کشسانی ندارد؛ آزادی را کامل و طبق مدل بگیرید.';
        }

        if ((float) $profile->get('recovery') < 80 && $profile->maxStretch() >= 20) {
            $points[] = 'برگشت‌پذیری پایین است؛ در مدل چسبان، زانو و آرنج شل می‌ماند.';
        }

        return [
            'headline' => $this->fabricSummary($fabric),
            'points' => $points,
            'reasons' => [[
                'label' => 'منبع اعداد',
                'text' => 'شناسنامه پارچه پایه «'.$fabric->typeName().'» به‌همراه بازنویسی‌های خود شما.',
            ]],
        ];
    }

    protected function answerCutting(?Fabric $fabric, ?GarmentType $garmentType): array
    {
        if ($fabric === null) {
            return $this->needsFabric();
        }

        $results = $this->engine->evaluate('cutting', $this->engine->factsFor($fabric, $garmentType));
        $messages = $this->engine->messages($results);

        return [
            'headline' => $messages === []
                ? 'این پارچه هنگام برش دردسر خاصی ندارد؛ روی میز صاف و در راستای پارچه برش بزنید.'
                : 'هنگام برش این پارچه به این نکته‌ها توجه کنید.',
            'points' => $messages ?: [
                'لبه کناری پارچه را با لبه میز موازی کنید تا راستای پارچه کج نشود.',
                'الگو را با وزنه یا سنجاق داخل جای دوخت ثابت کنید.',
            ],
            'reasons' => $this->ruleReasons($results),
        ];
    }

    protected function answerSewing(?Fabric $fabric, ?GarmentType $garmentType): array
    {
        if ($fabric === null) {
            return $this->needsFabric();
        }

        $results = $this->engine->evaluate('production', $this->engine->factsFor($fabric, $garmentType));
        $hints = $this->engine->sewingHints($results);
        $sewing = $fabric->fabricType?->sewing ?? [];

        $points = array_values(array_filter([
            isset($hints['needle']) ? 'سوزن: '.$hints['needle'] : (isset($sewing['needle']) ? 'سوزن: '.$sewing['needle'] : null),
            isset($hints['stitch']) ? 'کوک: '.$hints['stitch'] : (isset($sewing['stitch']) ? 'کوک: '.$sewing['stitch'] : null),
            isset($sewing['seam']) ? 'درزبندی: '.$sewing['seam'] : null,
            isset($sewing['tip']) ? 'نکته: '.$sewing['tip'] : null,
        ]));

        foreach ($this->engine->messages($results) as $message) {
            $points[] = $message;
        }

        return [
            'headline' => 'برای دوخت «'.$fabric->typeName().'» این تنظیمات را روی چرخ بگذارید.',
            'points' => array_values(array_unique($points)),
            'reasons' => array_merge(
                [['label' => 'شناسنامه پارچه پایه', 'text' => 'پیشنهاد سوزن و کوک از بانک پارچه «'.$fabric->typeName().'» خوانده شد.']],
                $this->ruleReasons($results),
            ),
        ];
    }

    protected function answerPressing(?Fabric $fabric): array
    {
        if ($fabric === null) {
            return $this->needsFabric();
        }

        $profile = $fabric->profile();
        $care = $fabric->fabricType?->care ?? [];
        $results = $this->engine->evaluate('production', $this->engine->factsFor($fabric));

        $points = array_values(array_filter([
            isset($care['iron']) ? 'اتو: '.$care['iron'] : null,
            isset($care['wash']) ? 'شست‌وشو: '.$care['wash'] : null,
            isset($care['dryer']) ? 'خشک‌کن: '.$care['dryer'] : null,
        ]));

        if ((float) $profile->get('heat_sensitivity') >= 0.6) {
            $points[] = 'حساسیت به حرارت بالاست؛ همیشه یک پارچه نخی نازک روی کار بگذارید.';
        }

        if ((float) $profile->get('shrinkage') > 4) {
            $points[] = 'آب‌رفت '.Format::percent((float) $profile->get('shrinkage')).' است؛ پیش از برش پارچه را بشویید.';
        }

        return [
            'headline' => 'راهنمای اتو و نگهداری «'.$fabric->typeName().'».',
            'points' => $points,
            'reasons' => $this->ruleReasons($results, ['iron_temp', 'note', 'prewash']),
        ];
    }

    protected function answerConsumption(?Fabric $fabric, ?GarmentType $garmentType): array
    {
        if ($garmentType === null) {
            return [
                'headline' => 'برای برآورد متراژ، مدل لباس را انتخاب کنید.',
                'points' => ['برآورد سرانگشتی: بالاتنه ۱.۵ متر، دامن و شلوار ۱.۳ متر، پیراهن بلند ۲.۵ متر با عرض ۱۴۰.'],
            ];
        }

        $estimate = $this->estimate($fabric, $garmentType);

        return [
            'headline' => sprintf(
                'برای «%s» حدود %s پارچه با عرض %s لازم است.',
                $garmentType->name_fa,
                Format::number($estimate['meters'], 1).' متر',
                Format::cm($estimate['width']),
            ),
            'points' => array_merge([
                'پایه محاسبه: '.Format::number($estimate['base'], 1).' متر برای دسته «'.$garmentType->categoryLabel().'».',
            ], $estimate['extras']),
            'reasons' => [[
                'label' => 'روش برآورد',
                'text' => 'متراژ پایه هر دسته لباس بر پایه عرض ۱۴۰ سانتی‌متر حساب شده و سپس با عرض پارچه شما و ویژگی‌های آن (جهت‌داری، تطبیق طرح، آب‌رفت) تصحیح می‌شود. برای عدد دقیق، از چیدمان دوبعدی پروژه استفاده کنید.',
            ]],
        ];
    }

    protected function answerOverview(?Fabric $fabric, ?GarmentType $garmentType): array
    {
        if ($fabric === null && $garmentType === null) {
            return [
                'headline' => 'یک پارچه یا یک مدل لباس انتخاب کنید تا بتوانم پاسخ دقیق بدهم.',
                'points' => static::EXAMPLES,
            ];
        }

        if ($fabric === null) {
            $suggestions = $this->compatibility->suggestFabricTypes($garmentType, 5);

            return [
                'headline' => 'برای «'.$garmentType->name_fa.'» این پارچه‌ها بیشترین تناسب را دارند.',
                'points' => $suggestions
                    ->map(fn (array $row) => $row['fabric_type']->name_fa.' — نمره '.Format::number($row['score']['overall']).' از ۱۰۰')
                    ->all(),
                'reasons' => [['label' => 'معیار سنجش', 'text' => 'مقایسه شناسنامه هر پارچه با ترجیح این مدل لباس.']],
            ];
        }

        $results = $this->engine->evaluateScopes(RuleEngine::PROJECT_SCOPES, $this->engine->factsFor($fabric, $garmentType));

        return [
            'headline' => $this->fabricSummary($fabric),
            'points' => array_slice($this->engine->messages($results), 0, 8),
            'reasons' => $this->ruleReasons($results),
        ];
    }

    /** برآورد متراژ پارچه. */
    public function estimate(?Fabric $fabric, GarmentType $garmentType): array
    {
        $base = match ($garmentType->category) {
            'top' => 1.5,
            'bottom' => 1.4,
            'one_piece' => 2.5,
            'outer' => 2.6,
            'formal' => 3.0,
            default => 2.0,
        };

        $width = $fabric?->effectiveWidth() ?? 140.0;
        $meters = $base * (140 / max(60, $width));
        $extras = [];

        if ($fabric?->isDirectional()) {
            $meters *= 1.15;
            $extras[] = 'پارچه جهت‌دار یا خواب‌دار است؛ ۱۵ درصد بیشتر حساب شد.';
        }

        if ($fabric?->needsPatternMatching()) {
            $meters *= 1.2;
            $extras[] = 'طرح راه‌راه یا چهارخانه باید در درزها تطبیق شود؛ ۲۰ درصد بیشتر حساب شد.';
        }

        if ($fabric && (float) $fabric->profile()->get('shrinkage') > 4) {
            $meters *= 1.1;
            $extras[] = 'آب‌رفت پارچه بالای ۴ درصد است؛ ۱۰ درصد بیشتر حساب شد.';
        }

        return [
            'base' => round($base, 1),
            'width' => $width,
            'meters' => round($meters + 0.04, 1),
            'extras' => $extras,
        ];
    }

    /** استدلال بر پایه قواعدی که برقرار شده‌اند. */
    protected function ruleReasons(array $results, ?array $types = null): array
    {
        $reasons = [];

        foreach ($results as $result) {
            if ($types !== null) {
                $matched = false;

                foreach ($result['actions'] as $action) {
                    if (in_array($action['type'], $types, true)) {
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    continue;
                }
            }

            $reasons[] = [
                'label' => 'قاعده «'.$result['name'].'»',
                'text' => $result['message'],
            ];
        }

        return $reasons;
    }

    /** اگر پروژه شبیه‌سازی دارد، نتیجه تست تناسب هم به استدلال اضافه می‌شود. */
    protected function simulationReasons(?Project $project): array
    {
        $simulation = $project?->latestSimulation;

        if ($simulation === null) {
            return [];
        }

        $text = 'حالت «'.$simulation->poseLabel().'»';

        if ($simulation->fit_score !== null) {
            $text .= ' با نمره تناسب '.Format::number((float) $simulation->fit_score).' از ۱۰۰';
        }

        $warnings = collect($simulation->warnings ?? [])
            ->map(fn ($warning) => is_array($warning) ? ($warning['message'] ?? '') : (string) $warning)
            ->filter()
            ->take(3)
            ->implode(' • ');

        if ($warnings !== '') {
            $text .= '؛ هشدارها: '.$warnings;
        }

        return [['label' => 'آخرین شبیه‌سازی پروژه', 'text' => $text.'.']];
    }

    protected function needsFabric(): array
    {
        return [
            'headline' => 'برای این پرسش باید یک پارچه انتخاب کنید.',
            'points' => ['پارچه را از فهرست بالا انتخاب کنید یا اول آن را در «پارچه‌های من» ثبت کنید.'],
        ];
    }
}
