<?php

namespace App\Services\Pattern;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Style\StyleModifier;
use App\Services\Pattern\Style\StyleRegistry;
use App\Support\Format;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/**
 * ترکیب مدل‌ها: ساخت یک لباس از یک «دستور» (recipe).
 *
 * دستور دو بخش دارد:
 *
 *   پایه — یا یک «لباس کامل» (یک تولیدکننده از گروه garment) یا «بلوک‌ها»
 *          (بالاتنه + آستین + پایین‌تنه + یقه دوخته‌شده).
 *   سبک‌ها — فهرستی مرتب از سبک‌ها با پارامترهایشان که یکی‌یکی روی قطعه‌های آماده
 *          اجرا می‌شوند (خط یقه ← یقه ← آستین ← چین ← لبه ← جیب ← بست ← جزئیات).
 *
 * هیچ فهرست ثابتی از مدل‌ها و سبک‌ها این‌جا نیست؛ همه چیز از GeneratorRegistry و
 * StyleRegistry خوانده می‌شود، پس هر مدل یا سبک تازه‌ای خودکار در دسترس است.
 *
 * پس از اجرای سبک‌ها همان جورکردن‌های همیشگی دوباره اجرا می‌شود (کمر، حلقه آستین
 * و خط یقه)، تا سبکی که یک درز را عوض کرده لباس را ندوختنی نکند.
 *
 * برای پایهٔ بلوکی کار اصلی این است:
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
    /** بلوک‌های پیش‌فرض هر نقش (فقط برای پیش‌فرض صفحه؛ فهرست کامل از رجیستری می‌آید). */
    public const BODICE_BLOCKS = ['bodice_block', 'tshirt', 'shirt_classic', 'blazer'];

    /** بلوک آستین. */
    public const SLEEVE_BLOCKS = ['sleeve'];

    public const SKIRT_BLOCKS = ['skirt_a_line', 'skirt_pencil'];

    public const PANTS_BLOCKS = ['pants_straight', 'pants_wide_leg'];

    /** گروه‌های رجیستری که می‌توانند نقش «بالاتنه» را بازی کنند. */
    public const BODICE_GROUPS = [
        'bodice', 'top', 'shirt', 'swim', 'evening', 'dress', 'outerwear', 'suit',
        'traditional', 'active', 'underwear', 'sleepwear', 'beach', 'onepiece', 'child', 'garment',
    ];

    /** گروه‌های رجیستری که می‌توانند نقش «پایین‌تنه» را بازی کنند. */
    public const LOWER_GROUPS = ['skirt', 'pants'];

    /**
     * ترتیب اجرای سبک‌ها.
     *
     * اول شکل خود لباس عوض می‌شود (خط یقه، یقه، آستین، گشادی، لبه)، بعد قطعه‌ها
     * بریده می‌شوند (برش)، و آخر چیزهایی که رویشان می‌نشینند (جیب، بست، جزئیات)؛
     * وگرنه مثلاً جیب روی جایی می‌افتد که سبک بعدی آن را می‌بُرد. برش پس از گشادی
     * و لبه می‌آید تا وقتی قطعه دو تکه شد، شکل بیرونی‌اش دیگر تمام شده باشد. هر
     * گروه ناشناخته آخر از همه اجرا می‌شود.
     */
    public const STYLE_ORDER = ['neckline', 'collar', 'sleeve', 'fullness', 'hem', 'seam', 'pocket', 'closure', 'detail'];

    /** قطعه‌های آستین (برای جورکردن دوباره پس از سبک‌ها). */
    public const SLEEVE_PARTS = ['sleeve', 'sleeve_lower', 'sleeve_upper'];

    /** قطعه‌های یقه. */
    public const COLLAR_PARTS = ['collar', 'neckband'];

    /** یقه‌ها ژنراتور جدا ندارند و همین‌جا درفت می‌شوند. */
    public const COLLAR_STYLES = ['band', 'stand', 'shirt', 'flat'];

    /** قطعه‌هایی که همیشه «تنه» حساب می‌شوند (نام‌های تازه با برچسب لبه‌ها شناخته می‌شوند). */
    public const BODICE_PARTS = ['front_bodice', 'back_bodice', 'front_panel', 'back_panel', 'yoke', 'placket'];

    /** قطعه‌هایی که همیشه «پایین‌تنه» حساب می‌شوند. */
    public const LOWER_PARTS = ['skirt_front', 'skirt_back', 'front_leg', 'back_leg', 'skirt_panel', 'skirt_tier', 'peplum', 'godet'];

    /** بیشترین اختلاف کمری که با چین‌دادن جبران می‌شود؛ بیشتر از این درز پهلو راست می‌شود. */
    public const MAX_GATHER = 12.0;

    /** اختلاف کمتر از این مقدار (سانتی‌متر) اصلاً اختلاف حساب نمی‌شود. */
    public const WAIST_TOLERANCE = 0.4;

    /** اختلاف مجاز سرآستین با حلقه آستین. */
    public const CAP_TOLERANCE = 0.6;

    /** بیشترین گودتر شدن مجاز حلقه آستین برای جادادن سرآستین. */
    public const MAX_ARMHOLE_DROP = 6.0;

    /** پارامترهای مدل بالاتنه که به قطعه‌های کنارگذاشته‌شده (آستین و یقه) مربوطند. */
    public const BODICE_IGNORED_PARAMS = [
        'cap_ease', 'cuff', 'cuff_height', 'sleeve_length', 'collar_height',
        'neckband_height', 'neckband_stretch', 'lapel_width', 'lapel_break', 'lining',
    ];

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
        $entry = fn (string $key, array $extra = []) => array_merge([
            'label' => GeneratorRegistry::make($key)->label(),
            'hint' => $this->describe($key),
        ], $extra);

        $bodice = [];

        foreach (static::BODICE_GROUPS as $group) {
            foreach (GeneratorRegistry::group($group) as $key => $ignored) {
                $bodice[$key] = $entry($key, ['kind' => $group]);
            }
        }

        $lower = ['none' => ['label' => 'بدون پایین‌تنه', 'hint' => 'فقط بالاتنه بریده می‌شود.', 'kind' => null]];

        foreach (static::LOWER_GROUPS as $group) {
            foreach (GeneratorRegistry::group($group) as $key => $ignored) {
                $lower[$key] = $entry($key, ['kind' => $group]);
            }
        }

        $sleeve = ['none' => ['label' => 'بدون آستین', 'hint' => 'حلقه آستین با نوار یا سجاف تمام می‌شود.']];

        foreach (GeneratorRegistry::group('sleeve') as $key => $ignored) {
            $sleeve[$key] = $entry($key, ['kind' => 'sleeve']);
        }

        $garment = [];

        foreach (GeneratorRegistry::group('garment') + GeneratorRegistry::group('accessory') as $key => $ignored) {
            $garment[$key] = $entry($key, ['kind' => 'garment']);
        }

        $collar = ['none' => ['label' => 'بدون یقه', 'hint' => 'خط یقه با سجاف تمام می‌شود.']];

        foreach (static::COLLAR_STYLES as $style) {
            $collar[$style] = [
                'label' => static::COLLAR_LABELS[$style],
                'hint' => static::COLLAR_HINTS[$style],
            ];
        }

        return ['garment' => $garment, 'bodice' => $bodice, 'sleeve' => $sleeve, 'lower' => $lower, 'collar' => $collar];
    }

    /**
     * فهرست کامل کارگاه ترکیب: پایه‌ها و سبک‌ها با هر چیزی که صفحه لازم دارد.
     *
     * هیچ‌چیز این‌جا دستی نوشته نشده؛ همه از رجیستری‌ها خوانده می‌شود تا مدل و سبک
     * تازه بدون تغییر کد در صفحه پیدا شود.
     *
     * @return array<string, mixed>
     */
    public function catalogue(): array
    {
        $options = $this->options();
        $styles = [];

        foreach (StyleRegistry::grouped() as $group => $row) {
            $items = [];

            foreach ($row['styles'] as $key => $style) {
                $items[$key] = [
                    'key' => $key,
                    'group' => $group,
                    'label' => $style->label(),
                    'description' => $style->description(),
                    'schema' => $style->paramsSchema(),
                    'defaults' => $this->styleDefaults($style),
                ];
            }

            $styles[$group] = ['label' => $row['label'], 'styles' => $items];
        }

        return [
            'base' => $options,
            'styles' => $styles,
            'groups' => GeneratorRegistry::GROUPS,
            'style_order' => static::STYLE_ORDER,
        ];
    }

    /**
     * برای هر سبک: آیا روی این لباس اجرا می‌شود، و اگر نه چرا (به فارسی).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<string, array{ok: bool, reason: string|null}>
     */
    public function styleAvailability(array $pieces, array $context = []): array
    {
        $out = [];

        foreach (StyleRegistry::all() as $key => $class) {
            $style = StyleRegistry::make($key);
            $ctx = array_merge([
                'measurements' => [],
                'ease' => [],
                'garment' => [],
                'notes' => [],
            ], $context, ['params' => $this->styleDefaults($style)]);

            try {
                $verdict = $style->supports($pieces, $ctx);
            } catch (Throwable $exception) {
                $verdict = 'این سبک روی این لباس بررسی نشد: '.$exception->getMessage();
            }

            $out[$key] = [
                'ok' => $verdict === true,
                'reason' => $verdict === true ? null : (string) $verdict,
            ];
        }

        return $out;
    }

    /** پیش‌فرض پارامترهای یک سبک، از روی خود توضیح پارامترهایش. */
    public function styleDefaults(StyleModifier|string $style): array
    {
        $style = is_string($style) ? StyleRegistry::make($style) : $style;
        $defaults = [];

        foreach ($style->paramsSchema() as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $defaults;
    }

    /**
     * یک خط توضیح فارسی برای یک مدل.
     *
     * تولیدکننده‌ها متد توضیح ندارند، ولی همه‌شان توضیح فارسی بالای کلاس دارند؛
     * جمله اول همان توضیح برداشته می‌شود.
     */
    public function describe(string $key): string
    {
        if (isset(static::BLOCK_HINTS[$key])) {
            return static::BLOCK_HINTS[$key];
        }

        $class = GeneratorRegistry::all()[$key] ?? null;

        if ($class === null) {
            return '';
        }

        $doc = (new ReflectionClass($class))->getDocComment();

        if ($doc === false) {
            return '';
        }

        $label = GeneratorRegistry::make($key)->label();
        $lines = [];

        // نکته: پرچم u لازم است؛ وگرنه \R وسط حرف‌های فارسی هم می‌بُرد
        foreach (preg_split('/\R/u', $doc) ?: [] as $line) {
            $line = trim(preg_replace('#^\s*/?\*+/?#u', '', $line) ?? '');

            if ($line !== '' && ! str_starts_with($line, '@')) {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            return '';
        }

        // خط اول معمولاً همان نام مدل است؛ در آن صورت جمله بعدی توضیح است
        if (rtrim($lines[0], '.') === rtrim($label, '.')) {
            array_shift($lines);
        }

        $text = trim(implode(' ', $lines));
        $sentence = preg_split('/(?<=[.؟!])\s/u', $text)[0] ?? $text;

        return mb_strlen($sentence) > 120 ? mb_substr($sentence, 0, 117).'…' : $sentence;
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
        $selection = isset($selection['kind']) || isset($selection['garment']) || isset($selection['base'])
            ? $this->normalizeRecipe($selection, validate: false)['base']
            : $this->normalizeSelection($selection, validate: false);

        $out = [];

        foreach (['garment', 'bodice', 'sleeve', 'lower'] as $group) {
            $key = $selection[$group] ?? null;

            if ($key === null) {
                continue;
            }

            $generator = GeneratorRegistry::make($key);
            $schema = $generator->paramsSchema();

            if ($group === 'bodice') {
                // پارامترهای آستین و یقهٔ خود مدل به کار نمی‌آید؛ آن‌ها را جداگانه انتخاب کرده‌ایم
                $schema = array_diff_key($schema, array_flip(static::BODICE_IGNORED_PARAMS));
            }

            $out[$group] = [
                'key' => $key,
                'label' => $generator->label(),
                'schema' => $schema,
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
            if ($this->playsRole($lower, ['pants'])) {
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

            if (! $this->playsRole($bodice, static::BODICE_GROUPS)) {
                throw new InvalidArgumentException("«{$bodice}» بالاتنه نیست؛ یکی از بالاتنه‌های فهرست را انتخاب کنید.");
            }

            if ($sleeve !== null && ! $this->playsRole($sleeve, ['sleeve'])) {
                throw new InvalidArgumentException("آستین «{$sleeve}» شناخته نشد.");
            }

            if ($skirt !== null && ! $this->playsRole($skirt, ['skirt'])) {
                throw new InvalidArgumentException("دامن «{$skirt}» شناخته نشد.");
            }

            if ($pants !== null && ! $this->playsRole($pants, ['pants'])) {
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

    /** آیا این مدل می‌تواند این نقش را بازی کند؟ */
    protected function playsRole(string $key, array $groups): bool
    {
        return GeneratorRegistry::has($key) && in_array(GeneratorRegistry::groupOf($key), $groups, true);
    }

    /**
     * «دستور» ترکیب را مرتب و بررسی می‌کند.
     *
     * ورودی می‌تواند سه شکل داشته باشد و هر سه یک خروجی می‌دهند:
     *   ['base' => [...], 'styles' => [...]]        شکل کامل
     *   ['garment' => 'dress', 'styles' => [...]]   لباس کامل
     *   ['bodice' => ..., 'sleeve' => ...]          همان انتخاب قدیمی چهار بخشی
     *
     * سبک‌ها هم سه شکل می‌پذیرند: فهرست کلید، فهرست ['key' => .., 'params' => ..]
     * یا نگاشت کلید ⇒ پارامترها.
     *
     * @return array{base: array<string, mixed>, styles: array<int, array{key: string, group: string, params: array<string, mixed>}>}
     */
    public function normalizeRecipe(array $input, array $params = [], bool $validate = true): array
    {
        $base = is_array($input['base'] ?? null) ? $input['base'] : $input;
        $styles = $this->normalizeStyles($input['styles'] ?? ($base['styles'] ?? []), $params['styles'] ?? []);

        $garment = is_string($base['garment'] ?? null) && $base['garment'] !== '' && $base['garment'] !== 'none'
            ? $base['garment']
            : null;

        $kind = (string) ($base['kind'] ?? $input['base_kind'] ?? ($garment !== null ? 'garment' : 'blocks'));

        if ($kind === 'garment') {
            if ($validate && $garment === null) {
                throw new InvalidArgumentException('برای «یک لباس کامل» باید یک مدل انتخاب کنید.');
            }

            if ($validate && ! GeneratorRegistry::has($garment)) {
                throw new InvalidArgumentException("مدل «{$garment}» شناخته نشد.");
            }

            return [
                'base' => [
                    'kind' => 'garment',
                    'garment' => $garment,
                    'bodice' => null,
                    'sleeve' => null,
                    'lower' => null,
                    'lower_kind' => null,
                    'collar' => null,
                ],
                'styles' => $styles,
            ];
        }

        return [
            'base' => array_merge(
                ['kind' => 'blocks', 'garment' => null],
                $this->normalizeSelection($base, $validate),
            ),
            'styles' => $styles,
        ];
    }

    /**
     * فهرست سبک‌ها را به شکل یکسان درمی‌آورد و به ترتیب اجرا مرتب می‌کند.
     *
     * @return array<int, array{key: string, group: string, params: array<string, mixed>}>
     */
    public function normalizeStyles(mixed $styles, array $extraParams = []): array
    {
        if (! is_array($styles)) {
            return [];
        }

        $out = [];

        foreach ($styles as $index => $value) {
            $key = null;
            $params = [];

            $hand = 'both';

            if (is_string($value)) {
                $key = $value;
            } elseif (is_array($value) && isset($value['key']) && is_string($value['key'])) {
                $key = $value['key'];
                $params = is_array($value['params'] ?? null) ? $value['params'] : [];
                $hand = (string) ($value['side'] ?? 'both');
            } elseif (is_string($index)) {
                $key = $index;
                $params = is_array($value) ? $value : [];
                $hand = (string) ($value['side'] ?? 'both');
            }

            $hand = in_array($hand, ['left', 'right'], true) ? $hand : 'both';

            if ($key === null || $key === '' || $key === 'none' || isset($out[$key])) {
                continue;
            }

            $out[$key] = [
                'key' => $key,
                'side' => $hand,
                'group' => StyleRegistry::has($key) ? StyleRegistry::make($key)::group() : 'unknown',
                'params' => $this->cleanStyleParams(array_merge(
                    is_array($extraParams[$key] ?? null) ? $extraParams[$key] : [],
                    $params,
                )),
            ];
        }

        $out = array_values($out);

        usort($out, function (array $a, array $b) {
            $rank = fn (string $group) => ($position = array_search($group, static::STYLE_ORDER, true)) === false
                ? count(static::STYLE_ORDER)
                : $position;

            return $rank($a['group']) <=> $rank($b['group']);
        });

        return $out;
    }

    /** پارامترهای سبک: مقدارهای خالی کنار می‌رود و عددها عدد می‌شوند. */
    protected function cleanStyleParams(array $params): array
    {
        $out = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $out[(string) $key] = is_numeric($value) ? (float) $value : $value;
        }

        return $out;
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
        $recipe = $this->normalizeRecipe($selection, $params);
        $measurements = Measurements::complete($measurements);
        $seamMap = $seamAllowances === [] ? PatternBuilder::DEFAULT_SEAM_ALLOWANCES : $seamAllowances;

        [$groups, $notes, $metrics, $labels] = $recipe['base']['kind'] === 'garment'
            ? $this->garmentBase($recipe['base'], $measurements, $ease, $params)
            : $this->blockBase($recipe['base'], $measurements, $ease, $params);

        $pieces = $this->flatten($groups);

        // سبک‌ها یکی‌یکی روی همین قطعه‌ها اجرا می‌شوند
        if ($recipe['styles'] !== []) {
            [$pieces, $styleNotes, $metrics['styles']] = $this->applyStyles($pieces, $recipe['styles'], [
                'measurements' => $measurements,
                'ease' => $ease,
                'garment' => $recipe['base'] + ['labels' => $labels],
                'notes' => $notes,
            ]);

            $notes = array_merge($notes, $styleNotes);

            // هر سبکی می‌تواند یک درز را عوض کرده باشد؛ دوباره همه را جور می‌کنیم
            [$pieces, $fixNotes, $fixMetrics] = $this->reconcile($pieces, $params, $labels['lower'] ?? 'پایین‌تنه');
            $notes = array_merge($notes, $fixNotes);
            $metrics = array_merge($metrics, $fixMetrics);
        }

        $pieces = $this->finalize($pieces, $seamMap);

        $metrics['pieces'] = count($pieces);
        $metrics['cut_pieces'] = (int) collect($pieces)->sum('cut_quantity');

        return [
            'recipe' => $recipe,
            'selection' => $recipe['base'],
            'labels' => $labels,
            'pieces' => $pieces,
            'notes' => $notes,
            'metrics' => $metrics,
            'measurements' => $measurements,
            'seam_allowances' => $seamMap,
            'sewing_relations' => $this->relations($pieces),
            'name' => $this->suggestName($recipe),
        ];
    }

    /**
     * پایه «یک لباس کامل»: قطعه‌های یک تولیدکننده، دسته‌بندی‌شده.
     *
     * @return array{0: array<string, array<int, array<string, mixed>>>, 1: array<int, array<string, string>>, 2: array<string, mixed>, 3: array<string, string|null>}
     */
    protected function garmentBase(array $base, array $measurements, array $ease, array $params): array
    {
        $key = $base['garment'];
        $generator = GeneratorRegistry::make($key);
        $pieces = $this->blockPieces('garment', $key, $measurements, $ease, $params);

        $groups = [];

        foreach ($pieces as $piece) {
            $groups[$this->groupOfPart($piece)][] = $piece;
        }

        $notes = [$this->note('info', 'پایه ترکیب مدل «'.$generator->label().'» است؛ '
            .Format::number(count($pieces)).' قطعه از خود مدل آمد و سبک‌ها روی همین‌ها اجرا می‌شود.')];

        return [
            $groups,
            $notes,
            ['garment' => ['key' => $key, 'pieces' => count($pieces)]],
            ['garment' => $generator->label(), 'bodice' => null, 'lower' => null, 'sleeve' => null, 'collar' => null],
        ];
    }

    /**
     * پایه «از بلوک بساز»: همان ترکیب بالاتنه + پایین‌تنه + آستین + یقه.
     *
     * @return array{0: array<string, array<int, array<string, mixed>>>, 1: array<int, array<string, string>>, 2: array<string, mixed>, 3: array<string, string|null>}
     */
    protected function blockBase(array $selection, array $measurements, array $ease, array $params): array
    {
        $notes = [];
        $metrics = [];

        // ۱) بالاتنه — فقط قطعه‌های تنه؛ آستین و یقه خودِ مدل کنار گذاشته می‌شود
        $bodiceLabel = GeneratorRegistry::make($selection['bodice'])->label();
        $bodice = $this->keepForBodice(
            $this->blockPieces('bodice', $selection['bodice'], $measurements, $ease, $params),
            $selection['lower'] !== null,
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
                ['waistband'],
                $droppedLower,
                exclude: true,
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

        return [
            [
                'bodice' => $bodice,
                'lower' => $lower,
                'sleeve' => $sleeve,
                'collar' => $collar,
            ],
            $notes,
            $metrics,
            [
                'garment' => null,
                'bodice' => $bodiceLabel,
                'lower' => $lowerLabel,
                'sleeve' => $selection['sleeve'] ? GeneratorRegistry::make($selection['sleeve'])->label() : null,
                'collar' => $selection['collar'] ? static::COLLAR_LABELS[$selection['collar']] : null,
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  اجرای سبک‌ها
     * ------------------------------------------------------------------- */

    /**
     * سبک‌ها را به ترتیب روی قطعه‌ها اجرا می‌کند.
     *
     * قانون‌ها:
     *   • سبکی که خودش می‌گوید روی این لباس نمی‌نشیند، با همان دلیل فارسی رد می‌شود
     *     و بقیه سبک‌ها ادامه پیدا می‌کنند.
     *   • اگر سبکی خطا داد یا خروجی بی‌ریخت داد، قطعه‌های پیش از آن دست‌نخورده
     *     می‌مانند؛ یک سبک خراب نباید کل لباس را خراب کند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, array{key: string, group: string, params: array<string, mixed>}>  $styles
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>, 2: array<int, array<string, mixed>>}
     */
    /**
     * اجزایی که چپ و راست دارند.
     *
     * یقه، کمربند، مچ‌بند و نوارها یک تکه‌اند یا قرینه‌شان معنا ندارد، پس در
     * جداکردن چپ و راست دست نمی‌خورند.
     */
    protected const HANDED_PARTS = [
        'front_bodice', 'back_bodice', 'front_panel', 'back_panel',
        'front_leg', 'back_leg', 'skirt_front', 'skirt_back', 'sleeve', 'lining',
    ];

    protected function applyStyles(array $pieces, array $styles, array $context): array
    {
        $notes = [];
        $report = [];

        foreach ($styles as $entry) {
            $key = $entry['key'];

            if (! StyleRegistry::has($key)) {
                $notes[] = $this->note('warning', 'سبک «'.$key.'» دیگر در فهرست سبک‌ها نیست، پس روی این لباس اجرا نشد.');
                $report[] = ['key' => $key, 'label' => $key, 'group' => $entry['group'], 'status' => 'missing'];

                continue;
            }

            $style = StyleRegistry::make($key);
            $params = array_merge($this->styleDefaults($style), $entry['params']);
            $ctx = array_merge($context, ['params' => $params, 'notes' => $notes]);
            $row = [
                'key' => $key,
                'label' => $style->label(),
                'group' => $style::group(),
                'params' => $params,
            ];

            try {
                $verdict = $style->supports($pieces, $ctx);
            } catch (Throwable $exception) {
                $verdict = $exception->getMessage();
            }

            if ($verdict !== true) {
                $notes[] = $this->note('warning', 'سبک «'.$style->label().'» روی این لباس اجرا نشد: '.$verdict);
                $report[] = $row + ['status' => 'skipped', 'reason' => (string) $verdict];

                continue;
            }

            $hand = (string) ($entry['side'] ?? 'both');

            if ($hand !== 'both') {
                $split = $this->splitHands($pieces, $hand);

                if ($split === null) {
                    $notes[] = $this->note('warning', 'سبک «'.$style->label().'» فقط برای یک سمت خواسته شده بود، '
                        .'ولی تنه این لباس روی تای پارچه بریده می‌شود و چپ و راستش یک قطعه است. '
                        .'اول یک بست جلو (دکمه یا زیپ) بگذارید تا مرکز جلو باز شود.');
                    $report[] = $row + ['status' => 'skipped', 'reason' => 'تنه روی تای پارچه است و چپ و راست ندارد.'];

                    continue;
                }

                [$pieces, $ctx] = [$split['pieces'], array_merge($ctx, ['hand' => $hand])];
            }

            try {
                $result = $hand === 'both'
                    ? $style->apply($pieces, $ctx)
                    : $this->applyToOneHand($style, $pieces, $ctx, $hand);
            } catch (Throwable $exception) {
                $notes[] = $this->note('warning', 'سبک «'.$style->label().'» نیمه‌کاره ماند و کنار گذاشته شد: '
                    .$exception->getMessage());
                $report[] = $row + ['status' => 'failed', 'reason' => $exception->getMessage()];

                continue;
            }

            $next = is_array($result['pieces'] ?? null) ? array_values($result['pieces']) : [];

            if (! $this->piecesAreSane($next)) {
                $notes[] = $this->note('warning', 'سبک «'.$style->label()
                    .'» قطعه‌های سالمی نداد، پس اجرا نشد و لباس بدون آن ساخته شد.');
                $report[] = $row + ['status' => 'invalid'];

                continue;
            }

            $before = count($pieces);
            $pieces = $next;

            foreach ($result['notes'] ?? [] as $note) {
                $notes[] = $this->styleNote($note);
            }

            $report[] = $row + [
                'status' => 'applied',
                'side' => $hand,
                'added' => max(0, count($pieces) - $before),
                'meta' => $result['meta'] ?? [],
            ];
        }

        return [$pieces, $notes, $report];
    }

    /**
     * جدا کردن چپ و راست لباس.
     *
     * تا وقتی همه‌چیز قرینه است، تنه یک بار بریده و آینه می‌شود. برای اینکه یک
     * سمت با سمت دیگر فرق کند، باید همان قطعه دو قطعه مستقل شود: یکی چپ و یکی
     * راست، هرکدام یک بار بریده و بدون آینه.
     *
     * قطعه‌ای که روی تای پارچه بریده می‌شود اصلاً چپ و راست ندارد — یک تکه است
     * که وسطش روی تاست. آنجا null برمی‌گردانیم تا کاربر بداند اول باید مرکز جلو
     * را با یک بست باز کند؛ این همان چیزی است که خیاط هم می‌گوید.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{pieces: array<int, array<string, mixed>>}|null
     */
    protected function splitHands(array $pieces, string $hand): ?array
    {
        $out = [];
        $splitAny = false;

        foreach ($pieces as $piece) {
            $already = $piece['meta']['hand'] ?? null;

            if ($already !== null) {
                $out[] = $piece;
                $splitAny = true;

                continue;
            }

            // قطعه‌ای که روی تای پارچه بریده می‌شود یک تکه است و چپ و راستش از هم
            // جدا نمی‌شود؛ دست‌نخورده رد می‌شود. اگر هیچ قطعه‌ای جدا نشد، همین را
            // به کاربر می‌گوییم.
            if (! $this->hasHands($piece) || ! empty($piece['on_fold'])) {
                $out[] = $piece;

                continue;
            }

            foreach (['right' => 'راست', 'left' => 'چپ'] as $key => $label) {
                $copy = $piece;
                $copy['code'] = $piece['code'].'-'.$key;
                $copy['name'] = $piece['name'].' ('.$label.')';
                $copy['cut_quantity'] = 1;
                $copy['mirror'] = false;
                $copy['meta']['hand'] = $key;
                $copy['meta']['hand_of'] = $piece['code'];
                $out[] = $copy;
            }

            $splitAny = true;
        }

        return $splitAny ? ['pieces' => $out] : null;
    }

    /** آیا این قطعه چپ و راست دارد؟ (قطعه‌ای که دو بار قرینه بریده می‌شود) */
    protected function hasHands(array $piece): bool
    {
        return ! empty($piece['mirror'])
            && (int) ($piece['cut_quantity'] ?? 1) >= 2
            && in_array((string) ($piece['meta']['part'] ?? ''), static::HANDED_PARTS, true);
    }

    /**
     * اجرای یک سبک تنها روی یک سمت لباس.
     *
     * سبک را با مجموعه‌ای صدا می‌زنیم که فقط قطعه‌های همان سمت (به‌علاوه قطعه‌های
     * بی‌سمت مثل یقه و کمربند) در آن است، پس خودِ سبک لازم نیست چیزی از چپ و راست
     * بداند. هر قطعه تازه‌ای که ساخت هم مهر همان سمت را می‌گیرد.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{pieces: array<int, array<string, mixed>>, notes?: array, meta?: array}
     */
    protected function applyToOneHand(StyleModifier $style, array $pieces, array $context, string $hand): array
    {
        $mine = [];
        $others = [];

        foreach ($pieces as $piece) {
            $on = $piece['meta']['hand'] ?? null;

            if ($on === null || $on === $hand) {
                $mine[] = $piece;
            } else {
                $others[] = $piece;
            }
        }

        $result = $style->apply($mine, $context);
        $before = array_column($mine, 'code');
        $label = $hand === 'left' ? 'چپ' : 'راست';
        $next = [];

        foreach ($result['pieces'] ?? [] as $piece) {
            if (! in_array($piece['code'] ?? '', $before, true) && ($piece['meta']['hand'] ?? null) === null) {
                // قطعه‌ای که همین سبک تازه ساخته (جیب، پاتلت، لت) مهر همین سمت را می‌گیرد
                $piece['code'] = $piece['code'].'-'.$hand;
                $piece['name'] = ($piece['name'] ?? '').' ('.$label.')';
                $piece['meta']['hand'] = $hand;
            }

            $next[] = $piece;
        }

        return array_merge($result, [
            'pieces' => $this->mergeIdenticalHands(array_merge($next, $others)),
        ]);
    }

    /**
     * برگرداندن سمت‌هایی که در عمل فرقی نکردند.
     *
     * برای اجرای یک سبک روی یک سمت، همه قطعه‌های چپ‌وراست‌دار جدا می‌شوند — ولی
     * سبک شاید فقط به تنه دست زده باشد و آستین دست‌نخورده مانده باشد. آستینی که
     * چپ و راستش مو نمی‌زند، دوباره یک قطعه قرینه می‌شود تا نقشه برش و فهرست
     * قطعه‌ها بی‌جهت شلوغ نشود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function mergeIdenticalHands(array $pieces): array
    {
        $byOrigin = [];

        foreach ($pieces as $index => $piece) {
            $origin = $piece['meta']['hand_of'] ?? null;

            if ($origin !== null) {
                $byOrigin[$origin][$piece['meta']['hand']] = ['index' => $index, 'piece' => $piece];
            }
        }

        $drop = [];
        $restore = [];

        foreach ($byOrigin as $origin => $hands) {
            if (count($hands) !== 2) {
                continue;
            }

            $left = $hands['left']['piece'];
            $right = $hands['right']['piece'];

            if ($this->handFingerprint($left) !== $this->handFingerprint($right)) {
                continue;
            }

            $merged = $right;
            $merged['code'] = $origin;
            $merged['name'] = preg_replace('/ \((?:چپ|راست)\)$/u', '', (string) $merged['name']);
            $merged['cut_quantity'] = 2;
            $merged['mirror'] = true;
            unset($merged['meta']['hand'], $merged['meta']['hand_of']);

            $restore[$hands['right']['index']] = $merged;
            $drop[] = $hands['left']['index'];
        }

        $out = [];

        foreach ($pieces as $index => $piece) {
            if (in_array($index, $drop, true)) {
                continue;
            }

            $out[] = $restore[$index] ?? $piece;
        }

        return $out;
    }

    /** اثر انگشت یک قطعه برای مقایسه دو سمت. */
    protected function handFingerprint(array $piece): string
    {
        return json_encode([
            $piece['outline'] ?? [],
            $piece['darts'] ?? [],
            $piece['notches'] ?? [],
            $piece['drills'] ?? [],
            $piece['markers'] ?? [],
            $piece['pleats'] ?? [],
            array_diff_key($piece['meta'] ?? [], array_flip(['hand', 'hand_of'])),
        ]);
    }

    /** یادداشت سبک‌ها گاهی رشته است و گاهی ['type' => .., 'text' => ..]. */
    protected function styleNote(mixed $note): array
    {
        if (is_array($note) && isset($note['text'])) {
            return $this->note((string) ($note['type'] ?? 'info'), (string) $note['text']);
        }

        return $this->note('info', is_scalar($note) ? (string) $note : '');
    }

    /** حداقل سلامت خروجی یک سبک: قطعه‌ها باید مسیر بسته و قابل‌بریدن داشته باشند. */
    protected function piecesAreSane(array $pieces): bool
    {
        if ($pieces === []) {
            return false;
        }

        foreach ($pieces as $piece) {
            if (! is_array($piece) || count($piece['outline'] ?? []) < 3) {
                return false;
            }

            foreach ($piece['outline'] as $point) {
                if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                    return false;
                }
            }

            if (Geometry::width($piece['outline']) < 0.5 || Geometry::height($piece['outline']) < 0.5) {
                return false;
            }
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     |  جورکردن دوباره پس از سبک‌ها
     * ------------------------------------------------------------------- */

    /**
     * همان سه جورکردن همیشگی، این بار روی قطعه‌های سبک‌خورده: کمر، حلقه آستین و
     * خط یقه. سبکی که یک درز را عوض کرده باید این‌جا جبران شود، نه سر چرخ خیاطی.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>, 2: array<string, mixed>}
     */
    protected function reconcile(array $pieces, array $params, string $lowerLabel = 'پایین‌تنه'): array
    {
        $notes = [];
        $metrics = [];

        [$pieces, $waistNotes, $waistMetrics] = $this->reconcileWaistInPlace($pieces, $params, $lowerLabel);
        $notes = array_merge($notes, $waistNotes);

        if ($waistMetrics !== null) {
            $metrics['waist_after_styles'] = $waistMetrics;
        }

        [$pieces, $armNotes, $armMetrics] = $this->reconcileArmhole($pieces);
        $notes = array_merge($notes, $armNotes);

        if ($armMetrics !== null) {
            $metrics['sleeve_after_styles'] = $armMetrics;
        }

        [$pieces, $collarNotes, $collarMetrics] = $this->reconcileCollar($pieces, $params);
        $notes = array_merge($notes, $collarNotes);

        if ($collarMetrics !== null) {
            $metrics['collar_after_styles'] = $collarMetrics;
        }

        return [$pieces, $notes, $metrics];
    }

    /**
     * کمر بالاتنه و پایین‌تنه را دوباره هم‌اندازه می‌کند (اگر هنوز درز کمری هست).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>, 2: array<string, mixed>|null}
     */
    protected function reconcileWaistInPlace(array $pieces, array $params, string $lowerLabel): array
    {
        $top = $this->indexesOfGroup($pieces, 'bodice', 'waist');
        $bottom = $this->indexesOfGroup($pieces, 'lower', 'waist');

        if ($top === [] || $bottom === []) {
            return [$pieces, [], null];
        }

        $bodice = $this->pick($pieces, $top);
        $lower = $this->pick($pieces, $bottom);
        $difference = round($this->waistGirth($lower) - $this->waistGirth($bodice), 2);

        if (abs($difference) <= static::WAIST_TOLERANCE) {
            return [$pieces, [], null];
        }

        [$bodice, $lower, $metrics, $notes] = $this->reconcileWaist($bodice, $lower, $lowerLabel, $params);

        $pieces = $this->put($pieces, $top, $bodice);
        $pieces = $this->put($pieces, $bottom, $lower);

        array_unshift($notes, $this->note('info', 'سبک‌ها خط کمر را '.Format::cm(abs($difference))
            .' جابه‌جا کردند، پس کمر دوباره جور شد.'));

        return [$pieces, $notes, $metrics];
    }

    /**
     * سرآستین و حلقه آستین را دوباره اندازه می‌گیرد.
     *
     * چین و پیلی ثبت‌شده روی سرآستین از طول کم می‌شود، چون آستین پفی عمداً بلندتر
     * بریده می‌شود. اگر باز هم سرآستین بلندتر بود، حلقه تا جای ممکن گودتر می‌شود و
     * باقیمانده گزارش.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>, 2: array<string, mixed>|null}
     */
    protected function reconcileArmhole(array $pieces): array
    {
        $body = $this->indexesOfGroup($pieces, 'bodice', 'armhole');
        $sleeves = $this->indexesOfGroup($pieces, 'sleeve', 'armhole');

        if ($body === [] || $sleeves === []) {
            return [$pieces, [], null];
        }

        $armhole = $this->armholeLength($this->pick($pieces, $body));
        $cap = 0.0;

        foreach ($sleeves as $index) {
            $cap += $this->sewnCapLength($pieces[$index]);
        }

        if ($armhole <= 0 || $cap <= 0) {
            return [$pieces, [], null];
        }

        $difference = round($cap - $armhole, 2);
        $dropped = 0.0;

        // سرآستین خیلی بلندتر: حلقه را گودتر می‌کنیم تا در آن جا شود
        while ($difference > static::MAX_GATHER && $dropped < static::MAX_ARMHOLE_DROP) {
            $bodice = $this->pick($pieces, $body);
            $step = min(($difference - static::MAX_GATHER) / max(1, count($body)), static::MAX_ARMHOLE_DROP - $dropped);
            [$bodice, $applied] = $this->deepenArmhole($bodice, $step);

            if ($applied < 0.05) {
                break;
            }

            $pieces = $this->put($pieces, $body, $bodice);
            $dropped = round($dropped + $applied, 2);
            $armhole = $this->armholeLength($this->pick($pieces, $body));
            $difference = round($cap - $armhole, 2);
        }

        $metrics = [
            'armhole' => $armhole,
            'cap' => round($cap, 2),
            'difference' => $difference,
            'armhole_drop' => $dropped,
            'status' => abs($difference) <= static::CAP_TOLERANCE ? 'fitted' : 'eased',
        ];

        $notes = [];

        if ($dropped > 0.05) {
            $notes[] = $this->note('tip', 'پس از سبک‌ها حلقه آستین '.Format::cm($dropped)
                .' گودتر شد تا سرآستین در آن بنشیند.');
        }

        if ($difference < -static::CAP_TOLERANCE) {
            $notes[] = $this->note('warning', 'سرآستین '.Format::cm(abs($difference))
                .' از حلقه آستین کوتاه‌تر است؛ آستین کشیده می‌شود. دور بازو یا گودی حلقه را بازبینی کنید.');
        } elseif ($difference > static::CAP_TOLERANCE) {
            $notes[] = $this->note('info', 'سرآستین '.Format::cm($difference)
                .' بلندتر از حلقه آستین است؛ این مقدار روی سرشانه چین می‌خورد.');
        }

        return [$pieces, $notes, $metrics];
    }

    /** طول دوختنی سرآستین: طول لبه‌های حلقه منهای چین و پیلی ثبت‌شده روی همان‌ها. */
    protected function sewnCapLength(array $piece): float
    {
        $total = 0.0;

        foreach ($this->edgesWithTag($piece, 'armhole') as $edge) {
            $total += $this->seamLength($piece, $edge);
        }

        return round($total, 2);
    }

    /**
     * اگر سبکی خط یقه را عوض کرده باشد، یقه دوخته‌شده دوباره به اندازه خط یقه تازه
     * بریده می‌شود.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>, 2: array<string, mixed>|null}
     */
    protected function reconcileCollar(array $pieces, array $params): array
    {
        $collarIndex = null;

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? null) === 'collar' && isset($piece['meta']['collar_style'])) {
                $collarIndex = $index;
                break;
            }
        }

        if ($collarIndex === null) {
            return [$pieces, [], null];
        }

        $style = (string) $pieces[$collarIndex]['meta']['collar_style'];

        if (! isset(static::COLLAR_DEFAULTS[$style])) {
            return [$pieces, [], null];
        }

        $body = $this->indexesOfGroup($pieces, 'bodice', 'neck');
        $neck = $this->necklineLength($this->pick($pieces, $body));
        $before = (float) ($pieces[$collarIndex]['meta']['neckline_length'] ?? 0);

        if ($neck <= 0 || abs($neck - $before) <= 0.4) {
            return [$pieces, [], null];
        }

        [$collar, $metrics, $notes] = $this->fitCollar($style, $this->pick($pieces, $body), $params);

        if ($collar === []) {
            return [$pieces, $notes, $metrics];
        }

        $collar[0]['code'] = $pieces[$collarIndex]['code'];
        $collar[0]['meta']['group'] = $pieces[$collarIndex]['meta']['group'] ?? 'collar';
        $pieces[$collarIndex] = $collar[0];

        array_unshift($notes, $this->note('info', 'سبک‌ها خط یقه را از '.Format::cm($before).' به '
            .Format::cm($neck).' رساندند، پس '.static::COLLAR_LABELS[$style].' دوباره بریده شد.'));

        return [array_values($pieces), $notes, $metrics];
    }

    /* --- کمک‌کننده‌های کار با فهرست تخت قطعه‌ها ---------------------------- */

    /**
     * شماره قطعه‌هایی که این «نقش» را دارند (و در صورت خواست، این لبه را).
     *
     * @return array<int, int>
     */
    protected function indexesOfGroup(array $pieces, string $group, ?string $tag = null): array
    {
        $out = [];

        foreach ($pieces as $index => $piece) {
            if (! $this->cuts($piece)) {
                continue;
            }

            if (($piece['meta']['group'] ?? $this->groupOfPart($piece)) !== $group) {
                continue;
            }

            if ($tag !== null && $this->edgeWithTag($piece, $tag) === null) {
                continue;
            }

            $out[] = (int) $index;
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    protected function pick(array $pieces, array $indexes): array
    {
        return array_values(array_map(fn (int $index) => $pieces[$index], $indexes));
    }

    /** قطعه‌های تغییرکرده را سر جای خودشان برمی‌گرداند. */
    protected function put(array $pieces, array $indexes, array $updated): array
    {
        foreach (array_values($indexes) as $position => $index) {
            if (isset($updated[$position])) {
                $pieces[$index] = $updated[$position];
            }
        }

        return $pieces;
    }

    /**
     * گروه‌های ترکیب را به یک فهرست تخت تبدیل می‌کند و روی هر قطعه گروهش را می‌نویسد.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function flatten(array $groups): array
    {
        $order = ['bodice', 'lower', 'sleeve', 'collar'];
        $pieces = [];

        foreach (array_unique(array_merge($order, array_keys($groups))) as $group) {
            foreach ($groups[$group] ?? [] as $piece) {
                $piece['meta']['group'] = $group;
                $pieces[] = $piece;
            }
        }

        return $pieces;
    }

    /**
     * گروه یک قطعه از روی نقش و برچسب لبه‌هایش.
     *
     * نام قطعه‌ها بسته نیست (هر مدل تازه‌ای می‌تواند نام تازه بیاورد)، پس اگر نام
     * ناشناخته بود از روی لبه‌ها حدس زده می‌شود: هرچه حلقه آستین یا سرشانه و یقه
     * دارد تنه است، هرچه دم دارد و حلقه ندارد پایین‌تنه.
     */
    protected function groupOfPart(array $piece): string
    {
        $part = (string) ($piece['meta']['part'] ?? '');
        $tags = array_values($piece['meta']['edges'] ?? []);
        $has = fn (string $tag) => in_array($tag, $tags, true);

        return match (true) {
            in_array($part, static::SLEEVE_PARTS, true), str_starts_with($part, 'sleeve') => 'sleeve',
            in_array($part, static::COLLAR_PARTS, true) => 'collar',
            in_array($part, static::BODICE_PARTS, true), $part === 'lapel' => 'bodice',
            in_array($part, static::LOWER_PARTS, true), $part === 'waistband' => 'lower',
            str_contains($part, 'skirt'), str_contains($part, '_leg') => 'lower',
            $part !== '' && ! in_array($part, ['facing', 'lining', 'pocket', 'cuff', 'tie', 'binding'], true)
                && ($has('armhole') || ($has('shoulder') && $has('neck'))) => 'bodice',
            $part === '' && ($has('armhole') || ($has('shoulder') && $has('neck'))) => 'bodice',
            $has('hem') && $has('waist') && ! $has('armhole') && ! $has('neck') => 'lower',
            default => 'detail',
        };
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
                        'recipe' => $result['recipe'],
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
            $edge = $this->cuts($piece) ? $this->edgeWithTag($piece, 'waist') : null;

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
            if (! $this->cuts($piece)) {
                continue;
            }

            // اینجا «یک حلقه» شمرده می‌شود نه دور هر دو حلقه؛ پس وقتی قطعه‌ای برای
            // نامتقارنی به چپ و راست شکسته شده، تنها یک سمتش حساب می‌شود.
            if (($piece['meta']['hand'] ?? null) === 'right') {
                continue;
            }

            foreach ($this->edgesWithTag($piece, 'armhole') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /**
     * آیا این قطعه در اندازه‌گیری درزهای بیرونی حساب می‌شود؟
     *
     * آستر و لایی و رودوزی روی هم می‌خوابند و اگر شمرده شوند دور کمر و حلقه آستین
     * دو برابر درمی‌آید.
     */
    protected function cuts(array $piece): bool
    {
        return ($piece['layer'] ?? 'outer') === 'outer'
            && ! in_array($piece['meta']['part'] ?? null, ['facing', 'lining', 'binding'], true);
    }

    /** نصف خط یقه (جلو + پشت روی تای پارچه) — همان اندازه‌ای که نیم‌یقه باید داشته باشد. */
    public function necklineLength(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if (! $this->cuts($piece)) {
                continue;
            }

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
            // فقط قطعه‌های بیرونی در دور کمر شمرده می‌شوند، پس سهم اصلاح هم باید
            // از همان‌ها گرفته شود؛ وگرنه نیمی از اصلاح روی آستر می‌نشیند و دور
            // کمرِ اندازه‌گیری‌شده کوچک نمی‌شود.
            if ($this->cuts($piece) && $this->edgeWithTag($piece, 'waist') !== null) {
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

        // نقطه‌ای که روی نقطه پیشین می‌افتد (وقتی گوشه قطعه دقیقاً روی خط کمر است)
        // دور ریخته می‌شود؛ وگرنه لبه‌ای به طول صفر می‌ماند و اندازه‌گیری کمر را صفر می‌کند.
        $push = function (array $point, string $tag) use (&$points, &$arrivals) {
            $last = $points === [] ? null : $points[count($points) - 1];

            if ($last !== null && Geometry::distance($last, $point) < 0.05) {
                return;
            }

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

        // مسیر از «رسیدن به نقطه دوم» شروع شده؛ یک خانه می‌چرخانیمش تا قطعه از همان
        // نقطه‌ای شروع شود که پیش از برش شروع می‌شد (مثلاً سرِ خط یقه روی مرکز جلو).
        // سبک‌هایی که لبه‌ها را از سر قطعه دنبال می‌کنند به همین ترتیب تکیه دارند.
        array_unshift($points, array_pop($points));
        array_unshift($arrivals, array_pop($arrivals));

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
            $kept = $this->keepParts($pieces, $keep, $ignored);

            // مدل‌هایی که نام قطعه‌شان تازه است (مثلاً طبقه دامن) هم باید بندانگشتی داشته باشند
            $pieces = $kept === [] ? $pieces : $kept;
        }

        return array_map($this->simplify(...), array_slice($pieces, 0, 2));
    }

    /**
     * سبک‌کردن قطعه برای بندانگشتی.
     *
     * در کارتی به اندازه ناخن، نشانه و مته و راستای پارچه دیده نمی‌شود ولی حجم
     * صفحه را چند برابر می‌کند؛ فقط مسیر قطعه و ساسون‌ها می‌ماند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function simplify(array $piece): array
    {
        $piece['notches'] = [];
        $piece['drills'] = [];
        $piece['markers'] = [];
        $piece['grainline'] = null;
        $piece['edge_allowances'] = [];

        return $piece;
    }

    /**
     * دستور ذخیره‌شده در یک الگو، برای بازکردن دوباره در کارگاه ترکیب.
     *
     * @return array{base: array<string, mixed>, styles: array<int, array<string, mixed>>}
     */
    public function recipeOf(Pattern|array $pattern): array
    {
        $params = $pattern instanceof Pattern ? ($pattern->params ?? []) : $pattern;
        $compose = is_array($params['compose'] ?? null) ? $params['compose'] : [];

        foreach (['recipe', 'selection'] as $key) {
            if (is_array($compose[$key] ?? null) && $compose[$key] !== []) {
                return $this->normalizeRecipe($compose[$key], validate: false);
            }
        }

        return $this->normalizeRecipe([], validate: false);
    }

    /**
     * فقط قطعه‌های خواسته‌شده را نگه می‌دارد و بقیه را در $dropped می‌گذارد.
     *
     * با $exclude برعکس می‌شود: همه چیز جز این نقش‌ها می‌ماند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function keepParts(array $pieces, array $parts, ?array &$dropped, bool $exclude = false): array
    {
        $kept = [];
        $dropped = [];

        foreach ($pieces as $piece) {
            if (in_array($piece['meta']['part'] ?? null, $parts, true) !== $exclude) {
                $kept[] = $piece;
            } else {
                $dropped[] = $piece;
            }
        }

        return $kept;
    }

    /**
     * قطعه‌های یک مدل در نقش «بالاتنه».
     *
     * آستین و یقه خودِ مدل کنار می‌رود چون جداگانه انتخاب می‌شوند، و اگر پایین‌تنه
     * جدا انتخاب شده باشد دامنِ خود مدل هم کنار می‌رود. بقیه (یوک، سجاف، آستر،
     * برگردان یقه) می‌ماند، پس مدل‌های تازه با نام قطعه تازه هم درست بریده می‌شوند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function keepForBodice(array $pieces, bool $hasLower, ?array &$dropped): array
    {
        $kept = [];
        $dropped = [];

        foreach ($pieces as $piece) {
            $group = $this->groupOfPart($piece);
            $drop = in_array($group, ['sleeve', 'collar'], true)
                || ($piece['meta']['part'] ?? null) === 'waistband'
                || ($hasLower && $group === 'lower');

            if ($drop) {
                $dropped[] = $piece;
            } else {
                $kept[] = $piece;
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
        return $this->finalize($this->flatten($groups), $seamMap);
    }

    /**
     * آماده‌سازی نهایی هر قطعه: گروه، کد یکتا، ترتیب، برچسب لبه‌ها و جای دوخت.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finalize(array $pieces, array $seamMap): array
    {
        $out = [];
        $codes = [];
        $sort = 0;

        foreach ($pieces as $index => $piece) {
            $piece['meta'] = array_merge($piece['meta'] ?? [], [
                'group' => $piece['meta']['group'] ?? $this->groupOfPart($piece),
                'composed' => true,
            ]);

            $code = (string) ($piece['code'] ?? $piece['meta']['group'] ?? 'piece');
            $code = $code === '' ? 'piece-'.($index + 1) : $code;

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
            $piece['meta']['edges'] = $this->paddedEdges($piece);
            $piece['edge_allowances'] = $this->seams->allowancesFor($piece, $seamMap);

            $out[] = $piece;
        }

        return $out;
    }

    /** هر لبه باید دقیقاً یک برچسب داشته باشد، وگرنه جای دوخت و دوخت مجازی می‌لنگد. */
    protected function paddedEdges(array $piece): array
    {
        $count = count($piece['outline'] ?? []);
        $edges = array_values($piece['meta']['edges'] ?? []);

        while (count($edges) < $count) {
            $edges[] = 'default';
        }

        return array_slice($edges, 0, $count);
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

            // در شلوار لبه‌های پیش از دم پا درز پهلو و پس از آن درز داخل پا هستند
            $hem = $this->edgeWithTag($lowerFront, 'hem') ?? PHP_INT_MAX;

            for ($i = 0; $i < $pairs; $i++) {
                $label = match (true) {
                    ! $isPants => 'درز پهلوی دامن'.($pairs > 1 ? ' '.($i + 1) : ''),
                    $frontSides[$i] < $hem => 'درز پهلوی شلوار',
                    default => 'درز داخل پا',
                };

                $relations[] = [
                    'from' => ['piece' => $lowerFront['code'], 'edge' => $frontSides[$i]],
                    'to' => ['piece' => $lowerBack['code'], 'edge' => $backSides[$i]],
                    'label' => $label,
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
        $flat = array_filter($ease, fn ($value) => is_numeric($value));
        $own = is_array($ease[$group] ?? null) ? $ease[$group] : [];

        return array_merge($flat, $own);
    }

    /** پارامترهای این بخش (بدون پیش‌فرض ژنراتور). */
    protected function groupParams(array $params, string $group): array
    {
        $flat = array_filter($params, fn ($value) => ! is_array($value));
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
        $recipe = $this->normalizeRecipe($selection, validate: false);
        $base = $recipe['base'];
        $parts = [];

        foreach (['garment', 'bodice', 'sleeve', 'lower'] as $group) {
            if (($base[$group] ?? null) !== null && GeneratorRegistry::has($base[$group])) {
                $parts[] = GeneratorRegistry::make($base[$group])->label();
            }
        }

        if (($base['collar'] ?? null) !== null && isset(static::COLLAR_LABELS[$base['collar']])) {
            $parts[] = static::COLLAR_LABELS[$base['collar']];
        }

        foreach (array_slice($recipe['styles'], 0, 2) as $style) {
            if (StyleRegistry::has($style['key'])) {
                $parts[] = StyleRegistry::make($style['key'])->label();
            }
        }

        return 'ترکیب: '.implode(' + ', $parts ?: ['بدون انتخاب']);
    }

    protected function selectionSummary(array $result): string
    {
        $parts = array_filter(array_values($result['labels'] ?? []));

        foreach ($result['recipe']['styles'] ?? [] as $style) {
            if (StyleRegistry::has($style['key'])) {
                $parts[] = StyleRegistry::make($style['key'])->label();
            }
        }

        return implode(' + ', $parts);
    }

    /** نوع لباس حدسی برای ترکیب (پیراهن یک‌تکه، شلوار یا بلوز). */
    protected function guessGarmentTypeId(array $selection): ?int
    {
        $base = $selection['garment'] ?? $selection['bodice'] ?? null;

        $code = match (true) {
            ($selection['kind'] ?? null) === 'garment' => match ($base) {
                'tshirt' => 'tshirt',
                'shirt_classic' => 'shirt',
                'blazer' => 'blazer',
                'dress' => 'dress',
                default => str_starts_with((string) $base, 'skirt') ? 'skirt' : 'blouse',
            },
            ($selection['lower_kind'] ?? null) === 'pants' => 'pants',
            ($selection['lower_kind'] ?? null) === 'skirt' => 'dress',
            $base === 'tshirt' => 'tshirt',
            $base === 'shirt_classic' => 'shirt',
            $base === 'blazer' => 'blazer',
            default => 'blouse',
        };

        return GarmentType::query()->where('code', $code)->value('id')
            ?? GarmentType::query()->where('code', 'blouse')->value('id');
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
