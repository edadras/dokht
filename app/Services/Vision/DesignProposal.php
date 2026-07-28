<?php

namespace App\Services\Vision;

use App\Models\GarmentType;
use App\Models\PatternTemplate;
use App\Services\Pattern\GeneratorRegistry;
use App\Support\Jalali;

/**
 * تبدیل نتیجه تشخیص به یک «پیشنهاد» قابل پذیرش.
 *
 * پیشنهاد شامل نوع لباس، الگوی پایه مناسب، پارامترهای پیشنهادی تولیدکننده، سه
 * گزینه جایگزین و رونمای سیلوئت است. هیچ هندسه‌ای اینجا ساخته نمی‌شود؛ ساخت
 * الگو همچنان کار PatternBuilder و تولیدکننده‌های واقعی است تا الگو ویرایش‌پذیر،
 * سایزبندی‌پذیر و خروجی‌گرفتنی بماند.
 */
class DesignProposal
{
    /**
     * تولیدکننده‌های مناسب هر نوع لباس، به ترتیب اولویت.
     *
     * @var array<string, array<int, string>>
     */
    public const GENERATORS = [
        'shirt' => ['shirt_classic', 'bodice_block'],
        'blouse' => ['bodice_block', 'shirt_classic'],
        'shomiz' => ['shirt_classic', 'bodice_block'],
        'top' => ['bodice_block', 'tshirt'],
        'tshirt' => ['tshirt', 'bodice_block'],
        'blazer' => ['blazer', 'bodice_block'],
        'cardigan' => ['blazer', 'bodice_block'],
        'manteau' => ['blazer', 'shirt_classic'],
        'coat' => ['blazer'],
        'skirt_straight' => ['skirt_pencil', 'skirt_a_line'],
        'skirt_gored' => ['skirt_a_line', 'skirt_pencil'],
        'skirt_circle' => ['skirt_a_line'],
        'pants' => ['pants_straight', 'pants_wide_leg'],
        'shorts' => ['pants_straight', 'pants_wide_leg'],
        'jumpsuit' => ['pants_wide_leg', 'pants_straight'],
        'dress' => ['dress'],
        'evening_dress' => ['dress'],
        'cocktail_dress' => ['dress'],
        'bridal_dress' => ['dress'],
    ];

    /** بلندی تخمینی از کمر به پایین (سانتی‌متر) برای هر رده بلندی. */
    protected const SKIRT_LENGTHS = [
        'crop' => 40, 'hip' => 44, 'thigh' => 48, 'knee' => 58, 'midi' => 75, 'maxi' => 100,
    ];

    /** بلندی تنه از کمر به پایین (سانتی‌متر) برای بالاتنه‌ها. */
    protected const BODY_LENGTHS = [
        'crop' => 2, 'hip' => 16, 'thigh' => 26, 'knee' => 40, 'midi' => 45, 'maxi' => 55,
    ];

    /** بلندی آستین (سانتی‌متر). */
    protected const SLEEVE_LENGTHS = [
        'sleeveless' => 10, 'cap' => 12, 'short' => 22, 'long' => 56,
    ];

    /** گودی اضافه یقه جلو برای هر شکل یقه. */
    protected const NECK_DEPTH = [
        'high' => 0.0, 'round' => 1.5, 'boat' => 0.0, 'square' => 3.0, 'v' => 7.0,
    ];

    /** اضافه عرض یقه برای هر شکل یقه. */
    protected const NECK_WIDTH = [
        'high' => 0.0, 'round' => 1.0, 'boat' => 4.0, 'square' => 2.5, 'v' => 1.0,
    ];

    public function __construct(protected SilhouetteOverlay $overlay = new SilhouetteOverlay) {}

