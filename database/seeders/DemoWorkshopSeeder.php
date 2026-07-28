<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CuttingLayout;
use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Material;
use App\Models\MeasurementSet;
use App\Models\Order;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Simulation;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Cutting\NestingService;
use App\Services\Fit\FitAnalysisService;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SewingRelationBuilder;
use App\Services\TechPack\TechPackBuilder;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Database\Seeder;

/**
 * یک کارگاه نمونه برای دیدن سامانه بدون ورود دستی داده.
 *
 * همه چیز با همان سرویس‌های واقعی ساخته می‌شود: الگو با موتور الگوسازی، تناسب با
 * تحلیل تناسب، برش با موتور چیدمان و کارت فنی با سازنده کارت فنی.
 */
class DemoWorkshopSeeder extends Seeder
{
    protected const OWNER_EMAIL = 'demo@dokht.test';

    public function run(): void
    {
        if (User::where('email', static::OWNER_EMAIL)->exists()) {
            $this->command?->info('کارگاه نمونه از قبل ساخته شده است.');

            return;
        }

        $context = app(WorkshopContext::class);

        $workshop = Workshop::create([
            'name' => 'خیاطی گلستان',
            'phone' => '02155667788',
            'city' => 'تهران',
            'address' => 'خیابان ولیعصر، کوچه بهار، پلاک ۱۲',
            'currency' => 'IRT',
            'default_seam_allowance' => 1.0,
            'default_hem_allowance' => 3.0,
            'settings' => ['fabric_width' => 140],
        ]);

        $owner = User::create([
            'workshop_id' => $workshop->id,
            'name' => 'مریم گلستانی',
            'email' => static::OWNER_EMAIL,
            'password' => 'password',
            'role' => 'owner',
            'phone' => '09121234567',
        ]);

        $workshop->update(['owner_id' => $owner->id]);

        User::create([
            'workshop_id' => $workshop->id,
            'name' => 'زهرا رستمی',
            'email' => 'tailor@dokht.test',
            'password' => 'password',
            'role' => 'tailor',
        ]);

        User::create([
            'workshop_id' => $workshop->id,
            'name' => 'سمیرا نیک‌پور',
            'email' => 'designer@dokht.test',
            'password' => 'password',
            'role' => 'designer',
        ]);

        // مدیر سامانه، با کارگاه شخصی خودش
        $adminWorkshop = Workshop::create(['name' => 'کارگاه مدیر سامانه']);

        $admin = User::create([
            'workshop_id' => $adminWorkshop->id,
            'name' => 'سرپرست سامانه',
            'email' => 'admin@dokht.test',
            'password' => 'password',
            'role' => 'owner',
            'is_admin' => true,
        ]);

        $adminWorkshop->update(['owner_id' => $admin->id]);

        // از این پس همه داده‌ها به کارگاه نمونه تعلق می‌گیرند
        $context->set($workshop);

        try {
            $customers = $this->customers();
            $fabrics = $this->fabrics();
            $this->materials();
            $projects = $this->projects($customers, $fabrics);
            $this->orders($workshop, $customers, $projects, $fabrics);
        } finally {
            $context->forget();
        }

        $this->command?->info('کارگاه نمونه ساخته شد — ورود با '.static::OWNER_EMAIL.' و رمز password');
    }

