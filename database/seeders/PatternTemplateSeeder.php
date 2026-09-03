<?php

namespace Database\Seeders;

use App\Models\GarmentType;
use App\Models\PatternPiece;
use App\Models\PatternTemplate;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\PatternGenerator;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * کتابخانه الگوهای پایه (سراسری، بدون کارگاه).
 *
 * برای «هر» تولیدکننده‌ای که در فهرست GeneratorRegistry باشد یک الگو ساخته
 * می‌شود؛ پس هر مدل تازه‌ای که به پوشه Generators اضافه شود بدون دست زدن به این
 * فایل در کتابخانه دیده می‌شود. ردیف‌های دستیِ زیر فقط برای مدل‌هایی است که
 * می‌خواهیم نام یا توضیح ویژه داشته باشند.
 *
 * پارامترهای پیش‌فرض و توضیح پارامترها از خود تولیدکننده خوانده می‌شود تا همیشه
 * با کد هم‌خوان باشد. پیش‌نمایش تنها وقتی دوباره ساخته می‌شود که تولیدکننده یا
 * پارامترهای پیش‌فرض عوض شده باشند؛ در غیر این صورت همان تصویر پیشین می‌ماند و
 * `migrate --seed` سبک باقی می‌ماند.
 */
class PatternTemplateSeeder extends Seeder
{
    /** سایز مبنای پیش‌نمایش. */
    protected const PREVIEW_SIZE = '38';

    public function run(): void
    {
        Cache::lock('pattern-template-seeder', 1800)->block(1800, function (): void {
            $this->seedTemplates();
        });
    }

    /**
     * ساخت کتابخانه درون قفل توزیع‌شده تا اجرای هم‌زمان seed ردیف تکراری نسازد.
     */
    protected function seedTemplates(): void
    {
        $garments = GarmentType::all()->keyBy('code');

        if ($garments->isEmpty()) {
            return;
        }

        $renderer = new SvgRenderer(new SeamAllowanceService);
        $existing = PatternTemplate::whereNull('workshop_id')->get()->keyBy('code');
        $now = now();

        $insert = [];
        $sort = 0;

        foreach ($this->templates() as $template) {
            $sort += 10;
            $garment = $garments[$template['garment']] ?? $garments->first();
            $generator = GeneratorRegistry::make($template['generator']);
            $params = array_merge($generator->defaultParams(), $template['params'] ?? []);
            $ease = $template['ease'] ?? $garment->ease();

            $current = $existing[$template['code']] ?? null;
            $preview = $this->reusablePreview($current, $template['generator'], $params)
                ?? $this->preview($renderer, $generator, $params, $ease);

            $row = [
                'workshop_id' => null,
                'garment_type_id' => $garment->id,
                'code' => $template['code'],
                'name_fa' => $template['name_fa'],
                'generator' => $template['generator'],
                'default_params' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'params_schema' => json_encode($generator->paramsSchema(), JSON_UNESCAPED_UNICODE),
                'description' => $template['description'],
                'preview_svg' => $preview,
                'is_public' => true,
                'sort' => $sort,
            ];

            if ($current === null) {
                $insert[] = array_merge($row, ['created_at' => $now, 'updated_at' => $now]);

                continue;
            }

            $this->updateIfChanged($current, $row, $now);
        }

        foreach (array_chunk($insert, 40) as $chunk) {
            DB::table('pattern_templates')->insert($chunk);
        }
    }

