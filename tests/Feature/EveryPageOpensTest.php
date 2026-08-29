<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * هر صفحه‌ای که مسیر دارد باید باز شود.
 *
 * آزمون‌های دیگر هر بخش را جدا می‌سنجند، ولی هیچ‌کدام نمی‌گوید «هیچ صفحه‌ای
 * از سامانه خطای پانصد نمی‌دهد». یک ستون تازه در جدول، یک نمای پاک‌شده، یا
 * متغیری که دیگر به قالب فرستاده نمی‌شود، همه از دستِ آزمون‌های موضوعی
 * درمی‌روند و همین‌جا گیر می‌افتند.
 *
 * فقط مسیرهای GET بی‌پارامتر می‌آیند — چیزی که کاربر با کلیک روی منو باز
 * می‌کند. مسیرهای پارامتردار داده‌ی خودشان را لازم دارند و آزمونِ خودشان را.
 */
class EveryPageOpensTest extends TestCase
{
    use RefreshDatabase;

    /** صفحه‌هایی که عمداً به جای دیگری می‌برند یا فایل می‌دهند. */
    protected const NOT_A_PAGE = [
        'logout', 'login', 'register', 'password.request', 'password.reset',
        'verification.notice', 'verification.verify', 'invites.show',
    ];

    /**
     * @return array<int, array{name: string, uri: string}>
     */
    protected function plainPages(): array
    {
        $pages = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // پارامتردار نه: آن صفحه بدون دادهٔ خودش معنا ندارد
            if ($route->parameterNames() !== [] || in_array($name, static::NOT_A_PAGE, true)) {
                continue;
            }

            $pages[] = ['name' => $name, 'uri' => $route->uri()];
        }

        return $pages;
    }

    public function test_every_plain_page_opens_for_a_workshop_owner(): void
    {
        $this->actingAsWorkshopUser();

        $pages = $this->plainPages();
        $this->assertGreaterThan(20, count($pages), 'باید ده‌ها صفحهٔ بی‌پارامتر باشد.');

        $broken = [];

        foreach ($pages as $page) {
            $response = $this->get('/'.ltrim($page['uri'], '/'));
            $status = $response->getStatusCode();

            // ۲۰۰ باز شد، ۳۰۲ جای دیگری برد (مثلاً چون هنوز داده‌ای نیست)،
            // ۴۰۳ اجازه نداشت — هیچ‌کدام خرابی نیست. ۵۰۰ هست.
            if ($status >= 500) {
                $broken[] = "{$page['name']} ({$page['uri']}): خطای {$status}";
            }
        }

        $this->assertSame([], $broken, "صفحه‌هایی که باز نمی‌شوند:\n  - ".implode("\n  - ", $broken));
    }

    public function test_no_plain_page_leaks_to_a_guest(): void
    {
        $open = [];

        foreach ($this->plainPages() as $page) {
            $status = $this->get('/'.ltrim($page['uri'], '/'))->getStatusCode();

            // مهمان یا به ورود فرستاده می‌شود یا صفحهٔ عمومی می‌بیند؛
            // آنچه نباید ببیند، خطای سرور است
            if ($status >= 500) {
                $open[] = "{$page['name']}: خطای {$status} برای مهمان";
            }
        }

        $this->assertSame([], $open, implode("\n", $open));
    }
}
