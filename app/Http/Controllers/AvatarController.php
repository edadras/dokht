<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Services\Simulation\AvatarFitService;
use Illuminate\Http\JsonResponse;

/**
 * آواتارِ سه‌بعدی، به اندازهٔ تنِ همین الگو.
 *
 * صفحهٔ الگو با ‎?avatar=نام این را می‌خواند: آواتار به اندازه‌های الگو پخته
 * می‌شود (یا از cache می‌آید) و مرورگر همان را به‌جای مانکنِ محاسباتی می‌کشد و
 * لباس را رویش می‌دوزد.
 */
class AvatarController extends Controller
{
    public function __construct(protected AvatarFitService $fitter)
    {
    }

    public function fit(string $name, Pattern $pattern): JsonResponse
    {
        $fit = $this->fitter->fit($name, (array) ($pattern->measurements ?? []));

        if ($fit === null) {
            return response()->json(['ok' => false, 'note' => 'آواتار پیدا نشد یا به اندازه درنیامد.'], 404);
        }

        return response()->json(['ok' => true, ...$fit]);
    }
}
