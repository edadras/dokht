<?php

namespace App\Services\Pattern;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Support\Format;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ترکیب مدل‌ها: ساخت یک لباس از بلوک‌های جدا.
 *
 * خیاط چهار چیز انتخاب می‌کند — بالاتنه، آستین، پایین‌تنه (دامن یا شلوار) و یقه —
 * و این کلاس آن‌ها را مثل یک الگوی یکپارچه به هم می‌دوزد:
 *
 *   ۱. بالاتنه در «خط کمر» خودش بریده می‌شود تا به پایین‌تنه برسد.
 *   ۲. دور کمر دو بخش اندازه‌گیری می‌شود (طول لبه منهای ساسون و پیلی) و اگر یکی
 *      بزرگ‌تر بود یا با چین‌دادن جبران می‌شود یا درز پهلو راست‌سازی (true) می‌شود
 *      تا دو لبه دقیقاً هم‌اندازه شوند. هر کاری که شد در «یادداشت‌ها» ثبت می‌شود.
 *   ۳. آستین با طول حلقه آستینِ همین ترکیب دوباره ساخته می‌شود (ارتفاع سرآستین
 *      تنظیم می‌شود)؛ اگر باز هم نرسید، اختلاف گزارش می‌شود.
 *   ۴. یقه به اندازه خط یقه همین ترکیب بریده می‌شود.
 *
 * خروجی compose() یک آرایه گزارش‌دار است:
 *   ['selection', 'pieces', 'notes', 'sewing_relations', 'metrics', 'measurements', ...]
 * که 'pieces' همان فهرست تخت قطعه‌ها با کلیدهای جدول pattern_pieces است؛ برای
 * گرفتن فقط قطعه‌ها composePieces() هست.
 */
class PatternComposer
{
    /** بلوک‌هایی که می‌توانند «بالاتنه» ترکیب باشند. */
    public const BODICE_BLOCKS = ['bodice_block', 'tshirt', 'shirt_classic', 'blazer'];

    /** بلوک آستین. */
    public const SLEEVE_BLOCKS = ['sleeve'];

    public const SKIRT_BLOCKS = ['skirt_a_line', 'skirt_pencil'];

    public const PANTS_BLOCKS = ['pants_straight', 'pants_wide_leg'];

    /** یقه‌ها ژنراتور جدا ندارند و همین‌جا درفت می‌شوند. */
    public const COLLAR_STYLES = ['band', 'stand', 'shirt', 'flat'];

    /** از مدل بالاتنه فقط این قطعه‌ها برداشته می‌شود؛ آستین و یقه‌اش جداگانه انتخاب می‌شود. */
    public const BODICE_PARTS = ['front_bodice', 'back_bodice', 'yoke', 'placket'];

    /** از مدل پایین‌تنه این قطعه‌ها برداشته می‌شود (کمربند فقط وقتی بالاتنه نباشد). */
    public const LOWER_PARTS = ['skirt_front', 'skirt_back', 'front_leg', 'back_leg'];

    /** بیشترین اختلاف کمری که با چین‌دادن جبران می‌شود؛ بیشتر از این درز پهلو راست می‌شود. */
    public const MAX_GATHER = 12.0;

    /** اختلاف کمتر از این مقدار (سانتی‌متر) اصلاً اختلاف حساب نمی‌شود. */
    public const WAIST_TOLERANCE = 0.4;

    /** اختلاف مجاز سرآستین با حلقه آستین. */
    public const CAP_TOLERANCE = 0.6;

    /** بیشترین گودتر شدن مجاز حلقه آستین برای جادادن سرآستین. */
    public const MAX_ARMHOLE_DROP = 6.0;

    public function __construct(
        protected SeamAllowanceService $seams = new SeamAllowanceService,
        protected PatternVersionService $versions = new PatternVersionService,
    ) {}

    /* ---------------------------------------------------------------------
     |  فهرست انتخاب‌ها و پارامترها (برای صفحه ترکیب)
     * ------------------------------------------------------------------- */

    /**
     * انتخاب‌های هر بخش با برچسب فارسی.
     *
     * @return array<string, array<string, array{label: string, hint: string, kind?: string}>>
     */
    public function options(): array
    {
        $label = fn (string $key) => GeneratorRegistry::make($key)->label();

        $bodice = [];

        foreach (static::BODICE_BLOCKS as $key) {
            $bodice[$key] = ['label' => $label($key), 'hint' => static::BLOCK_HINTS[$key] ?? ''];
        }

        $lower = ['none' => ['label' => 'بدون پایین‌تنه', 'hint' => 'فقط بالاتنه بریده می‌شود.', 'kind' => null]];

        foreach (static::SKIRT_BLOCKS as $key) {
            $lower[$key] = ['label' => $label($key), 'hint' => static::BLOCK_HINTS[$key] ?? '', 'kind' => 'skirt'];
        }

        foreach (static::PANTS_BLOCKS as $key) {
            $lower[$key] = ['label' => $label($key), 'hint' => static::BLOCK_HINTS[$key] ?? '', 'kind' => 'pants'];
        }

        $sleeve = ['none' => ['label' => 'بدون آستین', 'hint' => 'حلقه آستین با نوار یا سجاف تمام می‌شود.']];

        foreach (static::SLEEVE_BLOCKS as $key) {
            $sleeve[$key] = ['label' => $label($key), 'hint' => static::BLOCK_HINTS[$key] ?? ''];
        }

        $collar = ['none' => ['label' => 'بدون یقه', 'hint' => 'خط یقه با سجاف تمام می‌شود.']];

        foreach (static::COLLAR_STYLES as $style) {
            $collar[$style] = [
                'label' => static::COLLAR_LABELS[$style],
                'hint' => static::COLLAR_HINTS[$style],
            ];
        }

        return ['bodice' => $bodice, 'sleeve' => $sleeve, 'lower' => $lower, 'collar' => $collar];
    }

    public const BLOCK_HINTS = [
        'bodice_block' => 'بلوک پایه با ساسون سینه و کمر؛ قالب‌ترین بالاتنه.',
        'tshirt' => 'بالاتنه بدون ساسون برای پارچه کشی.',
        'shirt_classic' => 'تنه پیراهنی با یوک پشت و جای دکمه.',
        'blazer' => 'تنه کت با آزادی بیشتر؛ برای پارچه بدن‌دار.',
        'sleeve' => 'آستین یک‌تکه که سرآستینش با حلقه آستین جور می‌شود.',
        'skirt_a_line' => 'دامن ساده که از باسن به پایین باز می‌شود.',
        'skirt_pencil' => 'دامن راسته با ساسون و چاک پشت.',
        'pants_straight' => 'شلوار کلاسیک راسته.',
        'pants_wide_leg' => 'شلوار دم‌گشاد با پیلی جلو.',
    ];

    public const COLLAR_LABELS = [
        'band' => 'نوار یقه',
        'stand' => 'یقه ایستاده',
        'shirt' => 'یقه پیراهنی',
        'flat' => 'یقه برگردان',
    ];

    public const COLLAR_HINTS = [
        'band' => 'نوار کشی دور یقه؛ مخصوص تی‌شرت و پارچه کشی.',
        'stand' => 'یقه دیپلمات کوتاه که دور گردن می‌ایستد.',
        'shirt' => 'یقه کلاسیک پیراهن با نوک برگشته.',
        'flat' => 'یقه تخت برگردان که روی سرشانه می‌خوابد.',
    ];

    /** پارامترهای یقه (ژنراتور ندارد، پس توضیح پارامترها همین‌جاست). */
    public function collarSchema(string $style = 'shirt'): array
    {
        $defaults = static::COLLAR_DEFAULTS[$style] ?? static::COLLAR_DEFAULTS['shirt'];

        $schema = [
            'collar_height' => [
                'label' => 'بلندی یقه', 'min' => 1.5, 'max' => 14, 'step' => 0.5,
                'default' => $defaults['collar_height'], 'unit' => 'سانتی‌متر',
            ],
        ];

        if ($style === 'band') {
            $schema['collar_stretch'] = [
                'label' => 'کشش نوار یقه', 'min' => 0.6, 'max' => 1, 'step' => 0.05,
                'default' => $defaults['collar_stretch'],
                'hint' => 'نوار به این نسبت از خط یقه کوتاه‌تر بریده می‌شود تا کشیده و صاف بنشیند.',
            ];

            return $schema;
        }

        $schema['collar_point'] = [
            'label' => 'برگشت نوک یقه', 'min' => 0, 'max' => 6, 'step' => 0.5,
            'default' => $defaults['collar_point'], 'unit' => 'سانتی‌متر',
        ];

        $schema['collar_spread'] = [
            'label' => 'خوابیدگی یقه', 'min' => 0, 'max' => 8, 'step' => 0.5,
            'default' => $defaults['collar_spread'], 'unit' => 'سانتی‌متر',
            'hint' => 'هرچه بیشتر باشد یقه بیشتر روی سرشانه می‌خوابد.',
        ];

        return $schema;
    }