    /**
     * فهرست کامل الگوها: ردیف‌های دستی، بعد هر تولیدکننده‌ای که هنوز الگو ندارد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function templates(): array
    {
        $templates = $this->curated();
        $covered = array_column($templates, 'generator');

        foreach (GeneratorRegistry::grouped() as $group => $row) {
            foreach ($row['generators'] as $key => $label) {
                if (in_array($key, $covered, true)) {
                    continue;
                }

                $templates[] = [
                    'code' => str_replace('_', '-', $key),
                    'name_fa' => $label,
                    'garment' => $this->garmentFor($key, $group),
                    'generator' => $key,
                    'description' => $this->describe($key, $label, $group),
                ];
            }
        }

        return $templates;
    }

    /**
     * الگوهای دستی؛ کد این ردیف‌ها پایدار است و عوض نمی‌شود.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function curated(): array
    {
        return [
            [
                'code' => 'bodice-block',
                'name_fa' => 'بالاتنه پایه',
                'garment' => 'bodice_block',
                'generator' => 'bodice_block',
                'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
                'description' => 'بلوک پایه بالاتنه با ساسون سینه و کمر؛ مادر همه مدل‌های بالاتنه است و برای گرفتن قالب تن استفاده می‌شود.',
            ],
            [
                'code' => 'sleeve-basic',
                'name_fa' => 'آستین ساده',
                'garment' => 'blouse',
                'generator' => 'sleeve',
                'ease' => ['bicep' => 4],
                'description' => 'آستین یک‌تکه با سرآستین متناسب با حلقه آستین؛ با یا بدون مچ‌بند.',
            ],
            [
                'code' => 'skirt-a-line',
                'name_fa' => 'دامن A',
                'garment' => 'skirt_gored',
                'generator' => 'skirt_a_line',
                'ease' => ['waist' => 2, 'hip' => 5],
                'description' => 'دامن ساده که از باسن به پایین باز می‌شود؛ آسان‌ترین دامن برای شروع.',
            ],
            [
                'code' => 'skirt-pencil',
                'name_fa' => 'دامن راسته',
                'garment' => 'skirt_straight',
                'generator' => 'skirt_pencil',
                'ease' => ['waist' => 2, 'hip' => 4],
                'description' => 'دامن مدادی با دو ساسون پشت، کمربند و چاک پشت.',
            ],
            [
                'code' => 'pants-straight',
                'name_fa' => 'شلوار راسته',
                'garment' => 'pants',
                'generator' => 'pants_straight',
                'ease' => ['waist' => 2, 'hip' => 6],
                'description' => 'شلوار کلاسیک راسته با منحنی فاق استاندارد و کمربند.',
            ],
            [
                'code' => 'pants-wide-leg',
                'name_fa' => 'شلوار گشاد',
                'garment' => 'pants_wide',
                'generator' => 'pants_wide_leg',
                'ease' => ['waist' => 2, 'hip' => 8],
                'description' => 'شلوار دم‌گشاد با پیلی جلو؛ برای پارچه‌های خوش‌ریزش مناسب است.',
            ],
            [
                'code' => 'shirt-classic',
                'name_fa' => 'پیراهن کلاسیک',
                'garment' => 'shirt',
                'generator' => 'shirt_classic',
                'ease' => ['bust' => 10, 'waist' => 10, 'hip' => 10, 'bicep' => 6],
                'description' => 'پیراهن جلوباز با یوک پشت، یقه، پاتلت، آستین و مچ‌بند.',
            ],
            [
                'code' => 'dress-basic',
                'name_fa' => 'پیراهن یک‌تکه',
                'garment' => 'dress',
                'generator' => 'dress',
                'ease' => ['bust' => 8, 'waist' => 6, 'hip' => 8, 'bicep' => 5],
                'description' => 'پیراهن یک‌تکه با بالاتنه گرفته و دامن باز؛ درز پهلو یک‌سره از زیر بغل تا لبه دامن.',
            ],
            [
                'code' => 'blazer-basic',
                'name_fa' => 'کت',
                'garment' => 'blazer',
                'generator' => 'blazer',
                'ease' => ['bust' => 12, 'waist' => 12, 'hip' => 12, 'bicep' => 8],
                'description' => 'کت با برگردان یقه، آستین دوتکه، سجاف و آستر؛ برای پارچه‌های بدن‌دار.',
            ],
            [
                'code' => 'tshirt-knit',
                'name_fa' => 'تی‌شرت کشی',
                'garment' => 'tshirt',
                'generator' => 'tshirt',
                'ease' => ['bust' => 4, 'waist' => 6, 'hip' => 6, 'bicep' => 3],
                'description' => 'بلوک پارچه کشی بدون ساسون با نوار یقه؛ آزادی منفی هم می‌پذیرد.',
            ],
        ];
    }

    /** نوع لباسی که این تولیدکننده زیر آن دیده می‌شود. */
    protected function garmentFor(string $key, string $group): string
    {
        $map = [
            // بالاتنه
            'bodice_corset' => 'corset',
            'bodice_peplum' => 'peplum_top',
            'bodice_wrap' => 'wrap_top',
            'bodice_knit' => 'knit_top',
            'bodice_boxy' => 'blouse',
            'bodice_double_breasted' => 'vest',

            // دامن
            'skirt_straight' => 'skirt_straight',
            'skirt_gored' => 'skirt_gored',
            'skirt_tulip' => 'skirt_gored',
            'skirt_bubble' => 'skirt_gored',
            'skirt_godet' => 'skirt_gored',
            'skirt_trumpet' => 'skirt_mermaid',
            'skirt_mermaid' => 'skirt_mermaid',
            'skirt_circle_full' => 'skirt_circle',
            'skirt_circle_half' => 'skirt_circle',
            'skirt_circle_quarter' => 'skirt_circle',
            'skirt_pleat_sunburst' => 'skirt_circle',
            'skirt_pleat_knife' => 'skirt_pleated',
            'skirt_pleat_box' => 'skirt_pleated',
            'skirt_pleat_inverted' => 'skirt_pleated',
            'skirt_pleat_accordion' => 'skirt_pleated',
            'skirt_wrap' => 'skirt_wrap',
            'skirt_tiered' => 'skirt_tiered',
            'skirt_gathered' => 'skirt_tiered',
            'skirt_asymmetric' => 'skirt_asymmetric',
            'skirt_handkerchief' => 'skirt_asymmetric',
            'skirt_yoke' => 'skirt_yoke',
            'skirt_peplum' => 'peplum_top',

            // پایین‌تنه
            'pants_wide_leg' => 'pants_wide',
            'pants_flare' => 'pants_wide',
            'pants_harem' => 'pants_wide',
            'pants_culottes' => 'culottes',
            'pants_jogger' => 'joggers',
            'pants_elastic_waist' => 'joggers',
            'pants_cargo' => 'cargo_pants',
            'leggings' => 'leggings',
            'shorts_short' => 'shorts',
            'shorts_bermuda' => 'shorts',
            'shorts_paperbag' => 'shorts',
            'shorts_cycling' => 'leggings',

            // لباس کامل
            'manteau_straight' => 'manteau',
            'manteau_flared' => 'manteau',
            'manteau_belted' => 'manteau',
            'manteau_abaya' => 'manteau',
            'manteau_short' => 'manteau',
            'abaya' => 'abaya',
            'tunic' => 'tunic',
            'cardigan' => 'cardigan',
            'vest_single' => 'vest',
            'vest_double' => 'vest',
            'coat_classic' => 'coat',
            'coat_trench' => 'trench',
            'raincoat' => 'raincoat',
            'bomber' => 'bomber',
            'hoodie' => 'hoodie',
            'sweatshirt' => 'sweatshirt',
            'kaftan' => 'kaftan',
            'kimono_robe' => 'kimono_robe',
            'jumpsuit' => 'jumpsuit',
            'overall' => 'overall',
            'romper' => 'romper',
            'dress_short' => 'dress',
            'dress_flared' => 'dress',
            'dress_maxi' => 'maxi_dress',
            'dress_mermaid' => 'mermaid_dress',
            'bridal_gown' => 'bridal_dress',
            'child_dress' => 'child_dress',
            'child_hoodie' => 'child_hoodie',
            'child_tshirt' => 'child_top',
        ];

        return $map[$key] ?? match ($group) {
            'bodice' => 'bodice_block',
            'sleeve' => 'blouse',
            'skirt' => 'skirt_straight',
            'pants' => 'pants',
            default => 'dress',
        };
    }

