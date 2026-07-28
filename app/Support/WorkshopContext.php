<?php

namespace App\Support;

use App\Models\Workshop;

/**
 * کارگاه فعال درخواست جاری.
 *
 * همه داده‌های کارگاه از طریق همین کلاس محدود می‌شوند: در درخواست‌های وب از کاربر
 * وارد‌شده خوانده می‌شود و در دستورهای کنسول/سیدر خالی می‌ماند تا محدودیتی اعمال نشود.
 */
class WorkshopContext
{
    protected ?int $workshopId = null;

    protected bool $resolved = false;

    protected ?Workshop $workshop = null;

    public function set(Workshop|int|null $workshop): void
    {
        $this->workshop = $workshop instanceof Workshop ? $workshop : null;
        $this->workshopId = $workshop instanceof Workshop ? $workshop->id : $workshop;
        $this->resolved = true;
    }

    public function forget(): void
    {
        $this->workshop = null;
        $this->workshopId = null;
        $this->resolved = false;
    }

    public function id(): ?int
    {
        if (! $this->resolved) {
            $this->resolveFromAuth();
        }

        return $this->workshopId;
    }

    public function get(): ?Workshop
    {
        if ($this->workshop) {
            return $this->workshop;
        }

        $id = $this->id();

        return $this->workshop = $id ? Workshop::find($id) : null;
    }

    public function has(): bool
    {
        return $this->id() !== null;
    }

    /** اجرای یک بسته کد در حالت بدون محدودیت کارگاه (برای سیدر و پنل مدیریت). */
    public function withoutScope(callable $callback): mixed
    {
        $previousId = $this->workshopId;
        $previousWorkshop = $this->workshop;
        $previousResolved = $this->resolved;

        $this->workshop = null;
        $this->workshopId = null;
        $this->resolved = true;

        try {
            return $callback();
        } finally {
            $this->workshopId = $previousId;
            $this->workshop = $previousWorkshop;
            $this->resolved = $previousResolved;
        }
    }

    protected function resolveFromAuth(): void
    {
        $this->resolved = true;

        if (! app()->bound('auth')) {
            return;
        }

        $user = auth()->user();
        $this->workshopId = $user?->workshop_id;
    }
}
