<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\CustomerLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KlaviyoLifecycleController extends Controller
{
    public function vehicle(Request $request, CustomerLifecycleService $lifecycle): JsonResponse
    {
        $vehicle = $request->validate([
            'year' => ['required', 'integer', 'min:1886', 'max:'.(now()->year + 2)],
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
        ]);

        $lifecycle->updateVehicle($request->user(), $vehicle);

        return response()->json(['saved' => true]);
    }
}
