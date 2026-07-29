<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductUpload;
use App\Jobs\ProcessProductUploadJob;
use App\Services\SpreadsheetCsvConverter;
use Throwable;

class ProductUploadController extends Controller
{
    public function index()
    {
        $uploads = ProductUpload::orderByDesc('id')->paginate(15, ['*'], 'uploads_page');

        return view('back.product-upload.index', compact('uploads'));
    }

    public function generate(Request $request, SpreadsheetCsvConverter $converter)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx'
        ]);

        $file = $request->file('file');

        $filename = time().'_'.$file->getClientOriginalName();

        $file->move(storage_path('app/uploads'), $filename);

        $path = 'uploads/'.$filename;
        $extension = strtolower($file->getClientOriginalExtension());

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

                return back()->withError(__('Unable to read the XLSX file. Please confirm the first worksheet contains product headers and try again.'));
            }

            $path = $csvPath;
        }

        $upload = ProductUpload::create([
            'file_path' => $path,
            'status' => 'pending'
        ]);

        ProcessProductUploadJob::dispatch($upload->id)->onQueue('imports');

        return back()->withSuccess(
            __('Upload queued. Run :cmd for imports to process.', ['cmd' => 'php artisan queue:work --queue=imports,default'])
        );
    }

    public function progress($id)
    {
        return ProductUpload::findOrFail($id);
    }
}