    /**
     * ساخت پیشنهاد کامل.
     *
     * @param  array<string, mixed>  $classification
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function build(
        string $source,
        Silhouette $mask,
        SilhouetteFeatures $features,
        array $classification,
        array $options = [],
    ): array {
        $code = $classification['garment']['code'];
        $family = $classification['garment']['family'];
        $types = $this->garmentTypes();
        $type = $types[$code] ?? null;

        $template = $this->template($code, $type, $options['workshop_id'] ?? null);
        [$params, $paramReasons] = $template === null
            ? [[], []]
            : $this->params($template, $features, $classification);

        return [
            'source' => $source,
            'image_url' => $options['image_url'] ?? null,
            'image_path' => $options['image_path'] ?? null,
            'garment' => [
                'code' => $code,
                'id' => $type?->id,
                'name' => $type?->name_fa ?? GarmentClassifier::name($code),
                'family' => $family,
                'confidence' => $classification['confidence'],
                'score' => $classification['garment']['score'],
            ],
            'alternatives' => array_map(function (array $candidate) use ($types) {
                $type = $types[$candidate['code']] ?? null;

                return [
                    'code' => $candidate['code'],
                    'id' => $type?->id,
                    'name' => $type?->name_fa ?? GarmentClassifier::name($candidate['code']),
                    'confidence' => $candidate['confidence'],
                    'score' => $candidate['score'],
                    'reason' => $candidate['reason'],
                ];
            }, $classification['alternatives']),
            'attributes' => [
                'silhouette' => $classification['silhouette'],
                'length' => $classification['length'],
                'sleeve' => $classification['sleeve'],
                'neckline' => $classification['neckline'],
            ],
            'template' => $template === null ? null : [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name_fa,
                'generator' => $template->generator,
                'description' => $template->description,
                'reason' => $this->templateReason($template, $code, $type),
                'schema' => $template->params_schema ?? [],
            ],
            'params' => $params,
            'param_reasons' => $paramReasons,
            'evidence' => $classification['evidence'],
            'warnings' => $classification['warnings'],
            'confidence' => $classification['confidence'],
            'quality' => $classification['quality'],
            'distinctiveness' => $classification['distinctiveness'],
            'overlay_svg' => $this->overlay->render(
                $mask,
                $features,
                ! in_array($family, ['bottom'], true),
            ),
            'features' => $features->toArray(),
            'garment_options' => $types->map(fn (GarmentType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name_fa,
            ])->values()->all(),
        ];
    }

    /** فهرست نوع‌های لباس با کلید code. */
    protected function garmentTypes()
    {
        return GarmentType::query()->active()->orderBy('sort')->get()->keyBy('code');
    }

    /** انتخاب بهترین الگوی پایه موجود برای این نوع لباس. */
    protected function template(string $code, ?GarmentType $type, ?int $workshopId): ?PatternTemplate
    {
        $preferred = self::GENERATORS[$code] ?? ['bodice_block'];

        $templates = PatternTemplate::query()
            ->availableTo($workshopId)
            ->whereIn('generator', $preferred)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($templates->isEmpty()) {
            return PatternTemplate::query()->availableTo($workshopId)->orderBy('sort')->first();
        }

        return $templates->sortBy(fn (PatternTemplate $template) => [
            $template->garment_type_id === $type?->id ? 0 : 1,
            array_search($template->generator, $preferred, true),
            $template->sort,
        ])->first();
    }

    protected function templateReason(PatternTemplate $template, string $code, ?GarmentType $type): string
    {
        $name = $type?->name_fa ?? GarmentClassifier::name($code);

        return 'برای «'.$name.'» تولیدکننده «'.GeneratorRegistry::make($template->generator)->label()
            .'» مناسب است، و «'.$template->name_fa.'» نزدیک‌ترین الگوی پایه موجود در کتابخانه شماست.';
    }