    /** @return array<string, Customer> */
    protected function customers(): array
    {
        $rows = [
            'نازنین' => ['name' => 'نازنین موسوی', 'phone' => '09121110011', 'size' => '38', 'gender' => 'female'],
            'الهام' => ['name' => 'الهام قاسمی', 'phone' => '09121110022', 'size' => '42', 'gender' => 'female'],
            'شیرین' => ['name' => 'شیرین کاظمی', 'phone' => '09121110033', 'size' => '40', 'gender' => 'female'],
            'مینا' => ['name' => 'مینا حسینی', 'phone' => '09121110044', 'size' => '46', 'gender' => 'female'],
            'رضا' => ['name' => 'رضا امینی', 'phone' => '09121110055', 'size' => '44', 'gender' => 'male'],
            'یلدا' => ['name' => 'یلدا صادقی', 'phone' => '09121110066', 'size' => '36', 'gender' => 'female'],
        ];

        $customers = [];

        foreach ($rows as $key => $row) {
            $customer = Customer::create([
                'name' => $row['name'],
                'phone' => $row['phone'],
                'gender' => $row['gender'],
                'notes' => $key === 'مینا' ? 'سرشانه افتاده؛ آستین را کمی بلندتر می‌خواهد.' : null,
            ]);

            // اندازه‌ها کمی حول سایز استاندارد تغییر می‌کنند تا واقعی به نظر برسند
            $base = Measurements::SIZE_TABLE[$row['size']];

            MeasurementSet::create([
                'customer_id' => $customer->id,
                'title' => 'اندازه‌های امسال',
                'base_size' => $row['size'],
                'values' => Measurements::clean([
                    'height' => $base['height'] + rand(-6, 6),
                    'bust' => $base['bust'] + rand(-2, 2),
                    'waist' => $base['waist'] + rand(-2, 3),
                    'hip' => $base['hip'] + rand(-2, 2),
                    'shoulder_width' => $base['shoulder_width'] + rand(-1, 1),
                    'arm_length' => $base['arm_length'] + rand(-2, 2),
                ]),
                'is_default' => true,
            ]);

            $customers[$key] = $customer;
        }

        // یک مشتری با دو مجموعه اندازه، برای دیدن تاریخچه
        MeasurementSet::create([
            'customer_id' => $customers['الهام']->id,
            'title' => 'اندازه‌های سال گذشته',
            'base_size' => '44',
            'values' => Measurements::clean(Measurements::SIZE_TABLE['44']),
            'is_default' => false,
            'notes' => 'قبل از تغییر وزن.',
        ]);

        return $customers;
    }

    /** @return array<string, Fabric> */
    protected function fabrics(): array
    {
        $rows = [
            'کتان' => ['code' => 'linen', 'name' => 'کتان ایتالیایی', 'color' => 'کرم', 'hex' => '#e8ddc8', 'width' => 150, 'price' => 480000, 'stock' => 12.5],
            'حریر' => ['code' => 'silk-habotai', 'name' => 'حریر مجلسی', 'color' => 'یاسی', 'hex' => '#c7b3d6', 'width' => 110, 'price' => 950000, 'stock' => 6],
            'کرپ' => ['code' => 'crepe', 'name' => 'کرپ اسکاچی', 'color' => 'مشکی', 'hex' => '#1c1c1c', 'width' => 150, 'price' => 320000, 'stock' => 18],
            'جین' => ['code' => 'denim', 'name' => 'جین سنگین', 'color' => 'آبی نفتی', 'hex' => '#2f4661', 'width' => 140, 'price' => 560000, 'stock' => 9],
            'جرسی' => ['code' => 'jersey', 'name' => 'جرسی پنبه', 'color' => 'طوسی روشن', 'hex' => '#b8b8b8', 'width' => 180, 'price' => 290000, 'stock' => 22],
            'مخمل' => ['code' => 'velvet', 'name' => 'مخمل کشی', 'color' => 'زمردی', 'hex' => '#1f5c4a', 'width' => 140, 'price' => 780000, 'stock' => 4.5],
            'گاباردین' => ['code' => 'wool-gabardine', 'name' => 'گاباردین کت و شلواری', 'color' => 'سرمه‌ای', 'hex' => '#25324b', 'width' => 150, 'price' => 690000, 'stock' => 14],
            'راه‌راه' => ['code' => 'cotton-poplin', 'name' => 'پوپلین راه‌راه', 'color' => 'آبی و سفید', 'hex' => '#9fc0dd', 'width' => 140, 'price' => 260000, 'stock' => 16, 'pattern' => 'stripe', 'repeat' => 4],
            'چهارخانه' => ['code' => 'wool-flannel', 'name' => 'فلانل چهارخانه', 'color' => 'خاکی', 'hex' => '#8d7b63', 'width' => 150, 'price' => 540000, 'stock' => 7, 'pattern' => 'plaid', 'repeat' => 8],
            'آستر' => ['code' => 'lining-poly', 'name' => 'آستر ساده', 'color' => 'مشکی', 'hex' => '#222222', 'width' => 150, 'price' => 120000, 'stock' => 30],
        ];

        $fabrics = [];

        foreach ($rows as $key => $row) {
            $type = FabricType::where('code', $row['code'])->first() ?? FabricType::orderBy('sort')->first();

            $fabrics[$key] = Fabric::create([
                'fabric_type_id' => $type?->id,
                'name' => $row['name'],
                'color' => $row['color'],
                'color_hex' => $row['hex'],
                'surface_pattern' => $row['pattern'] ?? 'plain',
                'pattern_repeat_cm' => $row['repeat'] ?? null,
                'width_cm' => $row['width'],
                'gsm' => $type?->fabricProfile()->get('weight_gsm'),
                'price_per_meter' => $row['price'],
                'stock_meters' => $row['stock'],
            ]);
        }

        return $fabrics;
    }

