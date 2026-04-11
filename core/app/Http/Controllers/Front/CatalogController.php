<?php

namespace App\Http\Controllers\Front;

use Illuminate\{
    Http\Request,
};

use App\{
    Models\Item,
    Models\Category,
    Http\Controllers\Controller,
};
use App\Helpers\PriceHelper;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\ChieldCategory;
use App\Models\Setting;
use App\Models\Subcategory;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function __construct()
    {
        $this->middleware('localize');
    }

	public function index(Request $request)
	{

        $year  = $request->year;
        $make  = $request->make;
        $model = $request->model;

        // attribute search
        $attr_item_ids = [];
        if($request->attribute){
            $attrubutes_get = Attribute::where('name',$request->attribute)->get();
            foreach($attrubutes_get as $attr_item_id){
                $attr_item_ids[] = $attr_item_id->item_id;
            }
        }

        $option_attr_ids = [];

        if($request->option){
            $option_get = AttributeOption::whereIn('name',explode(',',$request->option))->get();
            foreach($option_get as $option_attr_id){
                $option_attr_ids[] = $option_attr_id->attribute_id;
            }
        }


        $option_wise_item_ids = [];
        foreach(Attribute::whereIn('id',$option_attr_ids)->get() as $attr_item_id){
            $option_wise_item_ids[] = $attr_item_id->item_id;
        }
        $setting = Setting::first();

        $sorting = $request->has('sorting') ?  ( !empty($request->sorting) ? $request->sorting : null ) : null;
        $new = $request->has('new') ?  ( !empty($request->new) ? 1 : null ) : null;
        $feature = $request->has('quick_filter') ?  ( !empty($request->quick_filter == 'feature') ? 1 : null ) : null;
        $top = $request->has('quick_filter') ?  ( !empty($request->quick_filter == 'top') ? 1 : null ) : null;
        $best = $request->has('quick_filter') ?  ( !empty($request->quick_filter == 'best') ? 1 : null ) : null;
        $new = $request->has('quick_filter') ?  ( !empty($request->quick_filter == 'new') ? 1 : null ) : null;
        $brand = $request->has('brand') ?  ( !empty($request->brand) ? Brand::whereSlug($request->brand)->firstOrFail() : null ) : null;
        $search = $request->has('search') ?  ( !empty($request->search) ? $request->search : null ) : null;

        $category = $request->has('category') ? ( !empty($request->category) ? Category::whereSlug($request->category)->firstOrFail() : null ) : null;
        $subcategory = $request->has('subcategory') ? ( !empty($request->subcategory) ? Subcategory::whereSlug($request->subcategory)->firstOrFail() : null ) : null;
        $childcategory = $request->has('childcategory') ? ( !empty($request->childcategory) ? ChieldCategory::where('slug',$request->childcategory)->first() : null ) : null;
        $minPrice = $request->has('minPrice') ?  ( !empty($request->minPrice) ? PriceHelper::convertPrice($request->minPrice) : null ) : null;
        $maxPrice = $request->has('maxPrice') ?  ( !empty($request->maxPrice) ? PriceHelper::convertPrice($request->maxPrice) : null ) : null;
        $tag = $request->has('tag') ?  ( !empty($request->tag) ? $request->tag : null ) : null;
        $items = Item::with(['category', 'brand'])
        
        ->when($category, function ($query, $category) {
            return $query->where('category_id', $category->id);
        })
        ->when($subcategory, function ($query, $subcategory) {
            return $query->where('subcategory_id', $subcategory->id);
        })
        ->when($childcategory, function ($query, $childcategory) {
            return $query->where('childcategory_id', $childcategory->id);
        })

        ->when($feature, function ($query) {
            return $query->whereIsType('feature');
        })

        ->when($tag, function ($query, $tag) {
            return $query->where('tags', 'like', '%' . $tag . '%');
        })
      

        ->when($new, function ($query) {
            return $query->orderby('id','desc');
        })
        ->when($top, function ($query) {
            return $query->whereIsType('top');
        })
        ->when($best, function ($query) {
            return $query->whereIsType('best');
        })
        ->when($new, function ($query) {
            return $query->whereIsType('new');
        })

        ->when($brand, function ($query, $brand) {
            return $query->where('brand_id', $brand->id);
        })
        ->when($search, function ($query, $search) {
            return $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('prod_number', 'like', '%' . $search . '%')
                    ->orWhere('tags', 'like', '%' . $search . '%')
                    ->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('subcategory', function ($subcategoryQuery) use ($search) {
                        $subcategoryQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('childcategory', function ($childcategoryQuery) use ($search) {
                        $childcategoryQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        })
        ->when($minPrice, function($query, $minPrice) {
          return $query->where('discount_price', '>=', $minPrice);
        })

        ->when($maxPrice, function($query, $maxPrice) {
          return $query->where('discount_price', '<=', $maxPrice);
        })

        ->when($sorting, function($query, $sorting) {
            if($sorting == 'low_to_high'){
                return $query->orderby('discount_price','asc');
            }else{
                return $query->orderby('discount_price','desc');
            }

        })

        ->when($attr_item_ids, function($query, $attr_item_ids) {
          return $query->whereIn('id',$attr_item_ids);
        })
        ->when($option_wise_item_ids, function($query, $option_wise_item_ids) {
          return $query->whereIn('id',$option_wise_item_ids);
        })

        ->where('status',1)

        // ->orderby('id','desc')->paginate($setting->view_product);
        ->orderby('id','desc')
        ->get();
        $items = $this->filterItemsByFitment($items, $year, $make, $model);
        $items = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->forPage(
                \Illuminate\Pagination\Paginator::resolveCurrentPage(),
                $setting->view_product
            ),
            $items->count(),
            $setting->view_product,
            null,
            [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );

        $attrubutes_check =[];
       
        $options = AttributeOption::groupby('name')->select('attribute_id','name','id','keyword')->get();
        
        foreach($options as $option){
            if(!in_array(Attribute::withCount('options')->findOrFail($option->attribute_id)->keyword,$attrubutes_check)){
                $attrubutes_check[] = Attribute::withCount('options')->findOrFail($option->attribute_id)->keyword;
            }
        }

        
        $attrubutes = [];

        foreach($attrubutes_check as $attr_new_get){
            $attrubutes[] = Attribute::whereKeyword($attr_new_get)->first();
        }
      
        $blade = 'front.catalog.index';

        if($request->view_check){
            Session::put('view_catalog',$request->view_check);

        }

        if(Session::has('view_catalog')){
            $checkType = Session::get('view_catalog');
            $name_string_count = 55;
        }else{
            Session::put('view_catalog','grid');
            $checkType = Session::get('view_catalog');
            $name_string_count = 38;
        }


        if($request->ajax()) $blade = 'front.catalog.catalog';

        return view($blade,[
            'attrubutes' => $attrubutes,
            'options' => $options,
            'brand' => $brand,
            'brand' => $brand,
            'brand' => $brand,
            'items' => $items,
            'name_string_count' => $name_string_count,
            'category' => $category,
            'subcategory' => $subcategory,
            'childcategory' => $childcategory,
            'checkType'  => $checkType,
            'brands' => Brand::withCount('items')->whereStatus(1)->get(),
            'categories' => Category::whereStatus(1)->orderby('serial','asc')->withCount(['items' => function($query) {
                $query->where('status',1);
            }])->get(),
        ]);
	}


    public function viewType($type)
    {
        Session::put('view_catalog',$type);
        return response()->json($type);
    }


    public function suggestSearch(Request $request)
    {
        $category = $request->category
            ? Category::whereSlug($request->category)->first()
            : null;

        $search = $request->search;
        $year   = $request->year;
        $make   = $request->make;
        $model  = $request->model;

        // 1️⃣ BROAD QUERY (cheap, safe)
        $items = Item::with(['brand', 'category', 'subcategory', 'childcategory', 'reviews'])
            ->whereStatus(1)

            ->when($search, function ($q) use ($search) {
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('prod_number', 'like', "%{$search}%")
                        ->orWhere('tags', 'like', "%{$search}%")
                        ->orWhereHas('brand', function ($brandQuery) use ($search) {
                            $brandQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('subcategory', function ($subcategoryQuery) use ($search) {
                            $subcategoryQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('childcategory', function ($childcategoryQuery) use ($search) {
                            $childcategoryQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })

            ->when($category, fn ($q) =>
                $q->where('category_id', $category->id)
            )

            // optional pre-filter to reduce rows
            ->when($year,  fn ($q) => $q->where('details', 'like', "%{$year}%"))
            ->when($make,  fn ($q) => $q->where('details', 'like', "%{$make}%"))
            ->when($model, fn ($q) => $q->where('details', 'like', "%{$model}%"))

            ->orderByDesc('id')
            ->take(30) // small buffer
            ->get();

        // 2️⃣ STRICT PHP FILTER (exact same-row fitment match)
        $items = $this->filterItemsByFitment($items, $year, $make, $model);

        // final limit
        $items = $items->take(10);

        return view('includes.search_suggest', compact('items'));
    }

    private function filterItemsByFitment($items, $year, $make, $model)
    {
        if (!($year || $make || $model)) {
            return $items;
        }

        return $items->filter(function ($item) use ($year, $make, $model) {

            if (! $item->details) {
                return false;
            }

            $year = $this->normalizeFitmentToken($year);
            $make = $this->normalizeFitmentToken($make);
            $model = $this->normalizeFitmentToken($model);

            // Prefer the normalized fitment table to avoid matching arbitrary 3-column tables.
            $details = (string) $item->details;
            $rowsSource = $details;
            if (preg_match('/<table[^>]*class="[^"]*\bpa-fitment-table\b[^"]*"[^>]*>[\s\S]*?<\/table>/i', $details, $m)) {
                $rowsSource = $m[0];
            }

            preg_match_all('/<tr>(.*?)<\/tr>/si', $rowsSource, $rows);

            foreach ($rows[1] as $rowHtml) {

                preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);

                if (count($cols[1]) !== 3) {
                    continue;
                }

                [$yearsCell, $makeCell, $modelCell] = array_map(
                    fn ($v) => $this->normalizeFitmentToken(strip_tags((string) $v)),
                    $cols[1]
                );

                // YEAR
                if ($year) {
                    $years = array_map(
                        fn ($v) => $this->normalizeFitmentToken($v),
                        explode(',', (string) $yearsCell)
                    );
                    if (! in_array($year, $years, true)) {
                        continue;
                    }
                }

                // MAKE
                if ($make && strcasecmp($makeCell, $make) !== 0) {
                    continue;
                }

                // MODEL
                if ($model && strcasecmp($modelCell, $model) !== 0) {
                    continue;
                }

                return true; // ✅ SAME ROW MATCH
            }

            return false;
        });
    }

    private function normalizeFitmentToken(?string $value): string
    {
        return Str::of((string) $value)
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->lower()
            ->toString();
    }

}
