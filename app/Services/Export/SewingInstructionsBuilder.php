<?php

namespace App\Services\Export;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\Project;
use App\Support\FabricProfile;
use App\Support\Format;
use App\Support\Jalali;

/**
 * ترتیب دوخت به فارسی، بر پایه رابطه‌های دوخت الگو و اجزای لباس.
 *
 * اگر الگو رابطه دوخت نداشته باشد، از روی اجزای نوع لباس یک ترتیب استاندارد
 * (آماده‌سازی، لایی، ساسون، سرشانه، آستین، پهلو، یقه، تودوزی، اتو) ساخته می‌شود تا
 * کاربر تازه‌کار هم بداند از کجا شروع کند.
 */
class SewingInstructionsBuilder
{
    public function build(Project $project): array
    {
        $pattern = $project->pattern;
        $profile = $project->fabric?->profile() ?? FabricProfile::make();

        $steps = $this->steps($project, $pattern, $profile);

        return [
            'steps' => $this->numbered($steps),
            'advice' => $this->advice($project, $profile),
            'warnings' => $this->warnings($project, $profile),
            'source' => match (true) {
                ! empty($pattern?->sewing_relations) => 'pattern_relations',
                $pattern !== null => 'pattern_pieces',
                default => 'garment_parts',
            },
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function steps(Project $project, ?Pattern $pattern, FabricProfile $profile): array
    {
        $pieces = $pattern ? $pattern->pieces : collect();
        $parts = $project->garmentType?->parts ?? [];
        $steps = [];

        // ۱. آماده‌سازی پارچه
        $prepTips = ['پارچه را با راستای درست روی میز پهن کنید.'];

        if ((float) $profile->get('shrinkage') >= 3) {
            $prepTips[] = 'این پارچه آب می‌رود؛ پیش از برش یک بار بشویید و خشک کنید.';
        }

        if ((float) $profile->get('slippage') >= 0.6) {
            $prepTips[] = 'پارچه سر می‌خورد؛ زیر آن کاغذ بگذارید و با سنجاق یا گیره ثابت کنید.';
        }

        $steps[] = [
            'title' => 'آماده‌سازی و برش',
            'description' => 'قطعه‌ها را بر پایه نقشه برش روی پارچه بچینید، علامت‌ها و ساسون‌ها را منتقل کنید و سپس ببرید.',
            'tips' => $prepTips,
        ];

        // ۲. لایی
        $interfacing = $pieces->where('layer', 'interfacing');

        if ($interfacing->isNotEmpty() || in_array('interfacing', $parts, true)) {
            $steps[] = [
                'title' => 'چسباندن لایی',
                'description' => $interfacing->isNotEmpty()
                    ? 'لایی را روی این قطعه‌ها بچسبانید: '.$interfacing->pluck('name')->implode('، ')
                    : 'لایی را روی سجاف، یقه و جای دکمه بچسبانید.',
                'tips' => [(float) $profile->get('heat_sensitivity') >= 0.6
                    ? 'این پارچه به حرارت حساس است؛ با دمای کم و پارچه محافظ لایی بچسبانید.'
                    : 'یک تکه آزمایشی بچسبانید تا دما و زمان دست‌تان بیاید.'],
            ];
        }

        // ۳. ساسون‌ها و پیلی‌ها
        $darted = $pieces->filter(fn (PatternPiece $piece) => ! empty($piece->darts));

        if ($darted->isNotEmpty()) {
            $steps[] = [
                'title' => 'دوختن ساسون‌ها',
                'description' => 'ساسون‌های این قطعه‌ها را از پهن به نوک بدوزید و ته‌دوزی کنید: '
                    .$darted->pluck('name')->implode('، '),
                'tips' => ['ساسون سینه را به سمت پایین و ساسون کمر را به سمت مرکز اتو کنید.'],
            ];
        }

        $pleated = $pieces->filter(fn (PatternPiece $piece) => ! empty($piece->pleats));

        if ($pleated->isNotEmpty()) {
            $steps[] = [
                'title' => 'پیلی و چین',
                'description' => 'پیلی‌های '.$pleated->pluck('name')->implode('، ').' را تا بزنید و با کوک بلند ثابت کنید.',
                'tips' => [],
            ];
        }

        // ۴. رابطه‌های دوخت الگو
        foreach ($this->relationSteps($pattern) as $step) {
            $steps[] = $step;
        }

        // ۵. ترتیب استاندارد بر پایه اجزای لباس
        foreach ($this->partSteps($parts, $pieces) as $step) {
            $steps[] = $step;
        }

        // ۶. تودوزی و اتوی نهایی
        $steps[] = [
            'title' => 'پاک‌دوزی درزها',
            'description' => 'لبه درزها را با روش مناسب این پارچه تمام کنید: '.$this->seamFinish($profile),
            'tips' => [],
        ];

        $steps[] = [
            'title' => 'تودوزی و اتوی نهایی',
            'description' => 'دامن یا لبه پایین را تو بگذارید، دکمه و بندها را کار بگذارید و لباس را مرحله‌به‌مرحله اتو کنید.',
            'tips' => [$this->pressing($profile)],
        ];

        return $steps;
    }

    /**
     * مرحله‌های برگرفته از «دوخت مجازی» الگو: کدام لبه به کدام لبه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function relationSteps(?Pattern $pattern): array
    {
        $relations = (array) ($pattern?->sewing_relations ?? []);

        if ($relations === []) {
            return [];
        }

        $names = $pattern->pieces->mapWithKeys(fn (PatternPiece $piece) => [$piece->code => $piece->name]);
        $steps = [];

        foreach ($relations as $relation) {
            if (! is_array($relation)) {
                continue;
            }

            $from = $this->relationSide($relation, 'from');
            $to = $this->relationSide($relation, 'to');

            if ($from === null || $to === null) {
                continue;
            }

            $label = $relation['label'] ?? $relation['title'] ?? null;

            $steps[] = [
                'title' => $label ?: 'دوختن درز',
                'description' => sprintf(
                    'لبه %s از قطعه «%s» را به لبه %s از قطعه «%s» بدوزید.',
                    $from['edge'],
                    $names[$from['piece']] ?? $from['piece'],
                    $to['edge'],
                    $names[$to['piece']] ?? $to['piece'],
                ),
                'tips' => array_values(array_filter([$relation['note'] ?? null])),
            ];
        }

        return $steps;
    }

    /** خواندن یک سر رابطه دوخت با پذیرش چند نام‌گذاری متفاوت. */
    protected function relationSide(array $relation, string $side): ?array
    {
        $piece = data_get($relation, $side.'.piece')
            ?? data_get($relation, $side.'_piece')
            ?? data_get($relation, $side.'.code')
            ?? (is_string($relation[$side] ?? null) ? $relation[$side] : null);

        if ($piece === null) {
            return null;
        }

        $edge = data_get($relation, $side.'.edge')
            ?? data_get($relation, $side.'_edge')
            ?? data_get($relation, $side.'.label');

        return [
            'piece' => (string) $piece,
            'edge' => $edge === null ? 'مشخص‌شده' : (is_numeric($edge) ? 'شماره '.Jalali::digits((string) $edge) : (string) $edge),
        ];
    }

    /**
     * ترتیب استاندارد دوخت بر پایه اجزای نوع لباس.
     *
     * @param  array<int, string>  $parts
     * @return array<int, array<string, mixed>>
     */
    protected function partSteps(array $parts, $pieces): array
    {
        $codes = collect($pieces)->pluck('code')->map(fn ($code) => (string) $code)->all();
        $has = function (array $needles) use ($parts, $codes): bool {
            foreach ($needles as $needle) {
                if (in_array($needle, $parts, true)) {
                    return true;
                }

                foreach ($codes as $code) {
                    if (str_contains($code, $needle)) {
                        return true;
                    }
                }
            }

            return false;
        };

        $steps = [];

        if ($has(['front_bodice', 'back_bodice', 'front', 'back'])) {
            $steps[] = [
                'title' => 'دوختن سرشانه',
                'description' => 'بالاتنه جلو و پشت را از سرشانه به هم بدوزید و درز را اتو کنید.',
                'tips' => ['برای اینکه سرشانه کشیده نشود، از نوار تقویتی نازک استفاده کنید.'],
            ];
        }

        if ($has(['yoke'])) {
            $steps[] = [
                'title' => 'نصب یوک (سرشانه پشت)',
                'description' => 'یوک را روی بالاتنه پشت بدوزید و درز آن را به سمت بالا اتو کنید.',
                'tips' => [],
            ];
        }

        if ($has(['collar', 'lapel', 'hood'])) {
            $steps[] = [
                'title' => 'دوختن و نصب یقه',
                'description' => 'یقه را دوخته و برگردانید، سپس روی حلقه گردن بدوزید و گوشه‌ها را تیز اتو کنید.',
                'tips' => ['علامت مرکز جلو و مرکز پشت را روی هم بگذارید تا یقه کج نشود.'],
            ];
        }

        if ($has(['sleeve'])) {
            $steps[] = [
                'title' => 'نصب آستین',
                'description' => 'حلقه آستین را با علامت‌ها روی حلقه بالاتنه بگذارید، کمی چین سرآستین بدهید و بدوزید.',
                'tips' => ['اول آستین را به بالاتنه باز بدوزید، بعد درز پهلو و آستین را یک‌جا ببندید.'],
            ];
        }

        if ($has(['front_bodice', 'back_bodice', 'front_panel', 'back_panel', 'front', 'back'])) {
            $steps[] = [
                'title' => 'بستن درز پهلو',
                'description' => 'درزهای پهلو (و در صورت وجود، درز زیر آستین) را از حلقه تا پایین ببندید.',
                'tips' => [],
            ];
        }

        if ($has(['cuff'])) {
            $steps[] = [
                'title' => 'مچ آستین',
                'description' => 'مچ را با لایی آماده کنید و روی سر آستین بدوزید.',
                'tips' => [],
            ];
        }

        if ($has(['skirt_front', 'skirt_back', 'waistband'])) {
            $steps[] = [
                'title' => 'دامن و کمر',
                'description' => 'قطعه‌های دامن را از پهلو ببندید، سپس کمر را با لایی روی خط کمر بدوزید.',
                'tips' => [],
            ];
        }

        if ($has(['front_leg', 'back_leg'])) {
            $steps[] = [
                'title' => 'دوخت پاچه‌ها',
                'description' => 'درز داخل و بیرون پا را ببندید، سپس دو پاچه را از فاق به هم بدوزید.',
                'tips' => ['درز فاق را دو بار بدوزید تا محکم بماند.'],
            ];
        }

        if ($has(['pocket'])) {
            $steps[] = [
                'title' => 'جیب‌ها',
                'description' => 'جیب‌ها را در جای علامت‌گذاری‌شده کار بگذارید.',
                'tips' => ['جای جیب را پیش از بستن درز پهلو بدوزید؛ کار راحت‌تر است.'],
            ];
        }

        if ($has(['placket'])) {
            $steps[] = [
                'title' => 'جای دکمه و دکمه',
                'description' => 'پاتلت را تا بزنید و بدوزید، جای دکمه‌ها را علامت بزنید و مادگی‌ها را بدوزید.',
                'tips' => [],
            ];
        }

        if ($has(['lining'])) {
            $steps[] = [
                'title' => 'آستر',
                'description' => 'آستر را جدا بدوزید و از حلقه گردن و پایین به لباس وصل کنید.',
                'tips' => ['کمی آزادی (فرم) در آستر بگذارید تا لباس را نکشد.'],
            ];
        }

        if ($steps === []) {
            $steps[] = [
                'title' => 'دوختن درزهای اصلی',
                'description' => 'قطعه‌ها را به ترتیب از بالا به پایین به هم بدوزید و پس از هر درز اتو کنید.',
                'tips' => [],
            ];
        }

        return $steps;
    }

    /** @return array<int, array<string, mixed>> */
    protected function numbered(array $steps): array
    {
        return collect($steps)
            ->values()
            ->map(fn (array $step, int $index) => [
                'number' => $index + 1,
                'title' => $step['title'],
                'description' => $step['description'],
                'tips' => array_values($step['tips'] ?? []),
            ])
            ->all();
    }

    /** پیشنهاد سوزن، نخ، کوک، پاک‌دوزی و اتو بر پایه شناسنامه پارچه. */
    public function advice(Project $project, ?FabricProfile $profile = null): array
    {
        $profile ??= $project->fabric?->profile() ?? FabricProfile::make();
        $isKnit = $project->fabric?->fabricType?->family === 'knit' || $profile->isStretchy();
        $gsm = (float) $profile->get('weight_gsm');

        return [
            'needle' => $isKnit
                ? 'سوزن نوک‌گرد (جرسی) شماره ۷۵/۱۱'
                : match (true) {
                    $gsm < 80 => 'سوزن باریک ۶۰/۸ یا میکروتکس ۷۰/۱۰',
                    $gsm < 150 => 'سوزن ۷۰/۱۰',
                    $gsm < 250 => 'سوزن ۸۰/۱۲',
                    $gsm < 350 => 'سوزن ۹۰/۱۴',
                    default => 'سوزن ضخیم ۱۰۰/۱۶',
                },
            'thread' => match (true) {
                $gsm >= 300 => 'نخ پلی‌استر ضخیم (شماره ۳۰) هم‌رنگ پارچه',
                $profile->isSheer() => 'نخ نازک شماره ۱۰۰ هم‌رنگ پارچه',
                default => 'نخ پلی‌استر معمولی (شماره ۱۲۰) هم‌رنگ پارچه',
            },
            'stitch' => $isKnit
                ? 'کوک کشی یا زیگزاگ باریک، طول ۲.۵ میلی‌متر'
                : match (true) {
                    $gsm < 80 => 'کوک راسته با طول ۲ تا ۲.۵ میلی‌متر',
                    $gsm < 250 => 'کوک راسته با طول ۲.۵ تا ۳ میلی‌متر',
                    default => 'کوک راسته با طول ۳ تا ۳.۵ میلی‌متر',
                },
            'seam_finish' => $this->seamFinish($profile),
            'pressing' => $this->pressing($profile),
            'difficulty' => $profile->difficulty(),
            'difficulty_label' => match (true) {
                $profile->difficulty() >= 0.66 => 'کار با این پارچه دشوار است',
                $profile->difficulty() >= 0.33 => 'کار با این پارچه متوسط است',
                default => 'کار با این پارچه آسان است',
            },
        ];
    }

    protected function seamFinish(FabricProfile $profile): string
    {
        return match (true) {
            (float) $profile->get('fraying') >= 0.6 => 'سرتیز (اورلوک) یا درز فرانسوی؛ لبه این پارچه زیاد ریش می‌شود',
            (float) $profile->get('fraying') >= 0.3 => 'سرتیز یا زیگزاگ روی لبه درز',
            default => 'زیگزاگ ساده یا حتی لبه باز کافی است',
        };
    }

    protected function pressing(FabricProfile $profile): string
    {
        if ((float) $profile->get('heat_sensitivity') >= 0.6) {
            return 'اتو با دمای کم و پارچه محافظ؛ این پارچه به حرارت حساس است';
        }

        if ((float) $profile->get('steam_sensitivity') >= 0.6) {
            return 'اتوی خشک بدون بخار؛ بخار روی این پارچه رد می‌گذارد';
        }

        return match ($profile->get('pressing')) {
            'high' => 'اتوی مرحله‌به‌مرحله و کامل پس از هر درز لازم است',
            'low' => 'اتوی سبک کافی است',
            default => 'پس از هر درز اتو کنید',
        };
    }

    /** @return array<int, string> */
    protected function warnings(Project $project, FabricProfile $profile): array
    {
        $warnings = [];

        if ($project->pattern === null) {
            $warnings[] = 'الگویی به این پروژه وصل نیست؛ ترتیب دوخت عمومی نوشته شده است.';
        }

        if ($project->fabric === null) {
            $warnings[] = 'پارچه‌ای انتخاب نشده است؛ پیشنهاد سوزن و کوک بر پایه پارچه متوسط است.';
        }

        if ((float) $profile->get('transparency') >= 0.5) {
            $warnings[] = 'پارچه نازک و شفاف است؛ درزها را باریک و تمیز بدوزید یا آستر بگذارید.';
        }

        if ((float) $profile->get('curling') >= 0.6) {
            $warnings[] = 'لبه این پارچه لوله می‌شود؛ پیش از دوخت لبه‌ها را با کوک یا چسب موقت ثابت کنید.';
        }

        if ($project->garmentType && ! empty($project->garmentType->parts) && $project->pattern) {
            $missing = array_diff(
                ['sleeve'],
                $project->pattern->pieces->pluck('code')->all(),
            );

            if (in_array('sleeve', $project->garmentType->parts, true) && $missing !== []) {
                $warnings[] = 'نوع لباس آستین دارد ولی در الگو قطعه آستین پیدا نشد؛ الگو را کامل کنید.';
            }
        }

        return array_values(array_unique($warnings));
    }

    /** متن ساده ترتیب دوخت برای خروجی فایل. */
    public function toText(Project $project): string
    {
        $data = $this->build($project);
        $lines = ['ترتیب دوخت — '.$project->name, str_repeat('=', 40), ''];

        foreach ($data['steps'] as $step) {
            $lines[] = Jalali::digits((string) $step['number']).'. '.$step['title'];
            $lines[] = '   '.$step['description'];

            foreach ($step['tips'] as $tip) {
                $lines[] = '   • '.$tip;
            }

            $lines[] = '';
        }

        $lines[] = 'راهنمای پارچه';
        $lines[] = str_repeat('-', 20);

        foreach ([
            'سوزن' => $data['advice']['needle'],
            'نخ' => $data['advice']['thread'],
            'کوک' => $data['advice']['stitch'],
            'پاک‌دوزی' => $data['advice']['seam_finish'],
            'اتو' => $data['advice']['pressing'],
        ] as $label => $value) {
            $lines[] = $label.': '.$value;
        }

        if ($project->garmentType) {
            $lines[] = '';
            $lines[] = 'اجزای لباس: '.implode('، ', array_map(
                fn ($part) => GarmentType::partLabel($part),
                $project->garmentType->parts ?? [],
            ));
        }

        $lines[] = '';
        $lines[] = 'سختی کار با پارچه: '.Format::ratio($data['advice']['difficulty']);

        return implode("\n", $lines);
    }
}
