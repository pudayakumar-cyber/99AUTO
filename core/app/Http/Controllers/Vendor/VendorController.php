<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        abort(404);
    }
    public function __invoke(Request $request)
    {
        abort(404);
    }
}
