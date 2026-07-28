<?php

namespace App\Services\Rules;

use App\Models\DesignRule;
use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\Project;
use App\Support\FabricProfile;
use App\Support\Jalali;
use App\Support\WorkshopContext;
use Illuminate\Support\Collection;

/**
 * موتور قواعد طراحی: دانش خیاطی به‌صورت داده، نه کد.
 *
 * ## قالب شرط‌ها (ستون design_rules.conditions)
 *
 * ```json
 * {
 *   "all": [{"field": "fabric.stretch_weft", "op": ">",  "value": 20}],
 *   "any": [{"field": "garment.category",    "op": "in", "value": ["top", "one_piece"]}],
 *   "none":[{"field": "fabric.family",       "op": "=",  "value": "lace"}]
 * }
 * ```
 *
 * - `all` باید همه برقرار باشند، `any` حداقل یکی، `none` هیچ‌کدام.
 * - اگر به‌جای شیء یک فهرست ساده بدهید، مثل `[{...}, {...}]`، همان `all` است.
 * - شرط خالی یعنی قاعده همیشه برقرار است.
 * - عملگرها: `>` `>=` `<` `<=` `=` `!=` `in` `contains` `between`
 *   (`between` مقدار را به شکل `[min, max]` می‌گیرد و `contains` هم روی رشته و هم روی فهرست کار می‌کند).
 * - `field` یک مسیر نقطه‌دار از واقعیت‌ها است: `fabric.transparency`، `fabric.drape`،
 *   `garment.category`، `pattern.size`، `fit.bust_ease`. فیلد ناشناخته هیچ‌گاه خطا
 *   نمی‌دهد و شرط را ناموفق می‌کند.
 *
 * ## قالب کنش‌ها (ستون design_rules.actions)
 *
 * ```json
 * [
 *   {"type": "suggest_lining"},
 *   {"type": "set_ease", "key": "bust", "value": -2},
 *   {"type": "seam_allowance", "edge": "side", "value": 1.5},
 *   {"type": "note", "text": "با کاغذ زیر پارچه برش بزنید"}
 * ]
 * ```
 *
 * هر کنش می‌تواند کلید `message` داشته باشد؛ اگر نداشت، متن فارسی خودکار از نوع
 * کنش ساخته می‌شود و در نهایت نام قاعده به‌کار می‌رود.
 */
class RuleEngine
{
    /** دامنه‌هایی که برای یک پروژه کامل بررسی می‌شوند. */
    public const PROJECT_SCOPES = ['fabric', 'pattern', 'cutting', 'production'];

    public const OPERATORS = [
        '>' => 'بیشتر از',
        '>=' => 'بیشتر یا مساوی',
        '<' => 'کمتر از',
        '<=' => 'کمتر یا مساوی',
        '=' => 'برابر با',
        '!=' => 'نابرابر با',
        'in' => 'یکی از',
        'contains' => 'شامل',
        'between' => 'بین',
    ];