    /** توضیح فارسی این الگو برای کتابخانه. */
    protected function describe(string $key, string $label, string $group): string
    {
        $notes = [
            // بالاتنه
            'bodice_dartless' => 'همه کاهش کمر روی درز پهلو گرفته می‌شود؛ بلوکی برای پارچه‌های نرم و مدل‌هایی که ساسون در آن‌ها دیده نمی‌شود.',
            'bodice_princess_armhole' => 'ساسون سینه و کمر در یک درز بلند از حلقه آستین تا لبه پایین حل می‌شوند؛ دو لبه درز راه برده و هم‌اندازه می‌شوند.',
            'bodice_princess_shoulder' => 'همان درفت پرنسسی با درزی که از وسط سرشانه شروع می‌شود و قد را کشیده‌تر نشان می‌دهد.',
            'bodice_empire' => 'خط کمر لباس زیر سینه می‌نشیند؛ پنل پایین دقیقاً هم‌اندازه لبه برش درفت می‌شود و با چین یا ساسون به آن دوخته می‌گردد.',
            'bodice_drop_waist' => 'خط دوخت از کمر پایین‌تر و روی باسن کوچک می‌نشیند؛ بالاتنه راحت می‌ماند و پُری از پنل پایین می‌آید.',
            'bodice_wrap' => 'دو نیمه جلو روی هم می‌افتند و کاهش کمر با چین ریز گرفته می‌شود؛ روی اندام‌های گوناگون خوب می‌نشیند.',
            'bodice_peplum' => 'بالاتنه روی خط کمر تمام می‌شود و یک دامن کوتاه پُر (پپلوم) به همان خط دوخته می‌گردد.',
            'bodice_corset' => 'نیم‌تنه جلو و پشت به چند پنل عمودی تقسیم می‌شوند و جای ساسون را همین درزها می‌گیرند؛ تعداد پنل پارامتر است.',
            'bodice_boxy' => 'درز پهلو از زیر بغل تا پایین صاف می‌آید؛ سرشانه افتاده و حلقه گودتر برای فرم اورسایز.',
            'bodice_yoke' => 'بالای بالاتنه با یک برش افقی جدا می‌شود و تکه پایین با پیلی یا چین به یوک دوخته می‌گردد.',
            'bodice_double_breasted' => 'لبه جلو از خط مرکز بیرون می‌زند و دو رج دکمه می‌خورد؛ سجاف جلو و سجاف یقه پشت هم ساخته می‌شود.',
            'bodice_knit' => 'الگو با آزادی منفی و کوچک‌تر از بدن درفت می‌شود تا پارچه کشی روی تن بنشیند؛ یقه با نوار کشباف بسته می‌گردد.',

            // لباس کامل
            'manteau_straight' => 'پرکارترین برش مانتوی ایرانی: تنه راسته، جلوباز با دکمه، سجاف جلو و یقه، آستین حلقه‌ای و چاک پهلوی دلخواه.',
            'manteau_flared' => 'تنه تا کمر آرام گرفته و از آنجا کلوش می‌رود؛ برای کرپ و لینن و پارچه‌های خوش‌ریزش.',
            'manteau_belted' => 'تنه با ساسون کمر فرم می‌گیرد و کمربند پارچه‌ای با حلقه روی خط کمر بسته می‌شود.',
            'manteau_abaya' => 'تنه و آستین یک‌سره بریده می‌شوند و درز حلقه حذف می‌گردد؛ کم‌درزترین برش مانتو.',
            'manteau_short' => 'مانتوی کوتاه تا روی باسن با زیپ جلو، سرشانه افتاده و نوار کشباف روی لبه پایین و مچ.',
            'abaya' => 'پوششی بلند و آزاد با آستین یک‌سره؛ جلو باز یا با بند بسته می‌شود.',
            'tunic' => 'بلوز بلند تا میان ران با چاک جلو، کمرگیری ملایم و چاک پهلو.',
            'cardigan' => 'ژاکت جلوباز کشباف با نوار یک‌سره لبه جلو و یقه که کمی کوتاه‌تر بریده می‌شود تا موج نیندازد.',
            'vest_single' => 'جلیقه بدون آستین با حلقه بالاتر و تنگ‌تر، یک رج دکمه، سجاف جلو و آستر.',
            'vest_double' => 'همان جلیقه با هم‌پوشانی بیشتر و دو رج دکمه؛ سجاف جلو به همان اندازه پهن‌تر می‌شود.',
            'coat_classic' => 'پالتو با درز مرکز پشت، آستین دوتکه خیاطی، یقه شالی یا برگردان و آستر کامل.',
            'coat_trench' => 'ترنچ دوطرفه‌دکمه با لتِ سینه، سرشانه‌بند، کمربند و چاک بلند مرکز پشت.',
            'raincoat' => 'بارانی با آستین رگلان (بدون درز حلقه) و کلاه؛ هرچه درز کمتر باشد نفوذ آب کمتر است.',
            'bomber' => 'کاپشن کوتاه با نوار کشباف روی لبه پایین، یقه و مچ؛ قد تنه به اندازه نوار کوتاه‌تر درفت می‌شود.',
            'hoodie' => 'سویشرت کلاه‌دار کشباف با جیب کانگورویی یک‌تکه و نوار کشباف لبه پایین و مچ.',
            'sweatshirt' => 'همان درفت هودی بدون کلاه؛ یقه با نوار کشباف بسته می‌شود.',
            'kaftan' => 'پیراهن بلند یک‌سره با آستین پیوسته و چاک یقه؛ کم‌درزترین لباس کاتالوگ.',
            'kimono_robe' => 'روب جلوباز با آستین یک‌سره، نوار یک‌سره لبه جلو و یقه، و بند کمر با حلقه.',
            'jumpsuit' => 'بالاتنه و شلوار روی خط کمر به هم دوخته می‌شوند؛ بالاتنه کمی بلندتر درفت می‌شود تا نشستن راحت باشد.',
            'overall' => 'شلوار با لتِ سینه و لتِ پشت که با دو بند از روی شانه به هم می‌رسند.',
            'romper' => 'سرهمی کوتاه با پاچه کوتاه و کش کمر؛ بلندی پاچه مستقیم از کاربر گرفته می‌شود.',
            'dress_short' => 'پیراهن یک‌تکه قالبی تا بالای زانو با ساسون بادامی کمر و زیپ مرکز پشت.',
            'dress_flared' => 'بالاتنه روی خط کمر تمام می‌شود و دامن کلوش با کمانی هم‌اندازه لبه کمر به آن دوخته می‌گردد.',
            'dress_maxi' => 'پیراهن بلند تا مچ پا؛ تا باسن قالب و از آنجا راست یا کمی باز، با چاک پهلو برای راه رفتن.',
            'dress_mermaid' => 'بالاتنه پرنسسی و دامنی که تا بالای زانو قالب است و از آنجا کلوش می‌شود.',
            'bridal_gown' => 'کرست چندتکه با فنر روی درزها، آستر کامل، دامن بسیار پُر و زیردامن پفی.',
            'child_dress' => 'بالاتنه صاف بدون ساسون با دامن چین‌دار و بسته شدن از مرکز پشت؛ حلقه و یقه با نوار اریب تمیز می‌شوند.',
            'child_hoodie' => 'هودی بچگانه با یقه پهن‌تر (سر کودک باید راحت رد شود)، آزادی بیشتر برای بازی و کلاه کوچک‌تر.',
            'child_tshirt' => 'تی‌شرت کشباف کودک با نوار یقه؛ اگر دور یقه از دور سر کوچک‌تر بود، چاک مرکز پشت باز می‌شود.',
        ];

        if (isset($notes[$key])) {
            return $notes[$key];
        }

        $groupLabel = GeneratorRegistry::GROUPS[$group] ?? $group;
        $count = count(GeneratorRegistry::make($key)->paramsSchema());

        return 'الگوی پارامتریک «'.$label.'» از گروه '.$groupLabel.'؛ '
            .$this->fa($count).' پارامتر قابل تنظیم دارد و اندازه‌ها و آزادی از نوع لباس گرفته می‌شود.';
    }

