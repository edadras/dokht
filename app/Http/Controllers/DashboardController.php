<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function __invoke()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }
}