    /**
     * پارامترهای پیشنهادی تولیدکننده، بر پایه چیزی که واقعاً اندازه گرفته شد.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    protected function params(PatternTemplate $template, SilhouetteFeatures $features, array $classification): array
    {
        $length = $classification['length']['value'];
        $sleeve = $classification['sleeve']['value'];
        $neckline = $classification['neckline']['value'] ?? 'round';
        $flare = max(0.0, ($features->hemRatio - 1) * 26);

        $suggested = [];
        $reasons = [];

        $add = function (string $key, float|bool|int $value, string $reason) use (&$suggested, &$reasons) {
            $suggested[$key] = $value;
            $reasons[$key] = $reason;
        };

        switch ($template->generator) {
            case 'skirt_a_line':
                $add('length', self::SKIRT_LENGTHS[$length] ?? 60, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $add('flare', round($flare, 1), 'لبه پایین '.$this->n($features->hemRatio).' برابر کمر است، پس هر پنل حدود این مقدار باز می‌شود.');
                break;

            case 'skirt_pencil':
                $add('length', self::SKIRT_LENGTHS[$length] ?? 58, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $add('taper', round(max(0.0, (1 - $features->hemRatio) * 20), 1), 'لبه پایین نسبت به کمر '.$this->n($features->hemRatio).' برابر است.');
                break;

            case 'pants_straight':
            case 'pants_wide_leg':
                $add('length_extra', $length === 'crop' || $length === 'thigh' ? -42 : 0, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $add('hem_ease', round(max(0.0, min(45.0, ($features->hemRatio - 0.9) * 45)), 0), 'پهنای دم پا نسبت به کمر '.$this->n($features->hemRatio).' برابر اندازه‌گیری شد.');
                $add('knee_ease', round(max(0.0, min(35.0, ($features->hipWidth / max(0.01, $features->upperWidth)) * 12)), 0), 'پهنای میانه پاچه از نیم‌رخ پهنا خوانده شد.');
                break;

            case 'dress':
                $add('skirt_length', self::SKIRT_LENGTHS[$length] ?? 62, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $add('hem_flare', round(min(40.0, $flare), 1), 'باز شدن لبه پایین '.$this->n($features->hemRatio).' برابر سرشانه است.');
                $add('sleeve', $sleeve !== 'sleeveless', 'آستین: '.$classification['sleeve']['label'].'.');
                $add('sleeve_length', self::SLEEVE_LENGTHS[$sleeve] ?? 30, 'از رده آستین «'.$classification['sleeve']['label'].'» تخمین زده شد.');
                $this->neckParams($add, $neckline, $classification);
                break;

            case 'shirt_classic':
                $add('body_length', self::BODY_LENGTHS[$length] ?? 22, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $add('fitted', $features->waistPinch >= 0.08, 'فرورفتگی کمر '.$this->percent($features->waistPinch).' اندازه‌گیری شد.');
                $this->neckParams($add, $neckline, $classification);
                break;

            case 'blazer':
                $add('body_length', self::BODY_LENGTHS[$length] ?? 22, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $this->neckParams($add, $neckline, $classification);
                break;

            case 'tshirt':
                $add('body_length', self::BODY_LENGTHS[$length] ?? 16, 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $add('sleeve_length', self::SLEEVE_LENGTHS[$sleeve] ?? 22, 'از رده آستین «'.$classification['sleeve']['label'].'» تخمین زده شد.');
                $this->neckParams($add, $neckline, $classification);
                break;

            case 'bodice_block':
                $add('bodice_length_extra', round(min(20.0, max(-6.0, (self::BODY_LENGTHS[$length] ?? 0) - 6)), 1), 'رده بلندی «'.$classification['length']['label'].'» تخمین زده شد.');
                $this->neckParams($add, $neckline, $classification);
                break;
        }

        return [$this->clamp($template, $suggested), $reasons];
    }

    /** پارامترهای یقه از روی شکل تشخیص‌داده‌شده. */
    protected function neckParams(callable $add, string $neckline, array $classification): void
    {
        $label = $classification['neckline']['label'] ?? '';

        $add('front_neck_depth_extra', self::NECK_DEPTH[$neckline] ?? 1.5, 'شکل یقه «'.$label.'» تشخیص داده شد.');
        $add('neck_width_extra', self::NECK_WIDTH[$neckline] ?? 1.0, 'شکل یقه «'.$label.'» تشخیص داده شد.');
    }

    /**
     * نگه‌داشتن پارامترها در محدوده مجاز خود تولیدکننده.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function clamp(PatternTemplate $template, array $params): array
    {
        $schema = $template->params_schema ?: GeneratorRegistry::make($template->generator)->paramsSchema();
        $clean = [];

        foreach ($params as $key => $value) {
            $rule = $schema[$key] ?? null;

            if ($rule === null) {
                continue;
            }

            if (($rule['type'] ?? null) === 'toggle') {
                $clean[$key] = (bool) $value;

                continue;
            }

            $clean[$key] = round(max((float) ($rule['min'] ?? -1e6), min((float) ($rule['max'] ?? 1e6), (float) $value)), 2);
        }

        return $clean;
    }

    protected function n(float $value): string
    {
        return Jalali::digits(rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0');
    }

    protected function percent(float $value): string
    {
        return Jalali::digits(number_format($value * 100, 0)).'٪';
    }
}
