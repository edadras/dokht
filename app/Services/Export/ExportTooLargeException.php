<?php

namespace App\Services\Export;

use RuntimeException;

/**
 * درخواست خروجی از سقف مجاز بزرگ‌تر است (مثلاً PNG با dpi خیلی بالا).
 *
 * پیام این استثنا فارسی و برای نمایش مستقیم به کاربر نوشته می‌شود.
 */
class ExportTooLargeException extends RuntimeException {}