    protected function materials(): void
    {
        $rows = [
            ['name' => 'نخ دوخت پلی‌استر', 'kind' => 'thread', 'spec' => 'شماره ۱۲۰', 'unit' => 'قرقره', 'price' => 45000, 'stock' => 40, 'color' => 'مشکی'],
            ['name' => 'نخ دوخت پلی‌استر', 'kind' => 'thread', 'spec' => 'شماره ۱۲۰', 'unit' => 'قرقره', 'price' => 45000, 'stock' => 35, 'color' => 'سفید'],
            ['name' => 'دکمه صدفی', 'kind' => 'button', 'spec' => 'قطر ۱۲ میلی‌متر', 'unit' => 'عدد', 'price' => 8000, 'stock' => 220],
            ['name' => 'زیپ مخفی', 'kind' => 'zipper', 'spec' => '۵۰ سانتی‌متر', 'unit' => 'عدد', 'price' => 65000, 'stock' => 25],
            ['name' => 'زیپ فلزی', 'kind' => 'zipper', 'spec' => '۱۸ سانتی‌متر', 'unit' => 'عدد', 'price' => 48000, 'stock' => 30],
            ['name' => 'لایی چسب نرم', 'kind' => 'interfacing', 'spec' => 'عرض ۹۰', 'unit' => 'متر', 'price' => 95000, 'stock' => 20],
            ['name' => 'کشی کمر', 'kind' => 'elastic', 'spec' => 'عرض ۴ سانتی‌متر', 'unit' => 'متر', 'price' => 38000, 'stock' => 18],
        ];

        foreach ($rows as $row) {
            Material::create($row);
        }
    }

    /**
     * پروژه‌ها در مراحل مختلف: یکی کامل تا کارت فنی، یکی تا برش و یکی در ابتدای راه.
     *
     * @param  array<string, Customer>  $customers
     * @param  array<string, Fabric>  $fabrics
     * @return array<string, Project>
     */
    protected function projects(array $customers, array $fabrics): array
    {
        $projects = [];

        $projects['پیراهن'] = $this->buildProject(
            name: 'پیراهن کتان تابستانی',
            customer: $customers['نازنین'],
            garmentCode: 'shirt',
            templateCode: 'shirt-classic',
            fabric: $fabrics['کتان'],
            complete: true,
            notes: 'یقه کلاسیک، آستین بلند با مچ دکمه‌دار.',
        );

        $projects['دامن'] = $this->buildProject(
            name: 'دامن راسته اداری',
            customer: $customers['الهام'],
            garmentCode: 'skirt_straight',
            templateCode: 'skirt-pencil',
            fabric: $fabrics['گاباردین'],
            complete: false,
            withCutting: true,
            notes: 'چاک پشت ۱۵ سانتی‌متر، آستر کامل.',
        );

        $projects['پیراهن‌مجلسی'] = $this->buildProject(
            name: 'پیراهن مجلسی حریر',
            customer: $customers['یلدا'],
            garmentCode: 'dress',
            templateCode: 'dress-basic',
            fabric: $fabrics['حریر'],
            complete: false,
            withCutting: false,
            notes: 'پارچه شفاف است؛ آستر لازم دارد.',
        );

        // یک الگو در کتابخانه عمومی منتشر می‌شود تا بخش اشتراک‌گذاری خالی نباشد
        $projects['دامن']->pattern?->update(['is_published' => true]);

        // پروژه‌ای که تازه شروع شده و فقط مدل و اندازه دارد
        $projects['مانتو'] = Project::create([
            'customer_id' => $customers['مینا']->id,
            'garment_type_id' => GarmentType::where('code', 'manteau')->value('id'),
            'measurement_set_id' => $customers['مینا']->defaultMeasurementSet?->id,
            'name' => 'مانتو اداری بهاره',
            'size' => '46',
            'status' => 'draft',
            'step' => 3,
        ]);

        return $projects;
    }