    public const COLLAR_DEFAULTS = [
        'band' => ['collar_height' => 2.5, 'collar_stretch' => 0.85, 'collar_point' => 0, 'collar_spread' => 0],
        'stand' => ['collar_height' => 4.0, 'collar_stretch' => 1.0, 'collar_point' => 0.5, 'collar_spread' => 0.5],
        'shirt' => ['collar_height' => 7.5, 'collar_stretch' => 1.0, 'collar_point' => 2.0, 'collar_spread' => 1.5],
        'flat' => ['collar_height' => 6.0, 'collar_stretch' => 1.0, 'collar_point' => 1.0, 'collar_spread' => 5.0],
    ];

    /**
     * توضیح پارامترهای هر بخشِ انتخاب‌شده، برای ساختن فرم «تنظیمات حرفه‌ای».
     *
     * @return array<string, array{key: string, label: string, schema: array<string, array<string, mixed>>, defaults: array<string, mixed>}>
     */
    public function paramsSchema(array $selection): array
    {
        $selection = $this->normalizeSelection($selection, validate: false);
        $out = [];

        foreach (['bodice', 'sleeve', 'lower'] as $group) {
            $key = $selection[$group] ?? null;

            if ($key === null) {
                continue;
            }

            $generator = GeneratorRegistry::make($key);

            $out[$group] = [
                'key' => $key,
                'label' => $generator->label(),
                'schema' => $generator->paramsSchema(),
                'defaults' => $generator->defaultParams(),
            ];
        }

        if (($selection['collar'] ?? null) !== null) {
            $style = $selection['collar'];

            $out['collar'] = [
                'key' => $style,
                'label' => static::COLLAR_LABELS[$style],
                'schema' => $this->collarSchema($style),
                'defaults' => static::COLLAR_DEFAULTS[$style],
            ];
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  اعتبارسنجی انتخاب
     * ------------------------------------------------------------------- */

    /**
     * انتخاب کاربر را مرتب و بررسی می‌کند.
     *
     * ورودی می‌تواند کلید lower داشته باشد یا skirt و pants جدا.
     *
     * @return array{bodice: string|null, sleeve: string|null, lower: string|null, lower_kind: string|null, collar: string|null}
     */
    public function normalizeSelection(array $selection, bool $validate = true): array
    {
        $clean = fn (mixed $value) => is_string($value) && $value !== '' && $value !== 'none' ? $value : null;

        $bodice = $clean($selection['bodice'] ?? null);
        $sleeve = $clean($selection['sleeve'] ?? null);
        $collar = $clean($selection['collar'] ?? null);
        $skirt = $clean($selection['skirt'] ?? null);
        $pants = $clean($selection['pants'] ?? null);
        $lower = $clean($selection['lower'] ?? null);

        if ($lower !== null) {
            if (in_array($lower, static::PANTS_BLOCKS, true)) {
                $pants ??= $lower;
            } else {
                $skirt ??= $lower;
            }
        }

        if ($validate) {
            if ($skirt !== null && $pants !== null) {
                throw new InvalidArgumentException('دامن و شلوار هم‌زمان به یک بالاتنه دوخته نمی‌شوند؛ فقط یکی را انتخاب کنید.');
            }

            if ($bodice === null && $collar !== null) {
                throw new InvalidArgumentException('یقه بدون بالاتنه ساخته نمی‌شود؛ اول یک بالاتنه انتخاب کنید.');
            }

            if ($bodice === null && $sleeve !== null) {
                throw new InvalidArgumentException('آستین بدون بالاتنه ساخته نمی‌شود؛ اول یک بالاتنه انتخاب کنید.');
            }

            if ($bodice === null) {
                throw new InvalidArgumentException('برای ترکیب مدل‌ها انتخاب بالاتنه الزامی است.');
            }

            if (! in_array($bodice, static::BODICE_BLOCKS, true)) {
                throw new InvalidArgumentException("«{$bodice}» بالاتنه نیست؛ یکی از بالاتنه‌های فهرست را انتخاب کنید.");
            }

            if ($sleeve !== null && ! in_array($sleeve, static::SLEEVE_BLOCKS, true)) {
                throw new InvalidArgumentException("آستین «{$sleeve}» شناخته نشد.");
            }

            if ($skirt !== null && ! in_array($skirt, static::SKIRT_BLOCKS, true)) {
                throw new InvalidArgumentException("دامن «{$skirt}» شناخته نشد.");
            }

            if ($pants !== null && ! in_array($pants, static::PANTS_BLOCKS, true)) {
                throw new InvalidArgumentException("شلوار «{$pants}» شناخته نشد.");
            }

            if ($collar !== null && ! in_array($collar, static::COLLAR_STYLES, true)) {
                throw new InvalidArgumentException("یقه «{$collar}» شناخته نشد.");
            }
        }

        return [
            'bodice' => $bodice,
            'sleeve' => $sleeve,
            'lower' => $skirt ?? $pants,
            'lower_kind' => $skirt !== null ? 'skirt' : ($pants !== null ? 'pants' : null),
            'collar' => $collar,
        ];
    }

    /* ---------------------------------------------------------------------
     |  ترکیب
     * ------------------------------------------------------------------- */

    /**
     * ترکیب بلوک‌ها و ساخت فهرست یکپارچه قطعه‌ها.
     *
     * $ease و $params می‌توانند تخت باشند (برای همه بخش‌ها) یا با کلید بخش
     * (bodice/sleeve/lower/collar) مقدار ویژه همان بخش را بدهند.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, float>  $measurements
     * @param  array<string, mixed>  $ease
     * @param  array<string, mixed>  $params
     * @param  array<string, float>  $seamAllowances
     * @return array<string, mixed>
     */
    public function compose(
        array $selection,
        array $measurements,
        array $ease = [],
        array $params = [],
        array $seamAllowances = [],
    ): array {
        $selection = $this->normalizeSelection($selection);
        $measurements = Measurements::complete($measurements);
        $seamMap = $seamAllowances === [] ? PatternBuilder::DEFAULT_SEAM_ALLOWANCES : $seamAllowances;

        $notes = [];
        $metrics = [];

        // ۱) بالاتنه — فقط قطعه‌های تنه؛ آستین و یقه خودِ مدل کنار گذاشته می‌شود
        $bodiceLabel = GeneratorRegistry::make($selection['bodice'])->label();
        $bodice = $this->keepParts(
            $this->blockPieces('bodice', $selection['bodice'], $measurements, $ease, $params),
            static::BODICE_PARTS,
            $droppedBodice,
        );

        if ($droppedBodice !== []) {
            $notes[] = $this->note('info', 'از مدل «'.$bodiceLabel.'» فقط قطعه‌های تنه برداشته شد؛ '
                .$this->names($droppedBodice).' کنار گذاشته شد چون آستین و یقه را جداگانه انتخاب می‌کنید.');
        }

        // ۲) پایین‌تنه
        $lower = [];
        $lowerLabel = null;

        if ($selection['lower'] !== null) {
            $lowerLabel = GeneratorRegistry::make($selection['lower'])->label();
            $lower = $this->keepParts(
                $this->blockPieces('lower', $selection['lower'], $measurements, $ease, $params),
                static::LOWER_PARTS,
                $droppedLower,
            );

            if ($droppedLower !== []) {
                $notes[] = $this->note('info', $this->names($droppedLower)
                    .' کنار گذاشته شد، چون پایین‌تنه مستقیم به بالاتنه دوخته می‌شود و کمربند جدا نمی‌خواهد.');
            }
        }

        // ۳) بریدن بالاتنه در خط کمر و جورکردن دور کمر
        if ($lower !== []) {
            $cropped = [];
            $atWaist = [];

            foreach ($bodice as $piece) {
                $before = Geometry::height($piece['outline']);
                $piece = $this->cropAtWaist($piece);

                if (Geometry::height($piece['outline']) < $before - 0.5) {
                    $cropped[] = $piece['name'];
                }

                $atWaist[] = $piece;
            }

            $bodice = $atWaist;

            if ($cropped !== []) {
                $notes[] = $this->note('info', 'بالاتنه در خط کمر بریده شد ('.implode('، ', array_unique($cropped)).') تا به '.$lowerLabel.' برسد.');
            }

            [$bodice, $lower, $waist, $waistNotes] = $this->reconcileWaist($bodice, $lower, $lowerLabel ?? 'پایین‌تنه', $params);
            $metrics['waist'] = $waist;
            $notes = array_merge($notes, $waistNotes);
        }

        // ۴) آستین: سرآستین با حلقه آستینِ همین ترکیب جور می‌شود
        $sleeve = [];

        if ($selection['sleeve'] !== null) {
            [$bodice, $sleeve, $sleeveMetrics, $sleeveNotes] = $this->fitSleeve($selection['sleeve'], $bodice, $measurements, $ease, $params);
            $metrics['sleeve'] = $sleeveMetrics;
            $notes = array_merge($notes, $sleeveNotes);
        }

        // ۵) یقه به اندازه خط یقه همین ترکیب
        $collar = [];

        if ($selection['collar'] !== null) {
            [$collar, $collarMetrics, $collarNotes] = $this->fitCollar($selection['collar'], $bodice, $params);
            $metrics['collar'] = $collarMetrics;
            $notes = array_merge($notes, $collarNotes);
        }

        // ۶) یکپارچه‌سازی: گروه، کد یکتا، ترتیب، برچسب لبه‌ها و جای دوخت
        $pieces = $this->mergePieces([
            'bodice' => $bodice,
            'lower' => $lower,
            'sleeve' => $sleeve,
            'collar' => $collar,
        ], $seamMap);

        $metrics['pieces'] = count($pieces);
        $metrics['cut_pieces'] = (int) collect($pieces)->sum('cut_quantity');

        return [
            'selection' => $selection,
            'labels' => [
                'bodice' => $bodiceLabel,
                'lower' => $lowerLabel,
                'sleeve' => $selection['sleeve'] ? GeneratorRegistry::make($selection['sleeve'])->label() : null,
                'collar' => $selection['collar'] ? static::COLLAR_LABELS[$selection['collar']] : null,
            ],
            'pieces' => $pieces,
            'notes' => $notes,
            'metrics' => $metrics,
            'measurements' => $measurements,
            'seam_allowances' => $seamMap,
            'sewing_relations' => $this->relations($pieces),
            'name' => $this->suggestName($selection),
        ];
    }

    /**
     * فقط فهرست قطعه‌های ترکیب‌شده.
     *
     * @return array<int, array<string, mixed>>
     */
    public function composePieces(
        array $selection,
        array $measurements,
        array $ease = [],
        array $params = [],
        array $seamAllowances = [],
    ): array {
        return $this->compose($selection, $measurements, $ease, $params, $seamAllowances)['pieces'];
    }

    /**
     * ذخیره ترکیب به شکل یک الگوی معمولی (با نسخه اول)، تا در همان ویرایشگر و
     * چاپ و خروجی موجود باز شود.
     *
     * $context: measurements، ease، params، seam_allowances، name، base_size،
     * garment_type_id، measurement_set_id، workshop_id، notes
     */
    public function composeIntoPattern(array $selection, array $context = []): Pattern
    {
        $measurements = Measurements::complete($context['measurements'] ?? []);
        $seamAllowances = $context['seam_allowances'] ?? $this->workshopSeamAllowances();

        $result = $this->compose(
            $selection,
            $measurements,
            $context['ease'] ?? [],
            $context['params'] ?? [],
            $seamAllowances,
        );

        $size = (string) ($context['base_size'] ?? Measurements::guessSize($measurements));
        $name = trim((string) ($context['name'] ?? '')) ?: $result['name'];

        return DB::transaction(function () use ($result, $context, $measurements, $size, $name, $seamAllowances) {
            $pattern = Pattern::create(array_filter([
                'workshop_id' => $context['workshop_id'] ?? null,
                'garment_type_id' => $context['garment_type_id'] ?? $this->guessGarmentTypeId($result['selection']),
                'measurement_set_id' => $context['measurement_set_id'] ?? null,
                'name' => $name,
                'base_size' => $size,
                'notes' => $context['notes'] ?? null,
            ], fn ($value) => $value !== null) + [
                'measurements' => $measurements,
                'ease' => $context['ease'] ?? [],
                'seam_allowances' => $seamAllowances,
                'params' => [
                    'compose' => [
                        'selection' => $result['selection'],
                        'labels' => $result['labels'],
                        'metrics' => $result['metrics'],
                        'notes' => $result['notes'],
                    ],
                    'blocks' => $context['params'] ?? [],
                ],
                'sewing_relations' => $result['sewing_relations'],
                'version' => 1,
            ]);

            foreach ($result['pieces'] as $piece) {
                $pattern->pieces()->create($piece);
            }

            $pattern->load('pieces');

            $this->versions->snapshot($pattern, 'ترکیب مدل‌ها: '.$this->selectionSummary($result));

            return $pattern->load('pieces');
        });
    }

    /* ---------------------------------------------------------------------
     |  اندازه‌گیری‌های دوخت
     * ------------------------------------------------------------------- */

    /**
     * دور کمر «دوخته‌شده» یک دسته قطعه: طول لبه کمر منهای ساسون و پیلی، ضرب در
     * تعداد دفعاتی که آن قطعه دور بدن تکرار می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    public function waistGirth(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $edge = $this->edgeWithTag($piece, 'waist');

            if ($edge === null) {
                continue;
            }

            $total += $this->repeats($piece) * $this->seamLength($piece, $edge);

            // اضافه جای دکمه روی هم می‌افتد و جزو دور کمر نیست
            $total -= $this->repeats($piece) * (float) ($piece['meta']['button_stand'] ?? 0);
        }

        return round($total, 2);
    }

    /** طول حلقه آستینِ ترکیب (جلو + پشت + یوک). */
    public function armholeLength(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            foreach ($this->edgesWithTag($piece, 'armhole') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /** نصف خط یقه (جلو + پشت روی تای پارچه) — همان اندازه‌ای که نیم‌یقه باید داشته باشد. */
    public function necklineLength(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            foreach ($this->edgesWithTag($piece, 'neck') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }

            $total += (float) ($piece['meta']['button_stand'] ?? 0);
        }

        return round($total, 2);
    }

    /** طول سرآستین (مجموع لبه‌های حلقه‌دار آستین). */
    public function capLength(array $piece): float
    {
        $total = 0.0;

        foreach ($this->edgesWithTag($piece, 'armhole') as $edge) {
            $total += Geometry::edgeLength($piece['outline'], $edge);
        }

        return round($total, 2);
    }

    /**
     * طول واقعی دوخت یک لبه: طول لبه منهای ساسون‌ها، پیلی‌ها و چینی که روی همان
     * لبه بسته می‌شود.
     */
    public function seamLength(array $piece, int $edge): float
    {
        $length = Geometry::edgeLength($piece['outline'], $edge);
        $length -= $this->consumedOn($piece, $edge);

        return round(max(0.0, $length), 2);
    }

    /** مقداری از یک لبه که با ساسون، پیلی یا چین بسته می‌شود. */
    protected function consumedOn(array $piece, int $edge): float
    {
        $consumed = 0.0;

        foreach ($piece['darts'] ?? [] as $dart) {
            if ((int) ($dart['edge'] ?? -1) === $edge) {
                $consumed += (float) ($dart['intake'] ?? 0);
            }
        }

        foreach ($piece['pleats'] ?? [] as $pleat) {
            if ((int) ($pleat['edge'] ?? $edge) === $edge) {
                $consumed += (float) ($pleat['depth'] ?? 0);
            }
        }

        foreach ($piece['meta']['gathers'] ?? [] as $gather) {
            if ((int) ($gather['edge'] ?? -1) === $edge) {
                $consumed += (float) ($gather['amount'] ?? 0);
            }
        }

        return $consumed;
    }

    /** چند بار این قطعه دور بدن تکرار می‌شود (روی تای پارچه = دو برابر). */
    protected function repeats(array $piece): int
    {
        return ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
    }

    /* ---------------------------------------------------------------------
     |  جورکردن کمر
     * ------------------------------------------------------------------- */

    /**
     * لبه کمر بالاتنه و پایین‌تنه را هم‌اندازه می‌کند.
     *
     * دو راه صادقانه دارد و هر دو ثبت می‌شود:
     *   چین‌دادن — وقتی پایین‌تنه گشادتر است و اختلاف در حد چین‌خوردن است.
     *   راست‌سازی درز پهلو — اختلاف بین درزهای پهلو پخش و از هندسه کم می‌شود.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<string, mixed>, 3: array<int, array<string, string>>}
     */
    protected function reconcileWaist(array $bodice, array $lower, string $lowerLabel, array $params): array
    {
        $mode = (string) ($params['waist_join'] ?? 'auto');

        $bodiceWaist = $this->waistGirth($bodice);
        $lowerWaist = $this->waistGirth($lower);
        $difference = round($lowerWaist - $bodiceWaist, 2);

        $metrics = [
            'bodice' => $bodiceWaist,
            'lower' => $lowerWaist,
            'difference' => $difference,
            'method' => 'none',
            'gathered' => 0.0,
            'trued' => 0.0,
        ];

        if ($bodiceWaist <= 0 || $lowerWaist <= 0) {
            return [$bodice, $lower, $metrics, [$this->note('warning', 'خط کمر یکی از دو بخش پیدا نشد؛ دوخت کمر را دستی بررسی کنید.')]];
        }

        if (abs($difference) <= static::WAIST_TOLERANCE) {
            $metrics['method'] = 'match';
            $metrics['bodice_after'] = $bodiceWaist;
            $metrics['lower_after'] = $lowerWaist;

            return [$bodice, $lower, $metrics, [$this->note('tip', 'کمر بالاتنه و '.$lowerLabel
                .' بدون تغییر جور شد ('.Format::cm($bodiceWaist).').')]];
        }

        $gathering = $mode === 'gather'
            || ($mode === 'auto' && $difference > 0 && $difference <= static::MAX_GATHER);

        if ($gathering && $difference > 0) {
            $lower = $this->gatherWaist($lower, $difference);
            $metrics['method'] = 'gather';
            $metrics['gathered'] = round($difference, 2);
            $note = $this->note('tip', Format::cm(abs($difference)).' اختلاف کمر با چین‌دادن '.$lowerLabel.' جبران شد.');
        } else {
            // بخش گشادتر با راست‌سازی درز پهلو کوچک می‌شود
            [$bodice, $lower, $trued] = $difference > 0
                ? [$bodice, $this->trueWaist($lower, $difference), $difference]
                : [$this->trueWaist($bodice, -$difference), $lower, -$difference];

            $metrics['method'] = 'true_side_seams';
            $metrics['trued'] = round($trued, 2);
            $note = $this->note('tip', Format::cm($trued).' اختلاف کمر با راست‌سازی درز پهلوی '
                .($difference > 0 ? $lowerLabel : 'بالاتنه').' جبران شد.');
        }

        $metrics['bodice_after'] = $this->waistGirth($bodice);
        $metrics['lower_after'] = $this->waistGirth($lower);
        $metrics['remaining'] = round($metrics['lower_after'] - $metrics['bodice_after'], 2);

        $notes = [$note];

        if (abs($metrics['remaining']) > static::WAIST_TOLERANCE) {
            $notes[] = $this->note('warning', 'هنوز '.Format::cm(abs($metrics['remaining']))
                .' اختلاف کمر مانده است؛ اندازه‌ها یا آزادی دو بخش را بازبینی کنید.');
        }

        return [$bodice, $lower, $metrics, $notes];
    }

    /**
     * اختلاف را روی لبه کمر قطعه‌ها به شکل چین ثبت می‌کند (هندسه دست نمی‌خورد،
     * پارچه در دوخت جمع می‌شود).
     */
    protected function gatherWaist(array $pieces, float $amount): array
    {
        $share = $this->waistShare($pieces);

        if ($share <= 0) {
            return $pieces;
        }

        foreach ($pieces as $index => $piece) {
            $edge = $this->edgeWithTag($piece, 'waist');

            if ($edge === null) {
                continue;
            }

            $piece['meta']['gathers'][] = [
                'edge' => $edge,
                'amount' => round($amount / $share, 2),
                'label' => 'چین کمر',
            ];

            $pieces[$index] = $piece;
        }

        return $pieces;
    }

    /** مجموع دفعات تکرار قطعه‌های کمردار — برای پخش‌کردن اختلاف. */
    protected function waistShare(array $pieces): int
    {
        $share = 0;

        foreach ($pieces as $piece) {
            if ($this->edgeWithTag($piece, 'waist') !== null) {
                $share += $this->repeats($piece);
            }
        }

        return $share;
    }

    /**
     * کوچک‌کردن کمر با راست‌سازی درز پهلو: نقطه پهلوی لبه کمر به اندازه لازم به
     * سمت مرکز می‌آید و منحنی پهلو با آن هم‌راه می‌شود.
     */
    protected function trueWaist(array $pieces, float $amount): array
    {
        $share = $this->waistShare($pieces);

        if ($share <= 0 || $amount <= 0) {
            return $pieces;
        }

        $target = $amount / $share;

        foreach ($pieces as $index => $piece) {
            $edge = $this->edgeWithTag($piece, 'waist');

            if ($edge === null) {
                continue;
            }

            $start = $this->seamLength($piece, $edge);
            $goal = max(1.0, $start - $target);
            $delta = $target;

            // لبه کمر همیشه کاملاً افقی نیست، پس چند بار تکرار می‌کنیم تا دقیق شود
            for ($i = 0; $i < 8; $i++) {
                $piece = $this->shrinkWaistEdge($piece, $edge, $delta);
                $now = $this->seamLength($piece, $edge);
                $delta = $now - $goal;

                if (abs($delta) < 0.02) {
                    break;
                }
            }

            $pieces[$index] = $piece;
        }

        return $pieces;
    }

    /** جابه‌جایی نقطه پهلوی لبه کمر به اندازه $delta (مثبت = تنگ‌تر). */
    protected function shrinkWaistEdge(array $piece, int $edge, float $delta): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $a = $edge % $count;
        $b = ($edge + 1) % $count;

        // نقطه «پهلو» همان سرِ دورتر از خط مرکز است
        $centerIsA = (float) $outline[$a]['x'] <= (float) $outline[$b]['x'];
        $side = $centerIsA ? $b : $a;
        $center = $centerIsA ? $a : $b;
        $sideX = (float) $outline[$side]['x'];
        $centerX = (float) $outline[$center]['x'];
        $direction = $sideX >= $centerX ? -1 : 1;
        $shift = $direction * $delta;

        $outline[$side]['x'] = round($sideX + $shift, 3);

        if (isset($outline[$side]['cx'])) {
            $outline[$side]['cx'] = round(((float) $outline[$side]['cx']) + ($shift * 0.5), 3);
        }

        $next = ($side + 1) % $count;

        if (isset($outline[$next]['cx'])) {
            $outline[$next]['cx'] = round(((float) $outline[$next]['cx']) + ($shift * 0.5), 3);
        }

        $piece['outline'] = $outline;
        $waistY = (float) $outline[$side]['y'];

        // ساسون‌ها و نشانه‌های روی خط کمر به نسبت فاصله‌شان از مرکز جابه‌جا می‌شوند
        $span = abs($sideX - $centerX);

        if ($span > 0.01) {
            $move = function (array $point) use ($centerX, $span, $shift, $waistY) {
                if (abs(((float) $point['y']) - $waistY) > 1.5) {
                    return $point;
                }

                $ratio = min(1.0, abs(((float) $point['x']) - $centerX) / $span);
                $point['x'] = round(((float) $point['x']) + ($shift * $ratio), 3);

                return $point;
            };

            foreach (['darts', 'notches'] as $key) {
                foreach ($piece[$key] ?? [] as $i => $item) {
                    if (isset($item['x'], $item['y'])) {
                        $item = $move($item);
                    }

                    foreach (['center', 'apex'] as $sub) {
                        if (isset($item[$sub]['x'], $item[$sub]['y'])) {
                            $item[$sub] = $move($item[$sub]);
                        }
                    }

                    if (isset($item['legs']) && is_array($item['legs'])) {
                        $item['legs'] = array_map(fn ($leg) => is_array($leg) && isset($leg['x'], $leg['y']) ? $move($leg) : $leg, $item['legs']);
                    }

                    $piece[$key][$i] = $item;
                }
            }
        }

        $piece['meta']['trued_waist'] = round(((float) ($piece['meta']['trued_waist'] ?? 0)) + $delta, 2);

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  آستین و یقه
     * ------------------------------------------------------------------- */

    /**
     * آستین را با حلقه آستین ترکیب جور می‌کند.
     *
     * اول ارتفاع سرآستین برای همین حلقه تنظیم می‌شود. اگر سرآستین باز هم بلندتر از
     * حلقه ماند (دور بازو اجازه کوچک‌تر شدن نمی‌دهد) حلقه آستین گودتر می‌شود —
     * همان کاری که خیاط سر چرخ می‌کند — و اگر باز هم نشد، اختلاف گزارش می‌شود.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<string, mixed>, 3: array<int, array<string, string>>}
     */
    protected function fitSleeve(string $key, array $bodice, array $measurements, array $ease, array $params): array
    {
        $sleeveParams = $this->paramsFor($params, 'sleeve', $key);
        $capEase = (float) ($sleeveParams['cap_ease'] ?? 1.5);

        $free = $this->blockPieces('sleeve', $key, $measurements, $ease, $params);
        $capBefore = $this->capLength($free[0] ?? ['outline' => [], 'meta' => []]);

        $armholeBefore = $this->armholeLength($bodice);
        $armhole = $armholeBefore;
        $dropped = 0.0;
        $fitted = [];
        $capAfter = 0.0;
        $remaining = 0.0;

        for ($round = 0; $round < 5; $round++) {
            $armhole = $this->armholeLength($bodice);
            $fitted = $this->blockPieces('sleeve', $key, $measurements, $ease, $params, ['armhole_length' => $armhole]);
            $capAfter = $this->capLength($this->sleevePiece($fitted));
            $remaining = round($capAfter - ($armhole + $capEase), 2);

            if ($remaining <= static::CAP_TOLERANCE || $dropped >= static::MAX_ARMHOLE_DROP - 0.05) {
                break;
            }

            // حلقه را گودتر می‌کنیم تا سرآستین در آن جا شود
            $step = min($remaining / max(1, $this->armholePieceCount($bodice)), static::MAX_ARMHOLE_DROP - $dropped);
            [$bodice, $applied] = $this->deepenArmhole($bodice, $step);

            if ($applied < 0.05) {
                break;
            }

            $dropped = round($dropped + $applied, 2);
        }

        $sleeveIndex = 0;

        foreach ($fitted as $index => $piece) {
            if (($piece['meta']['part'] ?? null) === 'sleeve') {
                $sleeveIndex = $index;
                break;
            }
        }

        $target = round($armhole + $capEase, 2);
        $fitted[$sleeveIndex]['meta']['armhole_length'] = $armhole;
        $fitted[$sleeveIndex]['meta']['cap_ease'] = round($capAfter - $armhole, 2);

        $metrics = [
            'armhole_before' => $armholeBefore,
            'armhole' => $armhole,
            'armhole_drop' => $dropped,
            'cap_before' => $capBefore,
            'cap_after' => $capAfter,
            'target' => $target,
            'ease' => round($capAfter - $armhole, 2),
            'difference' => $remaining,
            'status' => abs($remaining) <= static::CAP_TOLERANCE ? 'fitted' : 'mismatch',
        ];

        $notes = [];

        if ($dropped > 0.05) {
            $notes[] = $this->note('tip', 'حلقه آستین '.Format::cm($dropped).' گودتر شد تا سرآستین در آن بنشیند؛ '
                .'طول حلقه از '.Format::cm($armholeBefore).' به '.Format::cm($armhole).' رسید.');
        }

        if ($metrics['status'] === 'fitted') {
            $notes[] = $this->note('tip', 'سرآستین با حلقه آستین جور شد: حلقه '.Format::cm($armhole)
                .' و سرآستین '.Format::cm($capAfter).' ('.Format::cm(max(0, $metrics['ease'])).' آزادی برای پرکردن سرشانه).');
        } elseif ($remaining < 0) {
            $notes[] = $this->note('warning', 'سرآستین '.Format::cm(abs($remaining))
                .' از حلقه آستین کوتاه‌تر ماند؛ دور بازو را بیشتر بگیرید یا گودی حلقه آستین را کم کنید.');
        } else {
            $notes[] = $this->note('warning', 'سرآستین '.Format::cm($remaining)
                .' از حلقه آستین بلندتر ماند؛ این مقدار باید در دوخت روی سرشانه چین بخورد.');
        }

        return [$bodice, $fitted, $metrics, $notes];
    }

    /** قطعه آستین از میان قطعه‌های بلوک آستین. */
    protected function sleevePiece(array $pieces): array
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? null) === 'sleeve') {
                return $piece;
            }
        }