    /**
     * فهرست گزیده واقعیت‌ها برای فرم ساخت قاعده.
     *
     * فقط چیزهایی که یک خیاط می‌فهمد، با برچسب فارسی و نوع ورودی مناسب.
     */
    public const FACT_FIELDS = [
        'fabric.drape' => ['label' => 'لختی پارچه', 'type' => 'ratio', 'group' => 'پارچه'],
        'fabric.stiffness' => ['label' => 'سفتی پارچه', 'type' => 'ratio', 'group' => 'پارچه'],
        'fabric.weight_gsm' => ['label' => 'وزن پارچه (گرم بر متر مربع)', 'type' => 'number', 'group' => 'پارچه'],
        'fabric.thickness_mm' => ['label' => 'ضخامت پارچه (میلی‌متر)', 'type' => 'number', 'group' => 'پارچه'],
        'fabric.max_stretch' => ['label' => 'بیشترین کشسانی (درصد)', 'type' => 'percent', 'group' => 'پارچه'],
        'fabric.stretch_weft' => ['label' => 'کشسانی عرض (درصد)', 'type' => 'percent', 'group' => 'پارچه'],
        'fabric.stretch_warp' => ['label' => 'کشسانی طول (درصد)', 'type' => 'percent', 'group' => 'پارچه'],
        'fabric.stretch_bias' => ['label' => 'کشسانی اریب (درصد)', 'type' => 'percent', 'group' => 'پارچه'],
        'fabric.recovery' => ['label' => 'برگشت‌پذیری (درصد)', 'type' => 'percent', 'group' => 'پارچه'],
        'fabric.transparency' => ['label' => 'شفافیت پارچه', 'type' => 'ratio', 'group' => 'پارچه'],
        'fabric.sheen' => ['label' => 'براقی پارچه', 'type' => 'ratio', 'group' => 'پارچه'],
        'fabric.shrinkage' => ['label' => 'آب‌رفت (درصد)', 'type' => 'percent', 'group' => 'پارچه'],
        'fabric.fraying' => ['label' => 'ریش‌شدن لبه', 'type' => 'ratio', 'group' => 'برش و دوخت'],
        'fabric.curling' => ['label' => 'لوله‌شدن لبه', 'type' => 'ratio', 'group' => 'برش و دوخت'],
        'fabric.slippage' => ['label' => 'سرشدن زیر قیچی', 'type' => 'ratio', 'group' => 'برش و دوخت'],
        'fabric.distortion' => ['label' => 'تغییر شکل هنگام برش', 'type' => 'ratio', 'group' => 'برش و دوخت'],
        'fabric.heat_sensitivity' => ['label' => 'حساسیت به حرارت', 'type' => 'ratio', 'group' => 'برش و دوخت'],
        'fabric.steam_sensitivity' => ['label' => 'حساسیت به بخار', 'type' => 'ratio', 'group' => 'برش و دوخت'],
        'fabric.has_nap' => ['label' => 'پارچه خواب‌دار است', 'type' => 'bool', 'group' => 'برش و دوخت'],
        'fabric.directional' => ['label' => 'طرح پارچه جهت‌دار است', 'type' => 'bool', 'group' => 'برش و دوخت'],
        'fabric.family' => ['label' => 'خانواده پارچه', 'type' => 'option', 'options' => FabricType::FAMILIES, 'group' => 'پارچه'],
        'fabric.category' => ['label' => 'دسته الیاف', 'type' => 'option', 'options' => FabricType::CATEGORIES, 'group' => 'پارچه'],
        'fabric.surface_pattern' => ['label' => 'طرح روی پارچه', 'type' => 'option', 'options' => Fabric::SURFACE_PATTERNS, 'group' => 'پارچه'],
        'fabric.width_cm' => ['label' => 'عرض پارچه (سانتی‌متر)', 'type' => 'number', 'group' => 'پارچه'],
        'fabric.pressing' => ['label' => 'نیاز به اتو', 'type' => 'option', 'options' => ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'], 'group' => 'برش و دوخت'],
        'garment.category' => ['label' => 'دسته لباس', 'type' => 'option', 'options' => GarmentType::CATEGORIES, 'group' => 'لباس'],
        'garment.code' => ['label' => 'کد مدل لباس', 'type' => 'text', 'group' => 'لباس'],
        'garment.silhouette' => ['label' => 'فرم کلی لباس', 'type' => 'text', 'group' => 'لباس'],
        'pattern.size' => ['label' => 'سایز الگو', 'type' => 'text', 'group' => 'الگو'],
        'pattern.cut' => ['label' => 'جهت برش الگو', 'type' => 'option', 'options' => ['straight' => 'راستای پارچه', 'bias' => 'اریب', 'cross' => 'عرضی'], 'group' => 'الگو'],
        'fit.bust_ease' => ['label' => 'آزادی دور سینه (سانتی‌متر)', 'type' => 'number', 'group' => 'الگو'],
        'fit.waist_ease' => ['label' => 'آزادی دور کمر (سانتی‌متر)', 'type' => 'number', 'group' => 'الگو'],
        'fit.hip_ease' => ['label' => 'آزادی دور باسن (سانتی‌متر)', 'type' => 'number', 'group' => 'الگو'],
    ];

    /**
     * انواع کنش با برچسب فارسی و ورودی‌های لازم برای فرم ساخت قاعده.
     *
     * inputs: text | number | edge | ease_key | choice
     */
    public const ACTION_TYPES = [
        'note' => ['label' => 'نمایش یک یادداشت', 'inputs' => ['text' => ['label' => 'متن یادداشت', 'type' => 'text']]],
        'suggest_lining' => ['label' => 'پیشنهاد آستر', 'inputs' => []],
        'suggest_underlining' => ['label' => 'پیشنهاد آستر چسبیده (زیرلایه)', 'inputs' => []],
        'suggest_interfacing' => ['label' => 'پیشنهاد لایی', 'inputs' => ['text' => ['label' => 'نوع لایی', 'type' => 'text']]],
        'set_ease' => ['label' => 'تغییر آزادی لباس', 'inputs' => [
            'key' => ['label' => 'کدام اندازه', 'type' => 'choice', 'options' => ['bust' => 'دور سینه', 'waist' => 'دور کمر', 'hip' => 'دور باسن', 'bicep' => 'دور بازو']],
            'value' => ['label' => 'مقدار (سانتی‌متر، منفی یعنی تنگ‌تر)', 'type' => 'number'],
        ]],
        'seam_allowance' => ['label' => 'تعیین جای دوخت', 'inputs' => [
            'edge' => ['label' => 'کدام درز', 'type' => 'choice', 'options' => ['all' => 'همه درزها', 'side' => 'درز پهلو', 'shoulder' => 'درز سرشانه', 'armhole' => 'حلقه آستین', 'neckline' => 'یقه', 'hem' => 'لبه پایین']],
            'value' => ['label' => 'اندازه (سانتی‌متر)', 'type' => 'number'],
        ]],
        'hem_allowance' => ['label' => 'تعیین لب برگردان', 'inputs' => ['value' => ['label' => 'اندازه (سانتی‌متر)', 'type' => 'number']]],
        'seam_finish' => ['label' => 'روش تمیزکاری درز', 'inputs' => ['value' => ['label' => 'روش', 'type' => 'choice', 'options' => ['overlock' => 'سردوز', 'french' => 'درز فرانسوی', 'bound' => 'نواردوزی', 'zigzag' => 'زیگزاگ', 'flat_fell' => 'درز دوبل (فلت‌فل)']]]],
        'needle' => ['label' => 'سوزن پیشنهادی', 'inputs' => ['value' => ['label' => 'سوزن', 'type' => 'text']]],
        'stitch' => ['label' => 'کوک پیشنهادی', 'inputs' => ['value' => ['label' => 'کوک', 'type' => 'text']]],
        'iron_temp' => ['label' => 'دمای اتو', 'inputs' => ['value' => ['label' => 'دما', 'type' => 'choice', 'options' => ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد']]]],
        'prewash' => ['label' => 'شست‌وشوی پارچه پیش از برش', 'inputs' => []],
        'one_direction_cutting' => ['label' => 'برش همه قطعات در یک جهت', 'inputs' => []],
        'match_pattern' => ['label' => 'تطبیق طرح در درزها', 'inputs' => ['edge' => ['label' => 'کدام درز', 'type' => 'choice', 'options' => ['side' => 'درز پهلو', 'center' => 'خط مرکزی', 'all' => 'همه درزها']]]],
        'no_darts' => ['label' => 'بدون پنس', 'inputs' => []],
        'avoid_bias' => ['label' => 'پرهیز از برش اریب', 'inputs' => []],
        'unsuitable' => ['label' => 'اعلام نامناسب بودن پارچه', 'inputs' => ['text' => ['label' => 'دلیل', 'type' => 'text']]],
        'extra_fabric' => ['label' => 'پارچه بیشتر بخرید', 'inputs' => ['value' => ['label' => 'درصد اضافه', 'type' => 'number']]],
    ];

    /** @var array<string, Collection<int, DesignRule>> */
    protected array $cache = [];

    public function __construct(protected WorkshopContext $context) {}

    /**
     * ارزیابی قواعد یک دامنه روی واقعیت‌های داده‌شده.
     *
     * @return array<int, array{code:string,name:string,scope:string,severity:string,priority:int,is_global:bool,message:string,actions:array}>
     */
    public function evaluate(string $scope, array|RuleContext $facts): array
    {
        $context = $facts instanceof RuleContext ? $facts : RuleContext::make($facts);

        $results = [];

        foreach ($this->rules($scope) as $rule) {
            if (! $this->matches($rule->conditions, $context)) {
                continue;
            }

            $results[] = $this->result($rule);
        }

        return $results;
    }

    /** ارزیابی چند دامنه با هم؛ نتیجه یک فهرست تخت با کلید scope در هر ردیف است. */
    public function evaluateScopes(array $scopes, array|RuleContext $facts): array
    {
        $context = $facts instanceof RuleContext ? $facts : RuleContext::make($facts);

        $results = [];

        foreach ($scopes as $scope) {
            foreach ($this->evaluate($scope, $context) as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * همه قواعد مرتبط با یک پروژه (پارچه، الگو، برش و تولید).
     *
     * وقتی پروژه هنوز پارچه یا الگو ندارد، بی‌خطر کار می‌کند و فقط قواعدی که با
     * واقعیت‌های موجود برقرار می‌شوند برگردانده می‌شوند.
     */
    public function forProject(Project $project): array
    {
        $extra = [
            'project' => [
                'id' => $project->id,
                'status' => (string) $project->status,
                'step' => (int) $project->step,
                'size' => (string) $project->size,
                'has_lining' => $project->lining_fabric_id !== null,
            ],
        ];

        if ($silhouette = data_get($project->settings, 'silhouette')) {
            $extra['garment']['silhouette'] = (string) $silhouette;
        }

        $ease = $project->effectiveEase();

        foreach ($ease as $key => $value) {
            if (is_numeric($value)) {
                $extra['fit'][$key.'_ease'] = (float) $value;
            }
        }

        return $this->evaluateScopes(
            static::PROJECT_SCOPES,
            $this->factsFor($project->fabric, $project->garmentType, $project->pattern, $extra),
        );
    }

    /** سازنده واقعیت‌ها؛ آرایه تودرتو برمی‌گرداند. */
    public function factsFor(
        ?Fabric $fabric = null,
        ?GarmentType $garmentType = null,
        ?Pattern $pattern = null,
        array $extra = [],
    ): array {
        return RuleContext::build($fabric, $garmentType, $pattern, $extra);
    }

    /** واقعیت‌های یک نوع پارچه از بانک پایه (برای صفحه بانک پارچه). */
    public function factsForType(FabricType $type, ?GarmentType $garmentType = null, array $extra = []): array
    {
        return array_replace_recursive([
            'fabric' => RuleContext::fabricTypeFacts($type),
            'garment' => RuleContext::garmentFacts($garmentType),
        ], $extra);
    }

    /**
     * اعمال کنش‌های set_ease روی آزادی لباس.
     *
     * مقدار پیش‌فرض «افزودنی» است؛ اگر کنش mode=absolute داشته باشد جای مقدار را می‌گیرد.
     */
    public function applyEase(array $ease, array $results): array
    {
        foreach ($this->actionsOfType($results, 'set_ease') as $action) {
            $key = $action['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = (float) ($action['value'] ?? 0);

            $ease[$key] = ($action['mode'] ?? 'delta') === 'absolute'
                ? round($value, 2)
                : round((float) ($ease[$key] ?? 0) + $value, 2);
        }

        return $ease;
    }

    /**
     * جای دوخت‌های تحمیل‌شده توسط قواعد.
     *
     * @return array<string, float> کلید نام درز (all|side|shoulder|…|hem) و مقدار سانتی‌متر
     */
    public function seamAllowanceOverrides(array $results): array
    {
        $out = [];

        foreach ($this->actionsOfType($results, 'seam_allowance') as $action) {
            $edge = (string) ($action['edge'] ?? 'all');
            $out[$edge] = round((float) ($action['value'] ?? 1), 2);
        }

        foreach ($this->actionsOfType($results, 'hem_allowance') as $action) {
            $out['hem'] = round((float) ($action['value'] ?? 3), 2);
        }

        return $out;
    }

    /** همه کنش‌های یک نوع خاص در میان نتایج. */
    public function actionsOfType(array $results, string $type): array
    {
        $out = [];

        foreach ($results as $result) {
            foreach ($result['actions'] ?? [] as $action) {
                if (($action['type'] ?? null) === $type) {
                    $out[] = $action;
                }
            }
        }

        return $out;
    }

    /** آیا کنشی از این نوع در نتایج هست؟ */
    public function hasAction(array $results, string $type): bool
    {
        return $this->actionsOfType($results, $type) !== [];
    }

    /** پیام‌های قابل نمایش؛ در صورت نیاز فقط با شدت مشخص. */
    public function messages(array $results, ?string $severity = null): array
    {
        $out = [];

        foreach ($results as $result) {
            if ($severity !== null && ($result['severity'] ?? null) !== $severity) {
                continue;
            }

            foreach ($result['actions'] ?? [] as $action) {
                $out[] = $action['message'];
            }
        }

        return array_values(array_unique($out));
    }

    /** فقط هشدارها. */
    public function warnings(array $results): array
    {
        return $this->messages($results, 'warning');
    }

    /** خلاصه‌ای از پیشنهادهای دوخت برای کارت فنی. */
    public function sewingHints(array $results): array
    {
        return array_filter([
            'seam_finish' => $this->firstActionValue($results, 'seam_finish'),
            'needle' => $this->firstActionValue($results, 'needle'),
            'stitch' => $this->firstActionValue($results, 'stitch'),
            'iron_temp' => $this->firstActionValue($results, 'iron_temp'),
        ], fn ($v) => $v !== null);
    }

    public function firstActionValue(array $results, string $type, string $key = 'value'): mixed
    {
        foreach ($this->actionsOfType($results, $type) as $action) {
            if (isset($action[$key])) {
                return $action[$key];
            }
        }

        return null;
    }

    /** قواعد فعال یک دامنه برای کارگاه فعال، مرتب بر اساس اولویت. */
    public function rules(string $scope): Collection
    {
        return $this->cache[$scope] ??= DesignRule::query()
            ->active()
            ->availableTo($this->context->id())
            ->where('scope', $scope)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /** پاک کردن حافظه موقت قواعد (پس از ویرایش قاعده). */
    public function flush(): void
    {
        $this->cache = [];
    }

    protected function result(DesignRule $rule): array
    {
        $actions = $this->normalizeActions($rule);

        return [
            'id' => $rule->id,
            'code' => (string) $rule->code,
            'name' => (string) $rule->name,
            'scope' => (string) $rule->scope,
            'severity' => (string) $rule->severity,
            'priority' => (int) $rule->priority,
            'is_global' => $rule->workshop_id === null,
            'message' => $actions[0]['message'] ?? (string) $rule->name,
            'actions' => $actions,
        ];
    }

    /** هر کنش دقیقاً یک type و یک message دارد. */
    protected function normalizeActions(DesignRule $rule): array
    {
        $out = [];

        foreach ($rule->actions ?? [] as $action) {
            if (! is_array($action) || ! isset($action['type'])) {
                continue;
            }

            $action['type'] = (string) $action['type'];
            $action['message'] = (string) ($action['message'] ?? $action['text'] ?? $this->describe($action) ?? $rule->name);

            $out[] = $action;
        }

        if ($out === []) {
            $out[] = ['type' => 'note', 'message' => (string) $rule->name];
        }

        return $out;
    }

    /** متن فارسی خودکار برای کنشی که پیام ندارد. */
    protected function describe(array $action): ?string
    {
        $value = $action['value'] ?? null;
        $edge = static::ACTION_TYPES['seam_allowance']['inputs']['edge']['options'] ?? [];
        $finishes = static::ACTION_TYPES['seam_finish']['inputs']['value']['options'] ?? [];
        $easeKeys = static::ACTION_TYPES['set_ease']['inputs']['key']['options'] ?? [];
        $temps = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];

        return match ($action['type']) {
            'suggest_lining' => 'برای این پارچه آستر لازم است.',
            'suggest_underlining' => 'یک زیرلایه (آستر چسبیده به پارچه) بگذارید.',
            'suggest_interfacing' => 'از لایی مناسب استفاده کنید.',
            'set_ease' => sprintf(
                'آزادی %s را %s سانتی‌متر %s کنید.',
                $easeKeys[$action['key'] ?? ''] ?? ($action['key'] ?? ''),
                Jalali::digits(abs((float) $value)),
                ((float) $value) < 0 ? 'کم' : 'زیاد',
            ),
            'seam_allowance' => sprintf(
                'جای دوخت %s را %s سانتی‌متر بگیرید.',
                $edge[$action['edge'] ?? 'all'] ?? 'همه درزها',
                Jalali::digits((float) $value),
            ),
            'hem_allowance' => 'لب برگردان را '.Jalali::digits((float) $value).' سانتی‌متر بگیرید.',
            'seam_finish' => 'درزها را با روش «'.($finishes[$value] ?? $value).'» تمیز کنید.',
            'needle' => 'سوزن پیشنهادی: '.$value,
            'stitch' => 'کوک پیشنهادی: '.$value,
            'iron_temp' => 'دمای اتو را '.($temps[$value] ?? $value).' بگذارید.',
            'prewash' => 'پارچه را پیش از برش بشویید و اتو کنید.',
            'one_direction_cutting' => 'همه قطعات را در یک جهت بچینید و ببرید.',
            'match_pattern' => 'طرح پارچه را در درزها روی هم بیندازید.',
            'no_darts' => 'برای این پارچه پنس لازم نیست.',
            'avoid_bias' => 'این پارچه را اریب نبرید.',
            'unsuitable' => 'این پارچه برای این مدل مناسب نیست.',
            'extra_fabric' => Jalali::digits((float) $value).'٪ پارچه بیشتر بخرید.',
            default => null,
        };
    }

    /** آیا شرط‌های قاعده با واقعیت‌ها می‌خوانند؟ */
    protected function matches(mixed $conditions, RuleContext $context): bool
    {
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        if (array_is_list($conditions)) {
            $conditions = ['all' => $conditions];
        }

        $all = $conditions['all'] ?? [];
        $any = $conditions['any'] ?? [];
        $none = $conditions['none'] ?? [];

        if (is_array($all)) {
            foreach ($all as $condition) {
                if (! $this->test($condition, $context)) {
                    return false;
                }
            }
        }

        if (is_array($any) && $any !== []) {
            $matched = false;

            foreach ($any as $condition) {
                if ($this->test($condition, $context)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        if (is_array($none)) {
            foreach ($none as $condition) {
                if ($this->test($condition, $context)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** یک شرط تکی؛ هرگز استثنا نمی‌دهد. */
    protected function test(mixed $condition, RuleContext $context): bool
    {
        if (! is_array($condition)) {
            return false;
        }

        $field = $condition['field'] ?? null;

        if (! is_string($field) || $field === '' || ! $context->has($field)) {
            return false;
        }

        try {
            return $this->compare($context->get($field), (string) ($condition['op'] ?? '='), $condition['value'] ?? null);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function compare(mixed $actual, string $op, mixed $expected): bool
    {
        return match ($op) {
            '>' => $this->number($actual) > $this->number($expected),
            '>=' => $this->number($actual) >= $this->number($expected),
            '<' => $this->number($actual) < $this->number($expected),
            '<=' => $this->number($actual) <= $this->number($expected),
            '=', '==' => $this->equals($actual, $expected),
            '!=', '<>' => ! $this->equals($actual, $expected),
            'in' => $this->inList($actual, $expected),
            'contains' => $this->contains($actual, $expected),
            'between' => is_array($expected)
                && count($expected) >= 2
                && $this->number($actual) >= $this->number($expected[0])
                && $this->number($actual) <= $this->number($expected[1]),
            default => false,
        };
    }

    protected function equals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            return $this->boolean($actual) === $this->boolean($expected);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return abs($this->number($actual) - $this->number($expected)) < 1e-9;
        }

        return (string) (is_array($actual) ? json_encode($actual) : $actual)
            === (string) (is_array($expected) ? json_encode($expected) : $expected);
    }

    protected function inList(mixed $actual, mixed $expected): bool
    {
        $list = is_array($expected) ? $expected : preg_split('/\s*,\s*/', (string) $expected, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($list ?: [] as $candidate) {
            if ($this->equals($actual, $candidate)) {
                return true;
            }
        }

        return false;
    }

    protected function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ($this->equals($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        return $expected !== null && $expected !== ''
            && str_contains((string) $actual, (string) $expected);
    }

    protected function number(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    protected function boolean(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }

    /** برچسب فارسی یک مسیر واقعیت (برای نمایش قاعده به کاربر). */
    public static function fieldLabel(string $field): string
    {
        return static::FACT_FIELDS[$field]['label'] ?? $field;
    }

    public static function actionLabel(string $type): string
    {
        return static::ACTION_TYPES[$type]['label'] ?? $type;
    }

    /** واقعیت‌های گزیده، گروه‌بندی‌شده برای فرم. */
    public static function factGroups(): array
    {
        $groups = [];

        foreach (static::FACT_FIELDS as $field => $def) {
            $groups[$def['group']][$field] = $def['label'];
        }

        return $groups;
    }

    /** کلیدهای شناخته‌شده شناسنامه پارچه (برای مستندسازی و اعتبارسنجی). */
    public static function profileKeys(): array
    {
        return array_keys(FabricProfile::SCHEMA);
    }
}