    /** یک پروژه را با سرویس‌های واقعی سامانه تا مرحله خواسته‌شده جلو می‌برد. */
    protected function buildProject(
        string $name,
        Customer $customer,
        string $garmentCode,
        string $templateCode,
        Fabric $fabric,
        bool $complete,
        bool $withCutting = true,
        ?string $notes = null,
    ): Project {
        $garmentType = GarmentType::where('code', $garmentCode)->first();
        $measurementSet = $customer->defaultMeasurementSet;

        $project = Project::create([
            'customer_id' => $customer->id,
            'garment_type_id' => $garmentType?->id,
            'measurement_set_id' => $measurementSet?->id,
            'fabric_id' => $fabric->id,
            'name' => $name,
            'size' => $measurementSet?->base_size ?? '40',
            'status' => 'in_progress',
            'step' => 4,
            'notes' => $notes,
        ]);

        $template = PatternTemplate::where('code', $templateCode)->first();

        if ($template === null) {
            return $project;
        }

        /** @var Pattern $pattern */
        $pattern = app(PatternBuilder::class)->createForProject($project, $template);

        app(SeamAllowanceService::class)->apply($pattern);
        $pattern->update(['sewing_relations' => app(SewingRelationBuilder::class)->suggest($pattern)]);

        $project->update(['pattern_id' => $pattern->id, 'step' => 6]);
        $project->setRelation('pattern', $pattern);

        $fit = app(FitAnalysisService::class)->analyze(
            $pattern->fresh('pieces'),
            $fabric,
            $project->resolvedMeasurements(),
        );

        Simulation::create([
            'project_id' => $project->id,
            'pattern_id' => $pattern->id,
            'fabric_id' => $fabric->id,
            'measurement_set_id' => $measurementSet?->id,
            'pose' => 'stand',
            'params' => $fit['params'] ?? null,
            'zones' => $fit['zones'] ?? null,
            'warnings' => $fit['warnings'] ?? null,
            'suggestions' => $fit['suggestions'] ?? null,
            'fit_score' => $fit['fit_score'] ?? null,
        ]);

        $project->update(['step' => 8]);

        if ($withCutting || $complete) {
            $layout = app(NestingService::class)->nest($pattern->fresh('pieces'), $fabric);

            CuttingLayout::create([
                'project_id' => $project->id,
                'pattern_id' => $pattern->id,
                'fabric_id' => $fabric->id,
                'fabric_width_cm' => $layout['fabric_width_cm'],
                'required_length_cm' => $layout['required_length_cm'],
                'waste_percent' => $layout['waste_percent'],
                'efficiency' => $layout['efficiency'],
                'respect_nap' => $layout['options']['respect_nap'] ?? false,
                'match_stripes' => $layout['options']['match_stripes'] ?? false,
                'folded' => $layout['options']['folded'] ?? true,
                'placements' => $layout['placements'],
                'warnings' => $layout['warnings'] ?? [],
            ]);

            $project->update(['step' => 9]);
        }

        if ($complete) {
            app(TechPackBuilder::class)->store($project->fresh());
            $project->update(['status' => 'done', 'step' => 11]);
        }

        app(FitAnalysisService::class)->scorecard($project->fresh());

        return $project->fresh();
    }

