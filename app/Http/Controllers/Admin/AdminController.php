<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignRule;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Guide;
use App\Models\Order;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\User;
use App\Models\Workshop;
use App\Support\WorkshopContext;
use Illuminate\View\View;

/** خانه پنل مدیریت سامانه: یک نگاه کلی به داده‌های مشترک و کارگاه‌ها. */
class AdminController extends Controller
{
    public function __construct(protected WorkshopContext $context) {}

    public function __invoke(): View
    {
        return view('admin.index', $this->context->withoutScope(fn () => [
            'counts' => [
                'workshops' => Workshop::count(),
                'users' => User::count(),
                'fabric_types' => FabricType::count(),
                'garment_types' => GarmentType::count(),
                'templates' => PatternTemplate::count(),
                'rules' => DesignRule::count(),
                'guides' => Guide::count(),
                'patterns' => Pattern::acrossWorkshops()->count(),
                'orders' => Order::acrossWorkshops()->count(),
            ],
            'workshops' => Workshop::with('owner')
                ->withCount(['users', 'customers', 'patterns', 'orders'])
                ->latest('id')
                ->take(10)
                ->get(),
        ]));
    }
}
