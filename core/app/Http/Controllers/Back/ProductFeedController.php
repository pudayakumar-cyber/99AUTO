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