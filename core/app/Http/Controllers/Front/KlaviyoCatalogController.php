<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\KlaviyoCatalogService;
use Illuminate\Http\Request;

class KlaviyoCatalogController extends Controller
{
    public function __invoke(Request $request, KlaviyoCatalogService $catalog)
    {
        if (! $catalog->authorized($request->query('token'))) {
            abort(404);
        }

        return response()->stream(function () use ($catalog) {
            echo '[';
            $first = true;

            $catalog->query()->chunkById(500, function ($items) use ($catalog, &$first) {
                foreach ($items as $item) {
                    if (! $first) {
                        echo ',';
                    }

                    echo json_encode(
                        $catalog->map($item),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    );
                    $first = false;
                }
            });

            echo ']';
        }, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
