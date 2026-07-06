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
            return $this->applySmartSearch($query, $search);
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
            $matchedFitmentIds = $this->getFitmentMatchedItemIds($itemsQuery, $request, $year, $make, $model);

            $items = empty($matchedFitmentIds)
                ? $itemsQuery->whereRaw('1 = 0')->paginate($perPage)->appends($request->query())
                : $itemsQuery->whereIn('id', $matchedFitmentIds)->paginate($perPage)->appends($request->query());
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

        $itemsQuery = Item::with(['brand', 'category', 'subcategory', 'childcategory'])
            ->withAvg('reviews', 'rating')
            ->whereStatus(1)
            ->when($search, function ($query) use ($search) {
                $this->applySmartSearch($query, $search);
            })
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->orderByDesc('id');

        if ($year || $make || $model) {
            $matchedFitmentIds = $this->getFitmentMatchedItemIds($itemsQuery, $request, $year, $make, $model);
            $items = empty($matchedFitmentIds)
                ? collect()
                : (clone $itemsQuery)->whereIn('id', $matchedFitmentIds)->take(30)->get()->take(10);
        } else {
            $items = $itemsQuery->take(30)->get()->take(10);
        }

        return view('includes.search_suggest', compact('items'));
    }

    public function debugFitment(Request $request)
    {
        $year  = $request->year;
        $make  = $request->make;
        $model = $request->model;
        $search = $request->search;

        $itemsQuery = Item::query()->where('status', 1);

        if ($search) {
            $this->applySmartSearch($itemsQuery, $search);
        }

        $allCount = (clone $itemsQuery)->count();

        $candidateQuery = clone $itemsQuery;
        if ($year || $make || $model) {
            $this->applyFitmentKeywordPrefilter($candidateQuery, $year, $make, $model);
        }

        $candidatesCount = (clone $candidateQuery)->count();
        $candidates = $candidateQuery->select('id', 'name', 'details')->get();

        $filtered = $this->filterItemsByFitment($candidates, $year, $make, $model);

        $out = [
            'input' => [
                'year' => $year,
                'make' => $make,
                'model' => $model,
                'search' => $search,
            ],
            'total_items_in_db' => Item::count(),
            'total_active_items' => Item::where('status', 1)->count(),
            'search_filtered_count' => $allCount,
            'fitment_prefilter_count' => $candidatesCount,
            'final_filtered_count' => $filtered->count(),
            'final_filtered_items' => $filtered->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])->values()->all(),
        ];

        return response()->json($out, 200, [], JSON_PRETTY_PRINT);
    }

    public function debugItem($id)
    {
        $item = Item::findOrFail($id);
        
        $details = $item->details;
        $rowsSource = $details;
        if (preg_match('/<table[^>]*class="[^"]*\bpa-fitment-table\b[^"]*"[^>]*>[\s\S]*?<\/table>/i', $details, $m)) {
            $rowsSource = $m[0];
        }

        preg_match_all('/<tr>(.*?)<\/tr>/si', $rowsSource, $rows);

        $parsedRows = [];
        foreach ($rows[1] as $i => $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);
            if (count($cols[1]) !== 3) {
                $parsedRows[] = [
                    'index' => $i,
                    'html' => $rowHtml,
                    'status' => 'skipped (cols count not 3)',
                ];
                continue;
            }

            [$yearsCell, $makeCell, $modelCell] = array_map(
                fn ($v) => trim(html_entity_decode(strip_tags((string) $v))),
                $cols[1]
            );

            $years = $this->expandFitmentYears($yearsCell);

            $parsedRows[] = [
                'index' => $i,
                'raw' => [
                    'year' => $yearsCell,
                    'make' => $makeCell,
                    'model' => $modelCell,
                ],
                'expanded_years' => $years,
                'canonical_make' => $this->canonicalFitmentToken($makeCell),
                'canonical_model' => $this->canonicalFitmentToken($modelCell),
            ];
        }

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'has_fitment_table' => (strpos($details, 'pa-fitment-table') !== false),
            'fitment_table_html' => $rowsSource,
            'parsed_rows' => $parsedRows,
        ], 200, [], JSON_PRETTY_PRINT);
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
            $make = $this->canonicalFitmentToken($make);
            $model = $this->canonicalFitmentToken($model);

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
                    fn ($v) => trim(html_entity_decode(strip_tags((string) $v))),
                    $cols[1]
                );

                // YEAR
                if ($year) {
                    $years = $this->expandFitmentYears($yearsCell);
                    if (! in_array($year, $years, true)) {
                        continue;
                    }
                }

                // MAKE
                if ($make && $this->canonicalFitmentToken($makeCell) !== $make) {
                    continue;
                }

                // MODEL
                if ($model && $this->canonicalFitmentToken($modelCell) !== $model) {
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

    private function canonicalFitmentToken(?string $value): string
    {
        return Str::of(html_entity_decode((string) $value))
            ->lower()
            ->replace('&', ' and ')
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->trim()
            ->toString();
    }

    private function getFitmentMatchedItemIds($itemsQuery, Request $request, $year, $make, $model): array
    {
        return Cache::remember($this->fitmentCacheKey($request), 600, function () use ($itemsQuery, $year, $make, $model) {
            $candidateQuery = clone $itemsQuery;
            $candidateQuery->setEagerLoads([]);
            $this->applyFitmentKeywordPrefilter($candidateQuery, $year, $make, $model);

            return $this->filterItemsByFitment(
                $candidateQuery->select('id', 'details')->get(),
                $year,
                $make,
                $model
            )->pluck('id')->all();
        });
    }

    private function fitmentCacheKey(Request $request): string
    {
        $params = $request->except(['page', 'catalog_chunk', 'catalog_chunk_size', 'view_check']);
        ksort($params);

        return 'catalog_fitment_ids_v3_' . md5(json_encode($params));
    }

    private function applyFitmentKeywordPrefilter($query, $year, $make, $model): void
    {
        foreach ([$year, $make, $model] as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $variants = $this->fitmentTextVariants($value);
            $query->where(function ($detailsQuery) use ($variants) {
                foreach ($variants as $variant) {
                    $detailsQuery->orWhere('details', 'like', '%' . $this->escapeLike($variant) . '%');
                }
            });
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function fitmentTextVariants(string $value): array
    {
        $value = trim(html_entity_decode($value));
        if ($value === '') {
            return [];
        }

        return array_values(array_unique(array_filter([
            $value,
            preg_replace('/[-\/_\.]+/u', ' ', $value),
            preg_replace('/\s+/u', ' ', $value),
            preg_replace('/[^A-Za-z0-9]+/u', '', $value),
        ], fn ($variant) => trim((string) $variant) !== '')));
    }

    private function expandFitmentYears(string $yearsCell): array
    {
        $years = [];

        foreach (preg_split('/\s*,\s*/', trim($yearsCell)) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $part, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                if ($start <= $end) {
                    for ($year = $start; $year <= $end; $year++) {
                        $years[] = (string) $year;
                    }
                    continue;
                }
            }

            $years[] = $this->normalizeFitmentToken($part);
        }

        return array_values(array_unique($years));
    }

    private function applySmartSearch($query, string $search)
    {
        $normalizedSearch = $this->normalizeSearchTerm($search);
        $normalizedPatterns = $this->buildNormalizedSearchPatterns($search);

        return $query->where(function ($searchQuery) use ($search, $normalizedSearch, $normalizedPatterns) {
            $searchQuery
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('sku', 'like', '%' . $search . '%')
                ->orWhere('prod_number', 'like', '%' . $search . '%')
                ->orWhere('product_part_number', 'like', '%' . $search . '%')
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

            if ($normalizedSearch !== '') {
                foreach (['name', 'sku', 'prod_number', 'product_part_number'] as $column) {
                    foreach ($normalizedPatterns as $pattern) {
                        $searchQuery->orWhereRaw(
                            $this->normalizedSqlExpression($column) . ' LIKE ?',
                            [$pattern]
                        );
                    }
                }
            }
        });
    }

    private function normalizeSearchTerm(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace('&', 'and')
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->trim()
            ->toString();
    }

    private function normalizedSqlExpression(string $column): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '/', ''), '_', ''), '.', ''), '&', 'and'))";
    }

    private function buildNormalizedSearchPatterns(string $search): array
    {
        $normalizedSearch = $this->normalizeSearchTerm($search);
        if ($normalizedSearch === '') {
            return [];
        }

        $patterns = ['%' . $normalizedSearch . '%'];
        $segments = $this->splitSearchSegments($search);

        if (count($segments) > 1) {
            $patterns[] = '%' . implode('%', $segments) . '%';
        }

        return array_values(array_unique($patterns));
    }

    private function splitSearchSegments(string $search): array
    {
        $clean = strtolower(html_entity_decode($search));
        preg_match_all('/[a-z]+|\d+/u', $clean, $matches);

        return array_values(array_filter(array_map(function ($segment) {
            return preg_replace('/[^a-z0-9]+/u', '', (string) $segment);
        }, $matches[0] ?? [])));
    }

}
