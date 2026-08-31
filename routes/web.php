<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\FabricTypeController as AdminFabricTypeController;
use App\Http\Controllers\Admin\GarmentTypeController as AdminGarmentTypeController;
use App\Http\Controllers\Admin\GuideController as AdminGuideController;
use App\Http\Controllers\Admin\PatternTemplateController as AdminPatternTemplateController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CuttingLayoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignImportController;
use App\Http\Controllers\DesignRuleController;
use App\Http\Controllers\FabricBankController;
use App\Http\Controllers\FabricController;
use App\Http\Controllers\FittingController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MeasurementSetController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PatternComposerController;
use App\Http\Controllers\PatternController;
use App\Http\Controllers\PatternExportController;
use App\Http\Controllers\PatternVersionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStepController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TechPackController;
use App\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

/*
 * صفحه معرفی، ثبت‌نام و ورود
 */
Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    // بدون این محدودیت، یک اسکریپت می‌تواند بی‌نهایت کارگاه بسازد
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:8,60');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1');

    // بازیابی رمز؛ فرستادن لینک سخت‌گیرانه‌تر بسته می‌شود چون ایمیل می‌فرستد
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:5,60')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:10,60')->name('password.update');
});

// پذیرش دعوت همکار
Route::get('invites/{token}', [InviteController::class, 'show'])->name('invites.show');
Route::post('invites/{token}', [InviteController::class, 'accept'])->name('invites.accept');

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

/*
 * محیط کارگاه
 */
