<?php

namespace App\Http\Controllers;

use App\Models\FabricType;
use App\Models\GarmentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('home', [
            'fabricCount' => FabricType::active()->count(),
            'garmentCount' => GarmentType::active()->count(),
        ]);
    }
}