    /** پیش‌نمایش پیشین را نگه می‌دارد اگر چیزی عوض نشده باشد. */
    protected function reusablePreview(?PatternTemplate $current, string $generator, array $params): ?string
    {
        if ($current === null || $current->generator !== $generator) {
            return null;
        }

        if (($current->preview_svg ?? '') === '') {
            return null;
        }

        return ($current->default_params ?? []) == $params ? $current->preview_svg : null;
    }

    /** ذخیره تغییرها؛ اگر ردیف دقیقاً همان است، هیچ پرس‌وجویی زده نمی‌شود. */
    protected function updateIfChanged(PatternTemplate $current, array $row, mixed $now): void
    {
        $changed = [];

        foreach ($row as $column => $value) {
            if ($column === 'workshop_id') {
                continue;
            }

            $existing = $current->getRawOriginal($column);

            if ((string) $existing !== (string) $value) {
                $changed[$column] = $value;
            }
        }

        if ($changed === []) {
            return;
        }

        DB::table('pattern_templates')
            ->where('id', $current->id)
            ->update(array_merge($changed, ['updated_at' => $now]));
    }

    /** پیش‌نمایش کوچک الگو برای نمایش در کتابخانه. */
    protected function preview(SvgRenderer $renderer, PatternGenerator $generator, array $params, array $ease): string
    {
        try {
            $pieces = collect($generator->generate(Measurements::fromSize(static::PREVIEW_SIZE), $ease, $params))
                ->take(3)
                ->map(fn (array $piece) => new PatternPiece($piece));

            return $renderer->renderPieces($pieces, [
                'width' => 260,
                'labels' => false,
                'seam_allowance' => false,
                'gap' => 3,
            ]);
        } catch (Throwable) {
            return '';
        }
    }

    /** عدد فارسی. */
    protected function fa(int|float|string $value): string
    {
        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
