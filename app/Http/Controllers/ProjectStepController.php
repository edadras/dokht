<?php

namespace App\Http\Controllers;

class ProjectStepController extends Controller
{
    public function show()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }

    public function update()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }

    public function generatePattern()
    {
        abort(501, 'این بخش در حال ساخت است.');
    }
}
