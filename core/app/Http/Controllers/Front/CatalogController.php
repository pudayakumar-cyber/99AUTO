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
use Illuminate\Support\Facades\Cache;
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
        $attr_item_ids = $request->attribute
            ? Attribute::where('name', $request->attribute)->pluck('item_id')->all()
            : [];

        $option_attr_ids = $request->option
            ? AttributeOption::whereIn('name', explode(',', $request->option))->pluck('attribute_id')->all()
            : [];

        $option_wise_item_ids = $option_attr_ids
            ? Attribute::whereIn('id', $option_attr_ids)->pluck('item_id')->all()
            : [];
        $setting = Setting::first();
        $perPage = 16;

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
        $itemsQuery = Item::with(['category', 'brand'])
        ->withAvg('reviews', 'rating')
        
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
        ->orderby('id','desc');

        if ($year || $make || $model) {
            $items = $itemsQuery->get();
            $items = $this->filterItemsByFitment($items, $year, $make, $model);
            $items = new \Illuminate\Pagination\LengthAwarePaginator(
                $items->forPage(
                    \Illuminate\Pagination\Paginator::resolveCurrentPage(),
                    $perPage
                ),
                $items->count(),
                $perPage,
                \Illuminate\Pagination\Paginator::resolveCurrentPage(),
                [
                    'path'  => request()->url(),
                    'query' => request()->query(),
                ]
            );
        } else {
            $items = $itemsQuery->paginate($perPage)->appends($request->query());
        }

        if(Session::has('view_catalog')){
            $checkType = Session::get('view_catalog');
        }else{
            Session::put('view_catalog','grid');
            $checkType = Session::get('view_catalog');
        }

        if($request->view_check){
            Session::put('view_catalog',$request->view_check);
            $checkType = Session::get('view_catalog');
        }

        if ($request->filled('catalog_chunk')) {
            $chunk = max(1, (int) $request->input('catalog_chunk', 1));
            $chunkSize = max(1, (int) $request->input('catalog_chunk_size', 4));
            $itemsChunk = $items->getCollection()->slice(($chunk - 1) * $chunkSize, $chunkSize)->values();

            return view('front.catalog.chunk-items', [
                'itemsChunk' => $itemsChunk,
                'checkType' => $checkType,
            ]);
        }

        $options = Cache::remember('catalog_sidebar_options', 1800, function () {
            return AttributeOption::with('attribute:id,keyword')
                ->select('attribute_id', 'name', 'id', 'keyword')
                ->groupBy('attribute_id', 'name', 'id', 'keyword')
                ->get();
        });

        $attrubutes = Cache::remember('catalog_sidebar_attributes', 1800, function () {
            $attributeIds = AttributeOption::query()
                ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')
                ->selectRaw('MIN(attributes.id) as id')
                ->groupBy('attributes.keyword')
                ->pluck('id');

            return Attribute::withCount('options')
                ->whereIn('id', $attributeIds)
                ->get();
        });
      
        $blade = 'front.catalog.index';

        $name_string_count = $checkType === 'list' ? 55 : 38;


        if($request->ajax()) $blade = 'front.catalog.catalog';

        return view($blade,[
            'attrubutes' => $attrubutes,
            'options' => $options,
            'brand' => $brand,
            'items' => $items,
            'name_string_count' => $name_string_count,
            'category' => $category,
            'subcategory' => $subcategory,
            'childcategory' => $childcategory,
            'checkType'  => $checkType,
            'view_product' => $perPage,
            'brands' => Cache::remember('catalog_sidebar_brands', 1800, function () {
                return Brand::withCount('items')->whereStatus(1)->get();
            }),
            'categories' => Cache::remember('catalog_sidebar_categories', 1800, function () {
                return Category::whereStatus(1)
                    ->orderby('serial','asc')
                    ->with([
                        'subcategory.childcategory',
                    ])
                    ->withCount(['items' => function($query) {
                        $query->where('status',1);
                    }])
                    ->get();
            }),
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
        $items = Item::with(['brand', 'category', 'subcategory', 'childcategory'])
            ->withAvg('reviews', 'rating')
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
