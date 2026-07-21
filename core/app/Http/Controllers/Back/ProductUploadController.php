<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductUpload;
use App\Jobs\ProcessProductUploadJob;
use App\Services\SpreadsheetCsvConverter;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductUploadController extends Controller
{
    public function index()
    {
        $uploads = ProductUpload::orderByDesc('id')->paginate(15, ['*'], 'uploads_page');

        $chunkJobs = DB::table('jobs')
            ->select(['id', 'queue', 'attempts', 'reserved_at', 'available_at', 'created_at', 'payload'])
            ->where('payload', 'like', '%ProcessProductUploadChunkJob%')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'chunk_jobs_page');

        $batchRuns = DB::table('job_batches')
            ->select(['id', 'name', 'total_jobs', 'pending_jobs', 'failed_jobs', 'created_at', 'finished_at'])
            ->where('name', 'like', 'product-upload-%')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'batches_page');

        return view('back.product-upload.index', compact('uploads', 'chunkJobs', 'batchRuns'));
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