        return $pieces[0] ?? ['outline' => [], 'meta' => []];
    }

    /** چند قطعه بالاتنه لبه حلقه آستین دارد (یوک جدا حساب نمی‌شود). */
    protected function armholePieceCount(array $pieces): int
    {
        $count = 0;

        foreach ($pieces as $piece) {
            if ($this->canDeepen($piece)) {
                $count++;
            }
        }

        return $count;
    }

    protected function canDeepen(array $piece): bool
    {
        return in_array($piece['meta']['part'] ?? null, ['front_bodice', 'back_bodice'], true)
            && $this->edgeWithTag($piece, 'armhole') !== null;
    }

    /**
     * گودتر کردن حلقه آستین: نقطه زیر بغل پایین‌تر می‌رود و منحنی حلقه و درز پهلو
     * با آن هم‌راه می‌شود. مقدار واقعی اعمال‌شده برگردانده می‌شود.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    protected function deepenArmhole(array $pieces, float $drop): array
    {
        $applied = 0.0;

        foreach ($pieces as $index => $piece) {
            if (! $this->canDeepen($piece)) {
                continue;
            }

            $edge = $this->edgeWithTag($piece, 'armhole');
            $outline = array_values($piece['outline']);
            $count = count($outline);
            $under = ($edge + 1) % $count;
            $waistY = $this->waistLineY($piece);
            $room = $waistY === null
                ? $drop
                : max(0.0, $waistY - 6 - ((float) $outline[$under]['y']));
            $step = round(min($drop, $room), 3);

            if ($step < 0.05) {
                continue;
            }

            $outline[$under]['y'] = round(((float) $outline[$under]['y']) + $step, 3);

            if (isset($outline[$under]['cy'])) {
                $outline[$under]['cy'] = round(((float) $outline[$under]['cy']) + ($step * 0.55), 3);
            }

            $next = ($under + 1) % $count;

            if (isset($outline[$next]['cy'])) {
                $outline[$next]['cy'] = round(((float) $outline[$next]['cy']) + ($step * 0.35), 3);
            }

            $piece['outline'] = $outline;
            $piece['meta']['armhole_drop'] = round(((float) ($piece['meta']['armhole_drop'] ?? 0)) + $step, 2);
            $piece['meta']['armhole_length'] = Geometry::edgeLength($outline, $edge);

            // نشانه جفت‌شدن آستین روی منحنی تازه می‌نشیند
            $t = ($piece['meta']['side'] ?? 'front') === 'front' ? 0.62 : 0.55;
            $on = Geometry::pointOnEdge($outline, $edge, $t);

            foreach ($piece['notches'] ?? [] as $i => $notch) {
                if (($notch['pair'] ?? null) === 'armhole') {
                    $piece['notches'][$i]['x'] = round($on['x'], 2);
                    $piece['notches'][$i]['y'] = round($on['y'], 2);
                    $piece['notches'][$i]['edge'] = $edge;
                }
            }

            $pieces[$index] = $piece;
            $applied = max($applied, $step);
        }

        return [$pieces, $applied];
    }

    /**
     * یقه را به اندازه خط یقه ترکیب می‌سازد.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: array<int, array<string, string>>}
     */
    protected function fitCollar(string $style, array $bodice, array $params): array
    {
        $neck = $this->necklineLength($bodice);
        $collarParams = array_merge(static::COLLAR_DEFAULTS[$style], $this->groupParams($params, 'collar'));

        if ($neck <= 0) {
            return [[], ['neckline' => 0.0, 'status' => 'missing'], [
                $this->note('warning', 'خط یقه روی بالاتنه پیدا نشد، پس یقه ساخته نشد.'),
            ]];
        }

        if ($style === 'band') {
            $stretch = min(1.0, max(0.6, (float) $collarParams['collar_stretch']));
            $piece = $this->bandPiece($neck * 2, $stretch, (float) $collarParams['collar_height']);

            $metrics = [
                'neckline' => $neck,
                'target' => round($neck * 2, 2),
                'collar' => round($neck * 2 * $stretch, 2),
                'stretch' => $stretch,
                'status' => 'fitted',
            ];

            return [[$piece], $metrics, [
                $this->note('tip', 'نوار یقه به اندازه '.Format::cm($metrics['collar']).' بریده شد؛ '
                    .Format::cm(round($metrics['target'] - $metrics['collar'], 2))
                    .' کوتاه‌تر از خط یقه تا کشیده و صاف بنشیند.'),
            ]];
        }

        [$piece, $length] = $this->fitHalfCollar($style, $neck, $collarParams);
        $difference = round($length - $neck, 2);

        $metrics = [
            'neckline' => $neck,
            'target' => $neck,
            'collar' => $length,
            'difference' => $difference,
            'status' => abs($difference) <= 0.4 ? 'fitted' : 'mismatch',
        ];

        $notes = [$metrics['status'] === 'fitted'
            ? $this->note('tip', static::COLLAR_LABELS[$style].' به اندازه نیم‌خط یقه ('
                .Format::cm($neck).') بریده شد.')
            : $this->note('warning', static::COLLAR_LABELS[$style].' '.Format::cm(abs($difference))
                .' با خط یقه اختلاف دارد؛ بلندی یقه یا عرض خط یقه را تغییر دهید.'),
        ];

        return [[$piece], $metrics, $notes];
    }

    /** نوار یقه کشی: یک نوار دولا به اندازه خط یقه ضربدر کشش پارچه. */
    protected function bandPiece(float $neckline, float $stretch, float $height): array
    {
        $length = round(max(8.0, $neckline * $stretch), 2);
        $full = max(1.5, $height) * 2;

        return $this->piece([
            'code' => 'neckband',
            'name' => 'نوار یقه',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($length, 0),
                Geometry::point($length, $full),
                Geometry::point(0, $full),
            ],
            'grainline' => $this->grainline($length * 0.5, 0.8, $full - 0.8),
            'markers' => [
                $this->marker('fold', 'خط تای نوار یقه', 0, $full / 2, $length),
            ],
            'meta' => [
                'part' => 'collar',
                'collar_style' => 'band',
                'edges' => ['neck', 'side', 'default', 'side'],
                'fold_edges' => [],
                'neck_length' => $length,
                'neckline_length' => round($neckline, 2),
                'stretch' => $stretch,
            ],
        ]);
    }

    /**
     * نیم‌یقه روی تای مرکز پشت؛ بلندی افقی یقه چند بار تنظیم می‌شود تا طول لبه
     * یقه دقیقاً به نیم‌خط یقه برسد (همان کاری که خیاط با پیاده‌کردن خط یقه می‌کند).
     *
     * @return array{0: array<string, mixed>, 1: float}
     */
    protected function fitHalfCollar(string $style, float $target, array $params): array
    {
        $length = max(6.0, $target * 0.95);
        $piece = $this->halfCollarPiece($style, $length, $params);

        for ($i = 0; $i < 16; $i++) {
            $current = Geometry::edgeLength($piece['outline'], $this->edgeWithTag($piece, 'neck') ?? 0);

            if (abs($current - $target) < 0.1 || $current <= 0.01) {
                break;
            }

            $length = max(6.0, min(140.0, $length * ($target / $current)));
            $piece = $this->halfCollarPiece($style, $length, $params);
        }

        $final = Geometry::edgeLength($piece['outline'], $this->edgeWithTag($piece, 'neck') ?? 0);
        $piece['meta']['neck_length'] = round($final, 2);
        $piece['meta']['neckline_length'] = round($target, 2);

        return [$piece, round($final, 2)];
    }

    /** درفت یک نیم‌یقه با طول داده‌شده. */
    protected function halfCollarPiece(string $style, float $length, array $params): array
    {
        $height = max(1.5, (float) $params['collar_height']);
        $point = max(0.0, (float) $params['collar_point']);
        $spread = max(0.0, (float) $params['collar_spread']);

        if ($style === 'flat') {
            // یقه برگردان تخت: لبه یقه گود و لبه بیرونی گرد
            $drop = 2.0 + ($spread * 0.35);

            $outline = [
                Geometry::point(0, 0),
                Geometry::curve($length, $drop, $length * 0.62, $drop * 0.12),
                Geometry::curve($length - ($point + 1.0), $drop + $height, $length + 0.6, $drop + ($height * 0.7)),
                Geometry::curve(0, $height + ($spread * 0.25), $length * 0.5, $drop + $height + ($spread * 0.35)),
            ];

            $edges = ['neck', 'side', 'hem', 'default'];
        } else {
            // یقه ایستاده و یقه پیراهنی: نوار کمی کج با نوک برگشته
            $rise = $style === 'shirt' ? 0.8 : 0.4;

            $outline = [
                Geometry::point(0, 0),
                Geometry::point($length, $rise),
                Geometry::point($length + $point, $height),
                Geometry::curve(0, $height, $length * 0.5, $height - ($spread * 0.35) - 0.4),
            ];

            $edges = ['default', 'side', 'neck', 'default'];
        }

        return $this->piece([
            'code' => 'collar',
            'name' => static::COLLAR_LABELS[$style],
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 0.8, max(1.2, $height - 0.6)),
            'notches' => [
                ['x' => 0.0, 'y' => round($height, 2), 'edge' => 2, 'label' => 'مرکز پشت یقه', 'pair' => 'collar_center'],
            ],
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $height),
            ],
            'meta' => [
                'part' => 'collar',
                'collar_style' => $style,
                'edges' => $edges,
                'fold_edges' => [3],
                'interfacing' => true,
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  بریدن بالاتنه در خط کمر
     * ------------------------------------------------------------------- */

    /**
     * قطعه را روی خط کمر می‌برد و لبه تازه را «کمر» برچسب می‌زند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public function cropAtWaist(array $piece, ?float $waistY = null): array
    {
        $waistY ??= $this->waistLineY($piece);

        if ($waistY === null) {
            return $piece;
        }

        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return $piece;
        }

        [, , , $maxY] = Geometry::bounds($outline);

        if ($maxY <= $waistY + 0.5) {
            return $piece; // همین حالا در خط کمر تمام می‌شود
        }

        $tags = array_values($piece['meta']['edges'] ?? []);
        $above = fn (array $point) => ((float) $point['y']) <= $waistY + 0.01;

        $points = [];
        $arrivals = []; // برچسب لبه‌ای که به هر نقطه می‌رسد

        $push = function (array $point, string $tag) use (&$points, &$arrivals) {
            $points[] = $point;
            $arrivals[] = $tag;
        };

        for ($i = 0; $i < $count; $i++) {
            $from = $outline[$i];
            $to = $outline[($i + 1) % $count];
            $tag = $tags[$i] ?? 'default';

            $fromAbove = $above($from);
            $toAbove = $above($to);

            if ($fromAbove && $toAbove) {
                $push($to, $tag);

                continue;
            }

            if ($fromAbove) {
                // از بالا به پایین می‌رود: لبه را روی خط کمر می‌بریم
                $push($this->splitEdge($from, $to, $waistY, keepFirst: true), $tag);

                continue;
            }

            if ($toAbove) {
                // از پایین برمی‌گردد بالا: نقطه برخورد، سپس ادامه همان لبه
                $push($this->splitEdge($from, $to, $waistY, keepFirst: false), 'waist');

                $rest = $to;

                if (Geometry::isCurve($to)) {
                    $t = $this->crossingT($from, $to, $waistY);
                    $control = Geometry::lerp(
                        ['x' => (float) $to['cx'], 'y' => (float) $to['cy']],
                        ['x' => (float) $to['x'], 'y' => (float) $to['y']],
                        $t,
                    );
                    $rest['cx'] = round($control['x'], 3);
                    $rest['cy'] = round($control['y'], 3);
                }

                $push($rest, $tag);
            }
        }

        if (count($points) < 3) {
            return $piece;
        }

        // برچسب لبه i همان برچسب «رسیدن» به نقطه i+1 است
        $edges = [];
        $total = count($points);

        for ($i = 0; $i < $total; $i++) {
            $edges[$i] = $arrivals[($i + 1) % $total];
        }

        $piece['outline'] = Geometry::round($points);
        $piece['meta']['edges'] = $edges;
        $piece['meta']['shape'] = 'waist';
        $piece['meta']['waist_y'] = round($waistY, 2);
        $piece['meta']['cropped_at_waist'] = true;
        $piece['meta']['fold_edges'] = $this->foldEdgesOnCenter($piece);

        $piece = $this->dropBelow($piece, $waistY);
        $piece = $this->reindexAnchors($piece);

        return Geometry::normalizePiece($piece);
    }

    /** نقطه برخورد یک لبه با خط افقی؛ نیمه نگه‌داشته‌شده منحنی خودش را حفظ می‌کند. */
    protected function splitEdge(array $from, array $to, float $y, bool $keepFirst): array
    {
        $t = $this->crossingT($from, $to, $y);

        if (! Geometry::isCurve($to)) {
            $point = Geometry::lerp(
                ['x' => (float) $from['x'], 'y' => (float) $from['y']],
                ['x' => (float) $to['x'], 'y' => (float) $to['y']],
                $t,
            );

            return Geometry::point($point['x'], $point['y']);
        }

        $p0 = ['x' => (float) $from['x'], 'y' => (float) $from['y']];
        $control = ['x' => (float) $to['cx'], 'y' => (float) $to['cy']];
        $p1 = ['x' => (float) $to['x'], 'y' => (float) $to['y']];
        $at = Geometry::quadraticAt($p0, $control, $p1, $t);

        if (! $keepFirst) {
            return Geometry::point($at['x'], $at['y']);
        }

        $first = Geometry::lerp($p0, $control, $t);

        return Geometry::curve($at['x'], $at['y'], $first['x'], $first['y']);
    }

    /** نسبت t روی یک لبه که در آن y به مقدار خواسته‌شده می‌رسد. */
    protected function crossingT(array $from, array $to, float $y): float
    {
        $y0 = (float) $from['y'];
        $y1 = (float) $to['y'];

        if (! Geometry::isCurve($to)) {
            return abs($y1 - $y0) < 1e-9 ? 0.5 : max(0.0, min(1.0, ($y - $y0) / ($y1 - $y0)));
        }

        $p0 = ['x' => (float) $from['x'], 'y' => $y0];
        $control = ['x' => (float) $to['cx'], 'y' => (float) $to['cy']];
        $p1 = ['x' => (float) $to['x'], 'y' => $y1];

        $low = 0.0;
        $high = 1.0;
        $downwards = $y1 > $y0;

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;
            $value = Geometry::quadraticAt($p0, $control, $p1, $mid)['y'];

            if (($value < $y) === $downwards) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /** خط کمر قطعه: از meta یا از خط نشانه «کمر». */
    protected function waistLineY(array $piece): ?float
    {
        if (isset($piece['meta']['waist_y'])) {
            return (float) $piece['meta']['waist_y'];
        }

        foreach ($piece['markers'] ?? [] as $marker) {
            if (($marker['key'] ?? null) === 'waist' && isset($marker['from']['y'])) {
                return (float) $marker['from']['y'];
            }
        }

        return null;
    }

    /** ساسون، نشانه و خط‌های پایین‌تر از برش حذف می‌شوند. */
    protected function dropBelow(array $piece, float $waistY): array
    {
        $limit = $waistY + 0.5;

        $piece['notches'] = array_values(array_filter(
            $piece['notches'] ?? [],
            fn ($notch) => ! isset($notch['y']) || ((float) $notch['y']) <= $limit,
        ));

        $piece['markers'] = array_values(array_filter(
            $piece['markers'] ?? [],
            fn ($marker) => ! isset($marker['from']['y']) || ((float) $marker['from']['y']) <= $limit,
        ));

        $piece['markers'] = array_map(function ($marker) use ($waistY) {
            if (isset($marker['to']['y']) && ((float) $marker['to']['y']) > $waistY) {
                $marker['to']['y'] = round($waistY, 2);
            }

            return $marker;
        }, $piece['markers']);

        $darts = [];

        foreach ($piece['darts'] ?? [] as $dart) {
            $centerY = (float) ($dart['center']['y'] ?? 0);

            if ($centerY > $limit) {
                continue;
            }

            // ساسون بادامی بالاتنه بلند، با بریدن کمر به ساسون معمولی کمر تبدیل می‌شود
            unset($dart['apex_lower']);
            $darts[] = $dart;
        }

        $piece['darts'] = $darts;

        if (! empty($piece['grainline']['to']['y']) && ((float) $piece['grainline']['to']['y']) > $waistY - 1) {
            $piece['grainline']['to']['y'] = round(max(1.0, $waistY - 1), 2);
        }

        return $piece;
    }

    /** لبه‌هایی که روی خط مرکز (x≈۰) هستند؛ بعد از برش دوباره پیدا می‌شوند. */
    protected function foldEdgesOnCenter(array $piece): array
    {
        if (empty($piece['on_fold'])) {
            return [];
        }

        $outline = array_values($piece['outline']);
        $count = count($outline);
        $minX = Geometry::bounds($outline)[0];
        $edges = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $outline[$i];
            $b = $outline[($i + 1) % $count];

            if (abs(((float) $a['x']) - $minX) < 0.05 && abs(((float) $b['x']) - $minX) < 0.05) {
                $edges[] = $i;
            }
        }

        return $edges;
    }

    /** بعد از تغییر مسیر، شماره لبه ساسون‌ها و نشانه‌ها دوباره پیدا می‌شود. */
    protected function reindexAnchors(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return $piece;
        }

        $nearest = function (float $x, float $y) use ($outline, $count) {
            $best = 0;
            $bestDistance = INF;

            for ($edge = 0; $edge < $count; $edge++) {
                for ($step = 0; $step <= 8; $step++) {
                    $on = Geometry::pointOnEdge($outline, $edge, $step / 8);
                    $distance = Geometry::distance($on, ['x' => $x, 'y' => $y]);

                    if ($distance < $bestDistance) {
                        $bestDistance = $distance;
                        $best = $edge;
                    }
                }
            }

            return $best;
        };

        foreach ($piece['notches'] ?? [] as $index => $notch) {
            if (isset($notch['x'], $notch['y'])) {
                $piece['notches'][$index]['edge'] = $nearest((float) $notch['x'], (float) $notch['y']);
            }
        }

        foreach ($piece['darts'] ?? [] as $index => $dart) {
            if (isset($dart['center']['x'], $dart['center']['y'])) {
                $piece['darts'][$index]['edge'] = $nearest((float) $dart['center']['x'], (float) $dart['center']['y']);
            }
        }

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  ساخت بلوک‌ها و یکپارچه‌سازی
     * ------------------------------------------------------------------- */

    /**
     * قطعه‌های خام یک بلوک.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blockPieces(
        string $group,
        string $key,
        array $measurements,
        array $ease = [],
        array $params = [],
        array $extraParams = [],
    ): array {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete($measurements),
            $this->groupEase($ease, $group),
            array_merge($this->paramsFor($params, $group, $key), $extraParams),
        );
    }

    /** نمونه کوچک یک بلوک برای نمایش بندانگشتی در صفحه ترکیب. */
    public function previewPieces(string $group, string $key, array $measurements = []): array
    {
        if ($group === 'collar') {
            if ($key === 'band') {
                return [$this->bandPiece(46, 0.85, 2.5)];
            }

            return [$this->fitHalfCollar($key, 23, static::COLLAR_DEFAULTS[$key])[0]];
        }

        $measurements = $measurements === [] ? Measurements::fromSize('38') : $measurements;
        $pieces = $this->blockPieces($group, $key, $measurements);

        $keep = match ($group) {
            'bodice' => static::BODICE_PARTS,
            'lower' => static::LOWER_PARTS,
            default => null,
        };

        if ($keep !== null) {
            $pieces = $this->keepParts($pieces, $keep, $ignored);
        }

        return array_slice($pieces, 0, 3);
    }

    /**
     * فقط قطعه‌های خواسته‌شده را نگه می‌دارد و بقیه را در $dropped می‌گذارد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function keepParts(array $pieces, array $parts, ?array &$dropped): array
    {
        $kept = [];
        $dropped = [];

        foreach ($pieces as $piece) {
            if (in_array($piece['meta']['part'] ?? null, $parts, true)) {
                $kept[] = $piece;
            } else {
                $dropped[] = $piece;
            }
        }

        return $kept;
    }

    /**
     * کد یکتا، ترتیب مرتب، گروه و جای دوخت هر قطعه.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function mergePieces(array $groups, array $seamMap): array
    {
        $pieces = [];
        $codes = [];
        $sort = 0;

        foreach ($groups as $group => $groupPieces) {
            foreach ($groupPieces as $piece) {
                $piece['meta'] = array_merge($piece['meta'] ?? [], [
                    'group' => $group,
                    'composed' => true,
                ]);

                $code = (string) ($piece['code'] ?? $group);

                if (isset($codes[$code])) {
                    $codes[$code]++;
                    $code = $code.'-'.$codes[$code];
                } else {
                    $codes[$code] = 1;
                }

                $piece['code'] = $code;
                $piece['sort'] = $sort;
                $sort += 10;

                $piece = Geometry::normalizePiece($piece);
                $piece['outline'] = Geometry::round($piece['outline']);
                $piece['edge_allowances'] = $this->seams->allowancesFor($piece, $seamMap);

                $pieces[] = $piece;
            }
        }

        return $pieces;
    }

    /**
     * دوخت مجازی: پیشنهادهای عمومی به‌علاوه دوخت کمر و درز پهلوی پایین‌تنه.
     *
     * @return array<int, array<string, mixed>>
     */
    public function relations(array $pieces): array
    {
        $models = static::toModels($pieces);

        $pattern = new Pattern;
        $pattern->setRelation('pieces', $models);

        $relations = SewingRelationBuilder::suggest($pattern);

        $bodice = collect($pieces)->filter(fn ($piece) => ($piece['meta']['group'] ?? null) === 'bodice');
        $lower = collect($pieces)->filter(fn ($piece) => ($piece['meta']['group'] ?? null) === 'lower');

        // دوخت کمر: جلوی بالاتنه به جلوی پایین‌تنه، پشت به پشت
        foreach (['front', 'back'] as $side) {
            $top = $bodice->first(fn ($piece) => ($piece['meta']['side'] ?? null) === $side
                && $this->edgeWithTag($piece, 'waist') !== null);
            $bottom = $lower->first(fn ($piece) => ($piece['meta']['side'] ?? null) === $side
                && $this->edgeWithTag($piece, 'waist') !== null);

            if ($top === null || $bottom === null) {
                continue;
            }

            $relations[] = [
                'from' => ['piece' => $top['code'], 'edge' => $this->edgeWithTag($top, 'waist')],
                'to' => ['piece' => $bottom['code'], 'edge' => $this->edgeWithTag($bottom, 'waist')],
                'label' => 'دوخت کمر بالاتنه به پایین‌تنه ('.($side === 'front' ? 'جلو' : 'پشت').')',
            ];
        }

        // درز پهلوی پایین‌تنه (سازنده عمومی فقط جلو و پشت بالاتنه را می‌بیند)
        $lowerFront = $lower->first(fn ($piece) => ($piece['meta']['side'] ?? null) === 'front');
        $lowerBack = $lower->first(fn ($piece) => ($piece['meta']['side'] ?? null) === 'back');

        if ($lowerFront && $lowerBack) {
            $frontSides = $this->edgesWithTag($lowerFront, 'side');
            $backSides = $this->edgesWithTag($lowerBack, 'side');
            $pairs = min(count($frontSides), count($backSides));
            $isPants = in_array($lowerFront['meta']['part'] ?? null, ['front_leg'], true);

            for ($i = 0; $i < $pairs; $i++) {
                $relations[] = [
                    'from' => ['piece' => $lowerFront['code'], 'edge' => $frontSides[$i]],
                    'to' => ['piece' => $lowerBack['code'], 'edge' => $backSides[$i]],
                    'label' => ($isPants ? 'درز پا' : 'درز پهلوی دامن').($pairs > 1 ? ' '.($i + 1) : ''),
                ];
            }
        }

        return $this->uniqueRelations($relations);
    }

    /** حذف رابطه‌های تکراری. */
    protected function uniqueRelations(array $relations): array
    {
        $seen = [];
        $out = [];

        foreach ($relations as $relation) {
            $key = ($relation['from']['piece'] ?? '').':'.($relation['from']['edge'] ?? '')
                .'>'.($relation['to']['piece'] ?? '').':'.($relation['to']['edge'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $relation;
        }

        return array_values($out);
    }

    /**
     * تبدیل فهرست آرایه‌ای قطعه‌ها به مدل‌های ذخیره‌نشده (برای SvgRenderer و دوخت مجازی).
     *
     * @return Collection<int, PatternPiece>
     */
    public static function toModels(array $pieces): Collection
    {
        return collect($pieces)->map(fn (array $piece) => new PatternPiece($piece));
    }

    /* ---------------------------------------------------------------------
     |  کمک‌کننده‌ها
     * ------------------------------------------------------------------- */

    /** اولین لبه با برچسب خواسته‌شده. */
    public function edgeWithTag(array $piece, string $tag): ?int
    {
        $edges = $this->edgesWithTag($piece, $tag);

        return $edges === [] ? null : $edges[0];
    }

    /** @return array<int, int> */
    public function edgesWithTag(array $piece, string $tag): array
    {
        $out = [];

        foreach ($piece['meta']['edges'] ?? [] as $index => $value) {
            if ($value === $tag) {
                $out[] = (int) $index;
            }
        }

        return $out;
    }

    /** آزادی این بخش: مقدارهای تخت به‌علاوه مقدارهای ویژه همان بخش. */
    protected function groupEase(array $ease, string $group): array
    {
        $flat = array_filter($ease, fn ($value, $key) => is_numeric($value), ARRAY_FILTER_USE_BOTH);
        $own = is_array($ease[$group] ?? null) ? $ease[$group] : [];

        return array_merge($flat, $own);
    }

    /** پارامترهای این بخش (بدون پیش‌فرض ژنراتور). */
    protected function groupParams(array $params, string $group): array
    {
        $flat = array_filter($params, fn ($value, $key) => ! is_array($value), ARRAY_FILTER_USE_BOTH);
        $own = is_array($params[$group] ?? null) ? $params[$group] : [];

        unset($flat['waist_join']);

        return array_merge($flat, $own);
    }

    /** پارامترهای این بخش روی پیش‌فرض ژنراتور. */
    protected function paramsFor(array $params, string $group, string $key): array
    {
        return array_merge(GeneratorRegistry::make($key)->defaultParams(), $this->groupParams($params, $group));
    }

    /** @return array{type: string, text: string} */
    protected function note(string $type, string $text): array
    {
        return ['type' => $type, 'text' => $text];
    }

    /** نام قطعه‌ها برای پیام فارسی. */
    protected function names(array $pieces): string
    {
        return implode('، ', array_unique(array_map(fn ($piece) => (string) ($piece['name'] ?? $piece['code'] ?? '؟'), $pieces)));
    }

    /** نام پیشنهادی الگو از روی انتخاب‌ها. */
    public function suggestName(array $selection): string
    {
        $selection = $this->normalizeSelection($selection, validate: false);
        $parts = [];

        foreach (['bodice', 'sleeve', 'lower'] as $group) {
            if ($selection[$group] !== null) {
                $parts[] = GeneratorRegistry::make($selection[$group])->label();
            }
        }

        if ($selection['collar'] !== null) {
            $parts[] = static::COLLAR_LABELS[$selection['collar']];
        }

        return 'ترکیب: '.implode(' + ', $parts ?: ['بدون انتخاب']);
    }

    protected function selectionSummary(array $result): string
    {
        return implode(' + ', array_filter(array_values($result['labels'] ?? [])));
    }

    /** نوع لباس حدسی برای ترکیب (پیراهن یک‌تکه، شلوار یا بلوز). */
    protected function guessGarmentTypeId(array $selection): ?int
    {
        $code = match (true) {
            $selection['lower_kind'] === 'pants' => 'pants',
            $selection['lower_kind'] === 'skirt' => 'dress',
            $selection['bodice'] === 'tshirt' => 'tshirt',
            $selection['bodice'] === 'shirt_classic' => 'shirt',
            $selection['bodice'] === 'blazer' => 'blazer',
            default => 'blouse',
        };

        return GarmentType::query()->where('code', $code)->value('id');
    }

    /** @return array<string, float> */
    protected function workshopSeamAllowances(): array
    {
        return app(WorkshopContext::class)->get()?->defaultSeamAllowances() ?? PatternBuilder::DEFAULT_SEAM_ALLOWANCES;
    }

    /* --- ساخت قطعه (همان قرارداد BaseGenerator، برای یقه‌ها) --------------- */

    /** @param  array<string, mixed>  $attributes */
    protected function piece(array $attributes): array
    {
        $piece = array_merge([
            'code' => 'piece',
            'name' => 'قطعه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => false,
            'mirror' => false,
            'outline' => [],
            'grainline' => null,
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'edge_allowances' => [],
            'meta' => [],
            'sort' => 0,
        ], $attributes);

        $piece['outline'] = Geometry::round($piece['outline']);

        return Geometry::normalizePiece($piece);
    }

    protected function grainline(float $x, float $fromY, float $toY): array
    {
        return [
            'from' => Geometry::point($x, $fromY),
            'to' => Geometry::point($x, $toY),
            'label' => 'راستای پارچه',
        ];
    }

    protected function marker(string $key, string $label, float $fromX, float $y, float $toX, ?float $toY = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'from' => Geometry::point($fromX, $y),
            'to' => Geometry::point($toX, $toY ?? $y),
        ];
    }
}
