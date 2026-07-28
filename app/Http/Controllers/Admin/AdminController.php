<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminController extends Controller
{
    public function __invoke()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }
}
