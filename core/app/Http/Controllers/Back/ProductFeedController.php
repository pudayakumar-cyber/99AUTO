<?php

namespace App\Http\Controllers\Back;

use App\{
    Models\ProductExport,
    Repositories\Back\ProductExportRepository,
    Http\Requests\ImageStoreRequest,
    Http\Requests\ImageUpdateRequest,
    Http\Controllers\Controller
};
use Illuminate\Http\Request;
use App\Jobs\GenerateProductFeedJob;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;

class ProductFeedController extends Controller
{
    public function __construct(ProductExportRepository $repository)
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
        $this->repository = $repository;
    }
    
    public function index()
    {
        $exports = ProductExport::latest()->get();
        return view('back.feeds.index', compact('exports'));
    }

    public function generate()
    {
        $export = ProductExport::create([
            'status' => 'pending'
        ]);

        GenerateProductFeedJob::dispatch($export->id);

        return response()->json([
            'export' => $export
        ]);
    }

    public function progress($id)
    {
        return ProductExport::findOrFail($id);
    }

    public function download($id)
    {
        $export = ProductExport::findOrFail($id);

        if ($export->status !== 'completed') {
            abort(404);
        }

        $filePath = storage_path('app/public/exports/' . $export->file_name);

        if (!file_exists($filePath)) {
            abort(404);
        }

        return response()->download($filePath);
    }

    public function downloadProductUrls(Request $request)
    {
        $fileName = 'product_urls_' . now()->format('Ymd_His') . '.csv';
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        return response()->streamDownload(function () use ($baseUrl) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'sku',
                'prod_number',
                'brand',
                'name',
                'slug',
                'product_url',
                'stock',
                'updated_at',
            ]);

            Item::with('brand')
                ->where('status', 1)
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->orderBy('id')
                ->chunk(1000, function ($items) use ($handle, $baseUrl) {
                    foreach ($items as $item) {
                        fputcsv($handle, [
                            $item->id,
                            $item->sku,
                            $item->prod_number,
                            optional($item->brand)->name,
                            $item->name,
                            $item->slug,
                            $baseUrl . '/product/' . ltrim($item->slug, '/'),
                            $item->stock,
                            optional($item->updated_at)->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadProductSitemap(Request $request)
    {
        $fileName = 'product_sitemap_' . now()->format('Ymd_His') . '.xml';
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        return response()->streamDownload(function () use ($baseUrl) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

            Item::where('status', 1)
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->orderBy('id')
                ->chunk(1000, function ($items) use ($baseUrl) {
                    foreach ($items as $item) {
                        $productUrl = htmlspecialchars($baseUrl . '/product/' . ltrim($item->slug, '/'), ENT_XML1, 'UTF-8');
                        $lastMod = optional($item->updated_at)->toAtomString();

                        echo "  <url>" . PHP_EOL;
                        echo "    <loc>{$productUrl}</loc>" . PHP_EOL;
                        if ($lastMod) {
                            echo "    <lastmod>{$lastMod}</lastmod>" . PHP_EOL;
                        }
                        echo "  </url>" . PHP_EOL;
                    }
                });

            echo '</urlset>' . PHP_EOL;
        }, $fileName, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function delete($id)
    {
        $export = ProductExport::findOrFail($id);

        // Delete file if exists
        $filePath = 'public/exports/' . $export->file_name;

        if ($export->file_name && Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        // Delete DB record
        $export->delete();

        return response()->json([
            'success' => true
        ]);
    }
}
