<?php

namespace App\Services\Marketplace;

use RuntimeException;

/**
 * قاعده‌ای از بازارچه شکسته شده است.
 *
 * پیام این استثنا همیشه فارسی و قابل نمایش به کاربر است؛ کنترلر آن را به پیام
 * خطای صفحه تبدیل می‌کند. دسترسی غیرمجاز از این راه نمی‌آید — آن با 403/404 پاسخ
 * داده می‌شود.
 */
class MarketplaceException extends RuntimeException {}