    /**
     * سفارش‌ها در همه مراحل تخته تولید.
     *
     * @param  array<string, Customer>  $customers
     * @param  array<string, Project>  $projects
     * @param  array<string, Fabric>  $fabrics
     */
    protected function orders(Workshop $workshop, array $customers, array $projects, array $fabrics): void
    {
        $rows = [
            [
                'customer' => 'نازنین', 'project' => 'پیراهن', 'title' => 'پیراهن کتان تابستانی',
                'status' => 'ready', 'due' => '+3 days', 'price' => 2800000, 'deposit' => 1000000,
                'fabric' => 'کتان', 'meters' => 2.4, 'payment' => 800000,
            ],
            [
                'customer' => 'الهام', 'project' => 'دامن', 'title' => 'دامن راسته اداری',
                'status' => 'sewing', 'due' => '+6 days', 'price' => 1900000, 'deposit' => 700000,
                'fabric' => 'گاباردین', 'meters' => 1.3, 'payment' => null,
            ],
            [
                'customer' => 'یلدا', 'project' => 'پیراهن‌مجلسی', 'title' => 'پیراهن مجلسی حریر',
                'status' => 'cutting', 'due' => '+12 days', 'price' => 5200000, 'deposit' => 2000000,
                'fabric' => 'حریر', 'meters' => 3.2, 'payment' => null,
            ],
            [
                'customer' => 'مینا', 'project' => 'مانتو', 'title' => 'مانتو اداری بهاره',
                'status' => 'new', 'due' => '+18 days', 'price' => 4100000, 'deposit' => 1500000,
                'fabric' => 'کرپ', 'meters' => 2.8, 'payment' => null,
            ],
            [
                'customer' => 'شیرین', 'project' => null, 'title' => 'تعمیر و کوتاه کردن شلوار',
                'status' => 'delivered', 'due' => '-4 days', 'price' => 350000, 'deposit' => 350000,
                'fabric' => null, 'meters' => null, 'payment' => null,
            ],
            [
                'customer' => 'رضا', 'project' => null, 'title' => 'کت گاباردین سفارشی',
                'status' => 'fitting', 'due' => '-2 days', 'price' => 7800000, 'deposit' => 3000000,
                'fabric' => 'گاباردین', 'meters' => 3.4, 'payment' => 2000000,
            ],
        ];

        foreach ($rows as $index => $row) {
            $order = Order::create([
                'customer_id' => $customers[$row['customer']]->id,
                'project_id' => $row['project'] ? ($projects[$row['project']]->id ?? null) : null,
                'code' => Order::nextCode($workshop->id),
                'title' => $row['title'],
                'status' => $row['status'],
                'due_date' => now()->modify($row['due'])->format('Y-m-d'),
                'price' => $row['price'],
                'deposit' => $row['deposit'],
                'position' => $index,
                'delivered_at' => $row['status'] === 'delivered' ? now()->modify('-4 days') : null,
            ]);

            if ($row['fabric']) {
                $fabric = $fabrics[$row['fabric']];

                $order->items()->create([
                    'kind' => 'fabric',
                    'fabric_id' => $fabric->id,
                    'description' => $fabric->displayName(),
                    'quantity' => $row['meters'],
                    'unit' => 'متر',
                    'unit_price' => $fabric->price_per_meter,
                ]);
            }

            $order->items()->create([
                'kind' => 'service',
                'description' => 'دستمزد دوخت',
                'quantity' => 1,
                'unit' => 'عدد',
                'unit_price' => round($row['price'] * 0.45),
            ]);

            if ($row['payment']) {
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $row['payment'],
                    'method' => 'card',
                    'paid_at' => now()->modify('-3 days')->format('Y-m-d'),
                    'note' => 'پرداخت میان‌کار',
                ]);
            }
        }
    }
}
