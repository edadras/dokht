<?php

namespace App\Http\Controllers;

class OrderItemController extends Controller
{
    public function store()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }

    public function destroy()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }
}
