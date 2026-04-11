<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class VehicleController extends Controller
{
     public function years()
    {
        return response()->json(
            DB::table('years')->orderBy('year', 'desc')->get()
        );
    }

    public function makes($yearId)
    {
        return response()->json(
            DB::table('makes')
                ->where('year_id', $yearId)
                ->orderBy('make')
                ->get()
        );
    }

    public function models($makeId)
    {
        return response()->json(
            DB::table('models')
                ->where('make_id', $makeId)
                ->orderBy('model')
                ->get()
        );
    }
}
