<?php

namespace Database\Seeders;

use App\Models\DesignRule;
use Illuminate\Database\Seeder;

/**
 * دانش پایه خیاطی به‌صورت قاعده داده‌ای.
 *
 * این قواعد سراسری هستند (workshop_id خالی) و همه کارگاه‌ها آن‌ها را می‌بینند اما
 * نمی‌توانند تغییرشان دهند؛ هر کارگاه می‌تواند قاعده اختصاصی خودش را اضافه کند.
 * قالب شرط‌ها و کنش‌ها در App\Services\Rules\RuleEngine مستند شده است.
 */
class DesignRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rules() as $rule) {
            DesignRule::updateOrCreate(
                ['workshop_id' => null, 'code' => $rule['code']],
                [
                    'name' => $rule['name'],
                    'scope' => $rule['scope'],
                    'conditions' => $rule['conditions'],
                    'actions' => $rule['actions'],
                    'severity' => $rule['severity'] ?? 'suggestion',
                    'priority' => $rule['priority'] ?? 100,
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function rules(): array
    {
        return [
            // ——— پارچه ———
            [
                'code' => 'sheer-needs-lining',
                'name' => 'پارچه شفاف آستر لازم دارد',
                'scope' => 'fabric',
                'severity' => 'warning',
                'priority' => 10,
                'conditions' => ['all' => [
                    ['field' => 'fabric.transparency', 'op' => '>', 'value' => 0.6],
                ]],
                'actions' => [
                    ['type' => 'suggest_lining', 'message' => 'این پارچه شفاف است؛ بدون آستر بدن دیده می‌شود.'],
                    ['type' => 'note', 'text' => 'آستری هم‌رنگ یا یک درجه روشن‌تر انتخاب کنید.'],
                ],
            ],
            [
                'code' => 'prewash-high-shrinkage',
                'name' => 'پارچه پرآب‌رفت را پیش از برش بشویید',
                'scope' => 'fabric',
                'severity' => 'warning',
                'priority' => 15,
                'conditions' => ['all' => [
                    ['field' => 'fabric.shrinkage', 'op' => '>', 'value' => 4],
                ]],
                'actions' => [
                    ['type' => 'prewash', 'message' => 'آب‌رفت این پارچه بیش از ۴ درصد است؛ اول بشویید و اتو کنید، بعد ببرید.'],
                    ['type' => 'extra_fabric', 'value' => 10],
                ],
            ],
            [
                'code' => 'heavy-fabric-flowy-dress',
                'name' => 'پارچه سنگین برای پیراهن ریزشی مناسب نیست',
                'scope' => 'fabric',
                'severity' => 'warning',
                'priority' => 20,
                'conditions' => ['all' => [
                    ['field' => 'fabric.weight_gsm', 'op' => '>', 'value' => 300],
                    ['field' => 'garment.category', 'op' => 'in', 'value' => ['one_piece', 'formal']],
                ]],
                'actions' => [
                    ['type' => 'unsuitable', 'message' => 'این پارچه برای پیراهن ریزشی سنگین است؛ لباس آویزان و بی‌فرم می‌شود.'],
                    ['type' => 'note', 'text' => 'پارچه‌ای زیر ۲۰۰ گرم بر متر مربع انتخاب کنید یا مدل را به کت و دامن تغییر دهید.'],
                ],
            ],
            [
                'code' => 'stretch-low-recovery',
                'name' => 'کشسانی زیاد با برگشت‌پذیری کم',
                'scope' => 'fabric',
                'severity' => 'warning',
                'priority' => 25,
                'conditions' => ['all' => [
                    ['field' => 'fabric.max_stretch', 'op' => '>', 'value' => 25],
                    ['field' => 'fabric.recovery', 'op' => '<', 'value' => 80],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'این پارچه کشیده می‌شود اما برنمی‌گردد؛ زانو و آرنج لباس شل می‌ماند. برای مدل‌های چسبان به کارش نبرید.'],
                ],
            ],
            [
                'code' => 'drape-suits-aline',
                'name' => 'پارچه لخت برای مدل آزاد و اِی-لاین مناسب است',
                'scope' => 'fabric',
                'severity' => 'info',
                'priority' => 40,
                'conditions' => ['all' => [
                    ['field' => 'fabric.drape', 'op' => '>', 'value' => 0.8],
                ], 'any' => [
                    ['field' => 'garment.silhouette', 'op' => 'contains', 'value' => 'a_line'],
                    ['field' => 'garment.code', 'op' => 'contains', 'value' => 'a-line'],
                    ['field' => 'garment.category', 'op' => 'in', 'value' => ['one_piece', 'formal']],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'انتخاب خوبی است: لختی زیاد این پارچه در مدل آزاد و اِی-لاین ریزش زیبایی می‌دهد.'],
                ],
            ],
            [
                'code' => 'stiff-fabric-no-gathers',
                'name' => 'پارچه سفت برای چین و دراپه مناسب نیست',
                'scope' => 'fabric',
                'severity' => 'suggestion',
                'priority' => 45,
                'conditions' => ['all' => [
                    ['field' => 'fabric.stiffness', 'op' => '>', 'value' => 0.7],
                    ['field' => 'garment.category', 'op' => 'in', 'value' => ['one_piece', 'formal']],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'این پارچه سفت است؛ چین و دراپه در آن حجم می‌گیرد. مدل‌های خط‌دار و هندسی بهتر جواب می‌دهد.'],
                ],
            ],

            // ——— الگو و آزادی ———
            [
                'code' => 'stretch-negative-ease',
                'name' => 'پارچه کشی، آزادی منفی می‌خواهد',
                'scope' => 'pattern',
                'severity' => 'suggestion',
                'priority' => 10,
                'conditions' => ['all' => [
                    ['field' => 'fabric.max_stretch', 'op' => '>', 'value' => 20],
                ]],
                'actions' => [
                    ['type' => 'set_ease', 'key' => 'bust', 'value' => -2, 'message' => 'پارچه کشسانی دارد؛ آزادی دور سینه را ۲ سانتی‌متر کم کردیم.'],
                    ['type' => 'set_ease', 'key' => 'waist', 'value' => -2],
                    ['type' => 'set_ease', 'key' => 'hip', 'value' => -2],
                    ['type' => 'note', 'text' => 'در پارچه کشی، لباس باید کمی کوچک‌تر از بدن بریده شود تا روی بدن بنشیند.'],
                ],
            ],
            [
                'code' => 'knit-tshirt-no-darts',
                'name' => 'تی‌شرت کشباف پنس نمی‌خواهد',
                'scope' => 'pattern',
                'severity' => 'suggestion',
                'priority' => 15,
                'conditions' => ['all' => [
                    ['field' => 'fabric.family', 'op' => '=', 'value' => 'knit'],
                ], 'any' => [
                    ['field' => 'garment.code', 'op' => 'contains', 'value' => 't-shirt'],
                    ['field' => 'garment.code', 'op' => 'contains', 'value' => 'tshirt'],
                    ['field' => 'garment.code', 'op' => 'contains', 'value' => 'tee'],
                ]],
                'actions' => [
                    ['type' => 'no_darts', 'message' => 'در تی‌شرت کشباف پنس لازم نیست؛ کشسانی پارچه فرم سینه را می‌سازد.'],
                    ['type' => 'set_ease', 'key' => 'bust', 'value' => -2],
                ],
            ],
            [
                'code' => 'sheer-dress-underlining',
                'name' => 'پیراهن نیمه‌شفاف زیرلایه می‌خواهد',
                'scope' => 'pattern',
                'severity' => 'suggestion',
                'priority' => 20,
                'conditions' => ['all' => [
                    ['field' => 'fabric.transparency', 'op' => '>', 'value' => 0.45],
                    ['field' => 'garment.category', 'op' => 'in', 'value' => ['one_piece', 'formal', 'top']],
                ]],
                'actions' => [
                    ['type' => 'suggest_underlining', 'message' => 'یک زیرلایه (آستر چسبیده به پارچه) بگذارید تا هم پوشانندگی بیشتر شود و هم پارچه فرم بگیرد.'],
                ],
            ],
            [
                'code' => 'hem-allowance-default',
                'name' => 'لب برگردان استاندارد ۳ سانتی‌متر',
                'scope' => 'pattern',
                'severity' => 'info',
                'priority' => 60,
                'conditions' => ['all' => [
                    ['field' => 'fabric.drape', 'op' => '<=', 'value' => 0.85],
                ]],
                'actions' => [
                    ['type' => 'hem_allowance', 'value' => 3, 'message' => 'لب برگردان پایین لباس را ۳ سانتی‌متر بگیرید.'],
                ],
            ],
            [
                'code' => 'hem-allowance-fluid',
                'name' => 'پارچه بسیار لخت لب برگردان باریک می‌خواهد',
                'scope' => 'pattern',
                'severity' => 'suggestion',
                'priority' => 65,
                'conditions' => ['all' => [
                    ['field' => 'fabric.drape', 'op' => '>', 'value' => 0.85],
                ]],
                'actions' => [
                    ['type' => 'hem_allowance', 'value' => 1, 'message' => 'در پارچه بسیار لخت، لب برگردان را باریک (۱ سانتی‌متر) و دو بار تو بگذارید.'],
                    ['type' => 'note', 'text' => 'لب برگردان پهن در پارچه لخت سنگینی می‌کند و لبه را کج می‌اندازد.'],
                ],
            ],
            [
                'code' => 'bias-cut-high-stretch',
                'name' => 'برش اریب روی پارچه پرکشسان اریب',
                'scope' => 'pattern',
                'severity' => 'warning',
                'priority' => 30,
                'conditions' => ['all' => [
                    ['field' => 'fabric.stretch_bias', 'op' => '>', 'value' => 40],
                    ['field' => 'pattern.cut', 'op' => '=', 'value' => 'bias'],
                ]],
                'actions' => [
                    ['type' => 'avoid_bias', 'message' => 'این پارچه در جهت اریب بسیار کشسان است؛ برش اریب باعث درازشدن و کج‌افتادن لباس می‌شود.'],
                    ['type' => 'note', 'text' => 'اگر برش اریب لازم است، قطعه‌ها را یک شبانه‌روز آویزان بگذارید و بعد لبه را صاف کنید.'],
                ],
            ],
            [
                'code' => 'light-fabric-interfacing',
                'name' => 'پارچه سبک برای یقه و سرآستین لایی می‌خواهد',
                'scope' => 'pattern',
                'severity' => 'suggestion',
                'priority' => 70,
                'conditions' => ['all' => [
                    ['field' => 'fabric.weight_gsm', 'op' => '<', 'value' => 120],
                ]],
                'actions' => [
                    ['type' => 'suggest_interfacing', 'text' => 'لایی چسب نازک برای یقه، سرآستین و جای دکمه بگذارید تا فرم بگیرد.'],
                ],
            ],

            // ——— برش ———
            [
                'code' => 'nap-one-direction',
                'name' => 'پارچه خواب‌دار یک‌جهت بریده می‌شود',
                'scope' => 'cutting',
                'severity' => 'warning',
                'priority' => 10,
                'conditions' => ['all' => [
                    ['field' => 'fabric.has_nap', 'op' => '=', 'value' => true],
                ]],
                'actions' => [
                    ['type' => 'one_direction_cutting', 'message' => 'پارچه خواب‌دار است؛ همه قطعات باید در یک جهت چیده شوند وگرنه رنگ قطعه‌ها با هم فرق می‌کند.'],
                    ['type' => 'extra_fabric', 'value' => 15],
                ],
            ],
            [
                'code' => 'match-stripe-plaid',
                'name' => 'تطبیق راه‌راه و چهارخانه در درزها',
                'scope' => 'cutting',
                'severity' => 'warning',
                'priority' => 15,
                'conditions' => ['all' => [
                    ['field' => 'fabric.surface_pattern', 'op' => 'in', 'value' => ['stripe', 'plaid']],
                ]],
                'actions' => [
                    ['type' => 'match_pattern', 'edge' => 'side', 'message' => 'طرح پارچه باید در درز پهلو دقیقاً روی هم بیفتد.'],
                    ['type' => 'extra_fabric', 'value' => 20],
                    ['type' => 'note', 'text' => 'به اندازه یک تکرار کامل طرح، پارچه بیشتر بخرید.'],
                ],
            ],
            [
                'code' => 'slippery-fabric-cutting',
                'name' => 'پارچه لیز؛ روش برش ویژه',
                'scope' => 'cutting',
                'severity' => 'warning',
                'priority' => 20,
                'conditions' => ['all' => [
                    ['field' => 'fabric.slippage', 'op' => '>', 'value' => 0.7],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'با کاغذ زیر پارچه یا سوزن‌های نزدیک برش بزنید.'],
                    ['type' => 'note', 'text' => 'به‌جای قیچی از کاتر گرد روی زیرانداز برشی استفاده کنید و پارچه را از میز آویزان نگذارید.'],
                ],
            ],
            [
                'code' => 'fraying-seam-allowance',
                'name' => 'لبه‌های ریش‌شو جای دوخت بیشتری می‌خواهند',
                'scope' => 'cutting',
                'severity' => 'suggestion',
                'priority' => 25,
                'conditions' => ['all' => [
                    ['field' => 'fabric.fraying', 'op' => '>', 'value' => 0.6],
                ]],
                'actions' => [
                    ['type' => 'seam_allowance', 'edge' => 'all', 'value' => 1.5, 'message' => 'لبه این پارچه ریش می‌شود؛ جای دوخت را ۱.۵ سانتی‌متر بگیرید.'],
                    ['type' => 'seam_finish', 'value' => 'overlock'],
                ],
            ],
            [
                'code' => 'curling-knit-edges',
                'name' => 'لبه کشباف لوله می‌شود',
                'scope' => 'cutting',
                'severity' => 'suggestion',
                'priority' => 30,
                'conditions' => ['all' => [
                    ['field' => 'fabric.curling', 'op' => '>', 'value' => 0.5],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'لبه‌های این کشباف لوله می‌شود؛ پیش از دوخت با لایی چسب نواری یا اسپری تثبیت‌کننده لبه را بخوابانید.'],
                ],
            ],
            [
                'code' => 'distortion-single-layer',
                'name' => 'پارچه تغییرشکل‌پذیر را تک‌لایه ببرید',
                'scope' => 'cutting',
                'severity' => 'suggestion',
                'priority' => 35,
                'conditions' => ['all' => [
                    ['field' => 'fabric.distortion', 'op' => '>', 'value' => 0.55],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'این پارچه هنگام برش کج می‌شود؛ به‌جای دولا، تک‌لایه و با الگوی کامل (قرینه‌شده) برش بزنید.'],
                ],
            ],
            [
                'code' => 'directional-print-layout',
                'name' => 'طرح جهت‌دار در چیدمان',
                'scope' => 'cutting',
                'severity' => 'info',
                'priority' => 40,
                'conditions' => ['all' => [
                    ['field' => 'fabric.directional', 'op' => '=', 'value' => true],
                ]],
                'actions' => [
                    ['type' => 'one_direction_cutting', 'message' => 'طرح این پارچه جهت‌دار است؛ قطعات را وارونه نچینید.'],
                    ['type' => 'extra_fabric', 'value' => 10],
                ],
            ],

            // ——— تولید و دوخت ———
            [
                'code' => 'knit-overlock-seam',
                'name' => 'درز کشباف با سردوز و کوک کشی',
                'scope' => 'production',
                'severity' => 'suggestion',
                'priority' => 10,
                'conditions' => ['all' => [
                    ['field' => 'fabric.family', 'op' => '=', 'value' => 'knit'],
                ]],
                'actions' => [
                    ['type' => 'seam_finish', 'value' => 'overlock', 'message' => 'درزهای کشباف را سردوز کنید تا با بدن کشیده شود.'],
                    ['type' => 'needle', 'value' => 'سوزن جرسی ۷۵/۱۱ نوک گرد'],
                    ['type' => 'stitch', 'value' => 'کوک کشی یا زیگزاگ باریک'],
                ],
            ],
            [
                'code' => 'stretch-overlock-seam',
                'name' => 'پارچه پرکشسان با درز کشی دوخته می‌شود',
                'scope' => 'production',
                'severity' => 'suggestion',
                'priority' => 15,
                'conditions' => ['all' => [
                    ['field' => 'fabric.max_stretch', 'op' => '>', 'value' => 20],
                ]],
                'actions' => [
                    ['type' => 'seam_finish', 'value' => 'overlock', 'message' => 'کشسانی بالای ۲۰ درصد؛ درز را سردوز کنید تا با کشیدن پارچه نشکند.'],
                    ['type' => 'stitch', 'value' => 'کوک کشی ۲.۵ میلی‌متر'],
                ],
            ],
            [
                'code' => 'sheer-french-seam',
                'name' => 'درز فرانسوی برای پارچه نازک و شفاف',
                'scope' => 'production',
                'severity' => 'suggestion',
                'priority' => 20,
                'conditions' => ['all' => [
                    ['field' => 'fabric.transparency', 'op' => '>', 'value' => 0.5],
                    ['field' => 'fabric.weight_gsm', 'op' => '<', 'value' => 120],
                ]],
                'actions' => [
                    ['type' => 'seam_finish', 'value' => 'french', 'message' => 'در پارچه شفاف، درز فرانسوی بزنید تا لبه ریش از بیرون دیده نشود.'],
                    ['type' => 'seam_allowance', 'edge' => 'all', 'value' => 1.5],
                ],
            ],
            [
                'code' => 'heat-sensitive-low-iron',
                'name' => 'پارچه حساس به حرارت، اتوی کم',
                'scope' => 'production',
                'severity' => 'warning',
                'priority' => 25,
                'conditions' => ['all' => [
                    ['field' => 'fabric.heat_sensitivity', 'op' => '>', 'value' => 0.6],
                ]],
                'actions' => [
                    ['type' => 'iron_temp', 'value' => 'low', 'message' => 'این پارچه به حرارت حساس است؛ دمای اتو را روی کمترین درجه بگذارید.'],
                    ['type' => 'note', 'text' => 'همیشه یک پارچه نخی نازک روی کار بگذارید و اول روی تکه اضافه امتحان کنید.'],
                ],
            ],
            [
                'code' => 'steam-sensitive-no-steam',
                'name' => 'پارچه حساس به بخار',
                'scope' => 'production',
                'severity' => 'warning',
                'priority' => 30,
                'conditions' => ['all' => [
                    ['field' => 'fabric.steam_sensitivity', 'op' => '>', 'value' => 0.6],
                ]],
                'actions' => [
                    ['type' => 'note', 'text' => 'بدون بخار و از پشت پارچه اتو کنید؛ بخار روی این پارچه لکه و رگه می‌گذارد.'],
                ],
            ],
            [
                'code' => 'heavy-denim-needle',
                'name' => 'پارچه سنگین سوزن ضخیم می‌خواهد',
                'scope' => 'production',
                'severity' => 'suggestion',
                'priority' => 35,
                'conditions' => ['all' => [
                    ['field' => 'fabric.weight_gsm', 'op' => '>', 'value' => 300],
                ]],
                'actions' => [
                    ['type' => 'needle', 'value' => 'سوزن ۱۰۰/۱۶ مخصوص جین', 'message' => 'برای پارچه سنگین سوزن ۱۰۰/۱۶ و نخ مقاوم به کار ببرید.'],
                    ['type' => 'stitch', 'value' => 'کوک ۳ تا ۳.۵ میلی‌متر'],
                    ['type' => 'seam_finish', 'value' => 'flat_fell'],
                ],
            ],
            [
                'code' => 'lace-bound-seam',
                'name' => 'درز توری و دانتل با نواردوزی',
                'scope' => 'production',
                'severity' => 'suggestion',
                'priority' => 40,
                'conditions' => ['all' => [
                    ['field' => 'fabric.family', 'op' => '=', 'value' => 'lace'],
                ]],
                'actions' => [
                    ['type' => 'seam_finish', 'value' => 'bound', 'message' => 'درز توری و دانتل را با نوار اریب تمیز کنید یا لبه گل‌دار را روی هم بیندازید.'],
                    ['type' => 'needle', 'value' => 'سوزن ۷۰/۱۰ نوک گرد'],
                ],
            ],
            [
                'code' => 'pressing-high-fabric',
                'name' => 'پارچه‌ای که با اتو فرم می‌گیرد',
                'scope' => 'production',
                'severity' => 'info',
                'priority' => 45,
                'conditions' => ['all' => [
                    ['field' => 'fabric.pressing', 'op' => '=', 'value' => 'high'],
                ]],
                'actions' => [
                    ['type' => 'iron_temp', 'value' => 'high', 'message' => 'این پارچه اتو را خوب می‌گیرد؛ هر درز را بعد از دوخت اتو کنید.'],
                    ['type' => 'note', 'text' => 'اتوی مرحله‌به‌مرحله بیشتر از هر چیزی کار را حرفه‌ای نشان می‌دهد.'],
                ],
            ],
            [
                'code' => 'thin-fabric-fine-needle',
                'name' => 'پارچه نازک سوزن ریز می‌خواهد',
                'scope' => 'production',
                'severity' => 'suggestion',
                'priority' => 50,
                'conditions' => ['all' => [
                    ['field' => 'fabric.thickness_mm', 'op' => '<', 'value' => 0.25],
                ]],
                'actions' => [
                    ['type' => 'needle', 'value' => 'سوزن ۶۰/۸ یا ۷۰/۱۰ میکروتکس', 'message' => 'برای پارچه نازک سوزن ریز و نو به کار ببرید تا سوراخ نماند.'],
                    ['type' => 'stitch', 'value' => 'کوک ریز ۲ میلی‌متر'],
                ],
            ],
        ];
    }
}
