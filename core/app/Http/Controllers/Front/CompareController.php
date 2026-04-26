<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Helpers\PriceHelper;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CompareController extends Controller
{

    public function __construct()
    {
        $this->middleware('localize');
    }
    
    public function compare($id)
    {


        if(Session::has('compare')){

            if(count(Session::get('compare')) <2){
                $compares = Session::get('compare');
                if(in_array($id,$compares)){
                $status = 0;
                $mgs = __('This product already added to compare');
                return response()->json(['message'=>$mgs,'status'=>$status]);
                }
                 array_push($compares,$id);
                Session::put('compare',$compares);
                $status = 1;
                $mgs = __('Compare added successfully');
            }else{
                $compares = Session::get('compare');
                $status = 0;
                $mgs = __('already added 2 compare product');
            }

        }else{
            $compares = array($id);
            Session::put('compare',$compares);
            $status = 1;
            $mgs = __('Compare added successfully');
        }

        return response()->json(['message'=>$mgs,'status'=>$status,'compare_count'=>count($compares)]);
    }


    public function compare_product()
    {

        if(Session::has('compare')){
            $sname = [];
            $sdesc = [];
            $ids = Session::get('compare');
            foreach($ids as $key => $id){
                $item = Item::findOrFail($id);
                $items[] = $item;
                if(!empty($item->specification_name)){
                    $sname =  array_unique(array_merge($sname,json_decode($item->specification_name,true)));
                    $sdesc[] =  json_decode($item->specification_description,true);
                }else{
                    $sname = [];
                    $sdesc = [];
                }
            }
        }else{
            $items = [];
            $sname = [];
            $sdesc = [];
        }
    
        return view('front.compare',[
            'items' => $items,
            'sname'  =>$sname,
            'sdesc'  => $sdesc
        ]);
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->input('q'));
        $selectedIds = Session::get('compare', []);

        if ($query === '') {
            return response()->json([
                'items' => [],
            ]);
        }

        $items = Item::with('brand')
            ->whereStatus(1)
            ->whereNotIn('id', $selectedIds)
            ->where(function ($searchQuery) use ($query) {
                $searchQuery
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('prod_number', 'like', "%{$query}%")
                    ->orWhere('product_part_number', 'like', "%{$query}%")
                    ->orWhereHas('brand', function ($brandQuery) use ($query) {
                        $brandQuery->where('name', 'like', "%{$query}%");
                    });
            })
            ->latest('id')
            ->take(8)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => collect([
                        optional($item->brand)->name ?: null,
                        $item->product_part_number ?: $item->prod_number ?: null,
                        $item->name,
                    ])->filter(fn ($value) => trim((string) $value) !== '')->implode(' - '),
                    'brand' => optional($item->brand)->name,
                    'price' => PriceHelper::grandCurrencyPrice($item),
                    'product_url' => route('front.product', $item->slug),
                    'compare_url' => route('fornt.compare.product', $item->id),
                    'image_url' => $this->resolveCompareImageUrl($item->thumbnail ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'items' => $items,
        ]);
    }



    public function compareRemove($itemId)
    {
        $ids = Session::get('compare');
        $newIds = [];
        foreach($ids as $id){
            if($itemId != $id){
                $newIds[] = $id;
            }
        }


        if(!count($newIds) == 0){
            Session::put('compare',$newIds);
            return true;
        }else{
            Session::forget('compare');
            return true;
        }


    }

    private function resolveCompareImageUrl(?string $rawPath): string
    {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        $pathOnly = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;
        if (preg_match('~/core/public/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }
        if (preg_match('~/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }

        $filename = basename((string) $pathOnly);
        if ($filename === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        return url('/core/public/storage/images/' . $filename);
    }
}
