<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductUpload;
use App\Jobs\ProcessProductUploadJob;
use App\Services\SpreadsheetCsvConverter;
use App\Services\ItemCsvImporter;
use Illuminate\Support\Facades\File;
use Throwable;

class ProductUploadController extends Controller
{
    public function index()
    {
        return redirect()->route('back.uploads.new');
    }

    public function newProducts()
    {
        return $this->renderUploader(ItemCsvImporter::MODE_CREATE);
    }

    public function updateProducts()
    {
        return $this->renderUploader(ItemCsvImporter::MODE_UPDATE);
    }

    public function generateNew(Request $request, SpreadsheetCsvConverter $converter)
    {
        return $this->queueUpload($request, $converter, ItemCsvImporter::MODE_CREATE);
    }

    public function generateUpdate(Request $request, SpreadsheetCsvConverter $converter)
    {
        return $this->queueUpload($request, $converter, ItemCsvImporter::MODE_UPDATE);
    }

    public function downloadTemplate(string $mode)
    {
        abort_unless(in_array($mode, ItemCsvImporter::MODES, true), 404);

        $path = resource_path("import-templates/products-{$mode}-example.csv");
        abort_unless(File::isFile($path), 404);

        return response()->download($path, "products-{$mode}-example.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function renderUploader(string $mode)
    {
        $uploads = ProductUpload::where('import_mode', $mode)
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'uploads_page');

        return view('back.product-upload.index', compact('uploads', 'mode'));
    }

    private function queueUpload(Request $request, SpreadsheetCsvConverter $converter, string $mode)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:102400',
            'confirm_rules' => 'accepted',
        ]);

        $file = $request->file('file');

        $filename = time().'_'.bin2hex(random_bytes(4)).'_'.$file->getClientOriginalName();

        $file->move(storage_path('app/uploads'), $filename);

        $path = 'uploads/'.$filename;
        $extension = strtolower($file->getClientOriginalExtension());

        $storedPaths = [$path];
        if ($extension === 'xlsx') {
            $csvFilename = pathinfo($filename, PATHINFO_FILENAME).'.csv';
            $csvPath = 'uploads/'.$csvFilename;

            try {
                $converter->convertXlsxToCsv(
                    storage_path('app/'.$path),
                    storage_path('app/'.$csvPath)
                );
            } catch (Throwable $e) {
                report($e);
                $this->removeStoredFiles($storedPaths);

                return back()->withError(__('Unable to read the XLSX file. Please confirm the first worksheet contains product headers and try again.'));
            }

            $path = $csvPath;
            $storedPaths[] = $csvPath;
        }

        $header = $this->readCsvHeader(storage_path('app/'.$path));
        $headerErrors = ItemCsvImporter::validateHeaders($header, $mode);
        if ($headerErrors !== []) {
            $this->removeStoredFiles($storedPaths);

            return back()->withInput()->withError(implode(' ', $headerErrors));
        }

        $upload = ProductUpload::create([
            'file_path' => $path,
            'import_mode' => $mode,
            'status' => 'pending',
        ]);

        ProcessProductUploadJob::dispatch($upload->id)->onQueue('imports');

        return back()->withSuccess(
            __($mode === ItemCsvImporter::MODE_CREATE
                ? 'New-product file queued. Existing products will be skipped and never updated.'
                : 'Product-update file queued. Unknown Item IDs will be skipped and no products will be created.')
        );
    }

    private function readCsvHeader(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        try {
            return fgetcsv($handle) ?: [];
        } finally {
            fclose($handle);
        }
    }

    private function removeStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            File::delete(storage_path('app/'.$path));
        }
    }

    public function progress($id)
    {
        return ProductUpload::findOrFail($id);
    }
}