Route::middleware(['auth', 'workshop'])->group(function () {
    Route::get('panel', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');

    // حساب کاربری
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // مشتری‌ها و دفترچه اندازه
    Route::resource('customers', CustomerController::class);
    Route::get('customers/{customer}/measurements/create', [MeasurementSetController::class, 'create'])
        ->name('measurements.create');
    Route::post('customers/{customer}/measurements', [MeasurementSetController::class, 'store'])
        ->name('measurements.store');
    Route::get('measurements/{measurementSet}/edit', [MeasurementSetController::class, 'edit'])
        ->name('measurements.edit');
    Route::put('measurements/{measurementSet}', [MeasurementSetController::class, 'update'])
        ->name('measurements.update');
    Route::delete('measurements/{measurementSet}', [MeasurementSetController::class, 'destroy'])
        ->name('measurements.destroy');

    // سفارش‌ها
    Route::resource('orders', OrderController::class);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::post('orders/{order}/items', [OrderItemController::class, 'store'])->name('order-items.store');
    Route::delete('order-items/{orderItem}', [OrderItemController::class, 'destroy'])->name('order-items.destroy');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // پارچه‌های کارگاه و بانک پارچه
    Route::resource('fabrics', FabricController::class);
    Route::get('fabric-bank', [FabricBankController::class, 'index'])->name('fabric-bank.index');
    Route::get('fabric-bank/{fabricType}', [FabricBankController::class, 'show'])->name('fabric-bank.show');
    Route::post('fabric-bank/{fabricType}/add', [FabricBankController::class, 'store'])->name('fabric-bank.add');
    Route::resource('materials', MaterialController::class)->except('show');

    // قواعد طراحی و آموزش
    Route::resource('rules', DesignRuleController::class)->except('show')
        ->parameters(['rules' => 'designRule']);
    Route::get('guides', [GuideController::class, 'index'])->name('guides.index');
    Route::get('guides/{guide}', [GuideController::class, 'show'])->name('guides.show');

    // دستیار هوشمند
    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('assistant', [AssistantController::class, 'ask'])->name('assistant.ask');

    // الگوها
    // تصویر هر الگوی پایه جدا صدا زده می‌شود تا فهرست‌ها هزاران SVG را با خود
    // حمل نکنند؛ پیش از resource می‌آید وگرنه «templates» را شناسهٔ الگو می‌گیرد
    Route::get('patterns/templates/{template}/preview.svg', [PatternController::class, 'templatePreview'])
        ->name('patterns.templates.preview');
    // چهار نمای لباسِ دوخته‌شده برای همان مدل و همان اندازه، پیش از ساختِ الگو
    Route::get('patterns/templates/{template}/flats', [PatternController::class, 'templateFlats'])
        ->name('patterns.templates.flats');
    // پارامترهای همان مدلی که انتخاب شده، نه همهٔ مدل‌ها
    Route::get('patterns/templates/{template}/params', [PatternController::class, 'templateParams'])
        ->name('patterns.templates.params');
    // جستجوی زندهٔ فهرست الگوها؛ صفحه یک بسته می‌گیرد نه همهٔ کاتالوگ را
    Route::get('patterns/templates', [PatternController::class, 'templateSearch'])
        ->name('patterns.templates.search');
    Route::resource('patterns', PatternController::class);
    Route::get('patterns/{pattern}/editor', [PatternController::class, 'editor'])->name('patterns.editor');
    Route::put('patterns/{pattern}/geometry', [PatternController::class, 'updateGeometry'])->name('patterns.geometry');
    Route::post('patterns/{pattern}/pieces/{piece}/split', [PatternController::class, 'splitPiece'])
        ->name('patterns.pieces.split');
    Route::post('patterns/{pattern}/duplicate', [PatternController::class, 'duplicate'])->name('patterns.duplicate');
    Route::post('patterns/{pattern}/grade', [PatternController::class, 'grade'])->name('patterns.grade');
    Route::post('patterns/{pattern}/publish', [PatternController::class, 'publish'])->name('patterns.publish');
    Route::get('patterns/{pattern}/print', [PatternExportController::class, 'print'])->name('patterns.print');
    Route::get('patterns/{pattern}/export/{format}', [PatternExportController::class, 'export'])
        ->whereIn('format', ['svg', 'dxf', 'aama', 'astm', 'png', 'pdf', 'json', 'sew3d'])->name('patterns.export');
    Route::get('patterns/{pattern}/versions', [PatternVersionController::class, 'index'])->name('patterns.versions');
    Route::post('patterns/{pattern}/versions', [PatternVersionController::class, 'store'])
        ->name('patterns.versions.store');
    Route::post('patterns/{pattern}/versions/{version}/restore', [PatternVersionController::class, 'restore'])
        ->name('patterns.versions.restore');

    // ترکیب مدل: بالاتنه + آستین + دامن + یقه در یک الگو
    Route::get('compose', [PatternComposerController::class, 'create'])->name('patterns.compose');
    Route::post('compose', [PatternComposerController::class, 'store'])->name('patterns.compose.store');
    Route::get('compose/preview', [PatternComposerController::class, 'preview'])->name('patterns.compose.preview');
    // فهرست مدل‌ها بسته‌بسته و جستجویش از سرور می‌آید، نه همه‌اش در خود صفحه
    Route::get('compose/models', [PatternComposerController::class, 'pickerSearch'])
        ->name('patterns.compose.models');
    // بندانگشتی هر مدل جدا صدا زده می‌شود تا صفحه هزاران نقشه را با خودش حمل نکند
    Route::get('compose/thumb/{group}/{key}', [PatternComposerController::class, 'thumb'])
        ->where('key', '[A-Za-z0-9_\-]+')
        ->name('patterns.compose.thumb');

    // ساخت الگو از روی عکس لباس یا طرح دستی
    Route::get('design-import', [DesignImportController::class, 'create'])->name('design-import.create');
    Route::post('design-import/photo', [DesignImportController::class, 'photo'])->name('design-import.photo');
    Route::post('design-import/sketch', [DesignImportController::class, 'sketch'])->name('design-import.sketch');
    Route::post('design-import/apply', [DesignImportController::class, 'apply'])->name('design-import.apply');

    // بازارچه الگو
    Route::get('market', [MarketplaceController::class, 'index'])->name('market.index');
    Route::get('market/purchases', [MarketplaceController::class, 'purchases'])->name('market.purchases');
    Route::get('market/sales', [MarketplaceController::class, 'sales'])->name('market.sales');
    Route::post('market/listings', [MarketplaceController::class, 'store'])->name('market.listings.store');
    Route::get('market/{listing}', [MarketplaceController::class, 'show'])->name('market.show');
    Route::patch('market/{listing}', [MarketplaceController::class, 'update'])->name('market.listings.update');
    Route::delete('market/{listing}', [MarketplaceController::class, 'destroy'])->name('market.listings.destroy');
    Route::post('market/{listing}/order', [MarketplaceController::class, 'order'])->name('market.order');
    Route::post('market/purchases/{purchase}/confirm', [MarketplaceController::class, 'confirm'])
        ->name('market.purchases.confirm');
    Route::post('market/purchases/{purchase}/cancel', [MarketplaceController::class, 'cancel'])
        ->name('market.purchases.cancel');
    Route::post('market/purchases/{purchase}/copy', [MarketplaceController::class, 'copy'])
        ->name('market.purchases.copy');

    // کتابخانه مدل‌ها و الگوهای منتشرشده
    Route::get('library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('library/{pattern}', [LibraryController::class, 'show'])->name('library.show');
    Route::post('library/{pattern}/copy', [LibraryController::class, 'copy'])->name('library.copy');

    // پروژه‌ها و گام‌های کار
    Route::resource('projects', ProjectController::class);
    Route::get('projects/{project}/step/{step}', [ProjectStepController::class, 'show'])->name('projects.step');
    Route::put('projects/{project}/step/{step}', [ProjectStepController::class, 'update'])
        ->name('projects.step.update');
    Route::post('projects/{project}/pattern', [ProjectStepController::class, 'generatePattern'])
        ->name('projects.pattern.generate');
    Route::post('projects/{project}/simulate', [SimulationController::class, 'store'])->name('projects.simulate');
    Route::get('simulations/{simulation}', [SimulationController::class, 'show'])->name('simulations.show');
    Route::post('projects/{project}/cutting', [CuttingLayoutController::class, 'store'])->name('projects.cutting');
    Route::get('cutting-layouts/{cuttingLayout}', [CuttingLayoutController::class, 'show'])
        ->name('cutting-layouts.show');
    Route::get('cutting-layouts/{cuttingLayout}/print', [CuttingLayoutController::class, 'print'])
        ->name('cutting-layouts.print');
    // پرو: ثبت آنچه روی تن مشتری دیده شد و برگرداندنش به الگو
    Route::get('projects/{project}/fittings', [FittingController::class, 'index'])->name('projects.fittings');
    Route::post('projects/{project}/fittings', [FittingController::class, 'store'])->name('projects.fittings.store');
    Route::post('projects/{project}/fittings/{fitting}/apply', [FittingController::class, 'apply'])
        ->name('projects.fittings.apply');
    Route::delete('projects/{project}/fittings/{fitting}', [FittingController::class, 'destroy'])
        ->name('projects.fittings.destroy');

    Route::get('projects/{project}/tech-pack', [TechPackController::class, 'show'])->name('tech-packs.show');
    Route::post('projects/{project}/tech-pack', [TechPackController::class, 'store'])->name('tech-packs.store');
    Route::get('projects/{project}/tech-pack/print', [TechPackController::class, 'print'])->name('tech-packs.print');
    Route::get('projects/{project}/export', [TechPackController::class, 'export'])->name('projects.export');

    // گزارش‌ها
    Route::get('reports', ReportController::class)->name('reports.index');

    // تنظیمات کارگاه و اعضا
    Route::middleware('can:administer-workshop')->group(function () {
        // پشتیبان کامل کارگاه؛ دادهٔ این سامانه سرمایه یک کسب‌وکار است
        Route::get('workshop/backup', [WorkshopController::class, 'backup'])->name('workshop.backup');
        Route::get('workshop/activity', [ActivityController::class, 'index'])->name('workshop.activity');

        Route::get('workshop/settings', [WorkshopController::class, 'edit'])->name('workshop.edit');
        Route::patch('workshop/settings', [WorkshopController::class, 'update'])->name('workshop.update');
        Route::get('workshop/members', [WorkshopController::class, 'members'])->name('workshop.members');
        Route::post('workshop/invites', [WorkshopController::class, 'invite'])->name('workshop.invites.store');
        Route::delete('workshop/invites/{invite}', [WorkshopController::class, 'cancelInvite'])
            ->name('workshop.invites.destroy');
        Route::patch('workshop/members/{user}', [WorkshopController::class, 'updateMember'])
            ->name('workshop.members.update');
        Route::delete('workshop/members/{user}', [WorkshopController::class, 'removeMember'])
            ->name('workshop.members.destroy');
    });

    /*
     * پنل مدیریت سامانه: بانک پارچه، انواع لباس، الگوهای پایه و آموزش
     */
    Route::middleware('can:administer-system')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminController::class)->name('index');
        Route::resource('fabric-types', AdminFabricTypeController::class)->except('show');
        Route::resource('garment-types', AdminGarmentTypeController::class)->except('show');
        Route::resource('templates', AdminPatternTemplateController::class)->except('show')
            ->parameters(['templates' => 'patternTemplate']);
        Route::resource('guides', AdminGuideController::class)->except('show');
    });
});
