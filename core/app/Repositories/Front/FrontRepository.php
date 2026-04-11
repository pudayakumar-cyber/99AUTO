<?php

namespace App\Repositories\Front;

use App\{
    Models\Post,
    Models\Page,
    Models\Order,
};
use App\Helpers\PriceHelper;
use App\Models\Bcategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\Item;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FrontRepository
{

    public function catalogItemsWithFitment(Request $request, $perPage)
    {
        $cacheKey = 'catalog_fitment_' . md5(json_encode([
            'params' => $request->all(),
            'page'   => $request->page,
        ]));

        return Cache::remember($cacheKey, 600, function () use ($request, $perPage) {

            // 1️⃣ Pull a SAFE POOL (not entire table)
            $items = Item::with('category')
                ->whereStatus(1)
                ->latest('id')
                ->take(300) // 👈 critical: pool size
                ->get();

            // 2️⃣ Apply FITMENT FILTER in PHP
            if ($request->year || $request->make || $request->model) {
                $items = $items->filter(function ($item) use ($request) {

                    if (! $item->details) return false;

                    preg_match_all('/<tr>(.*?)<\/tr>/si', $item->details, $rows);

                    foreach ($rows[1] as $rowHtml) {

                        preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);
                        if (count($cols[1]) !== 3) continue;

                        [$yearsCell, $makeCell, $modelCell] = array_map(
                            fn ($v) => trim(strip_tags($v)),
                            $cols[1]
                        );

                        // YEAR
                        if ($request->year) {
                            $years = array_map('trim', explode(',', $yearsCell));
                            if (! in_array($request->year, $years)) continue;
                        }

                        // MAKE
                        if ($request->make && strcasecmp($makeCell, $request->make) !== 0) continue;

                        // MODEL
                        if ($request->model && strcasecmp($modelCell, $request->model) !== 0) continue;

                        return true;
                    }

                    return false;
                });
            }

            // 3️⃣ Paginate AFTER filtering
            $page = LengthAwarePaginator::resolveCurrentPage();
            $paged = $items->slice(($page - 1) * $perPage, $perPage)->values();

            return new LengthAwarePaginator(
                $paged,
                $items->count(),
                $perPage,
                $page,
                [
                    'path'  => request()->url(),
                    'query' => request()->query(),
                ]
            );
        });
    }

    public function catalogItems(Request $request, $perPage)
    {
        $cacheKey = 'catalog_' . md5(json_encode([
            'params' => $request->all(),
            'page'   => $request->page,
        ]));

        return Cache::remember($cacheKey, 600, function () use ($request, $perPage) {

            $query = Item::with('category')->whereStatus(1);

            if ($request->category) {
                $query->where('category_id', Category::whereSlug($request->category)->value('id'));
            }

            if ($request->subcategory) {
                $query->where('subcategory_id', Subcategory::whereSlug($request->subcategory)->value('id'));
            }

            if ($request->childcategory) {
                $query->where('childcategory_id', ChieldCategory::whereSlug($request->childcategory)->value('id'));
            }

            if ($request->brand) {
                $query->where('brand_id', Brand::whereSlug($request->brand)->value('id'));
            }

            if ($request->tag) {
                $query->where('tags', 'like', "%{$request->tag}%");
            }

            if ($request->minPrice) {
                $query->where('discount_price', '>=', PriceHelper::convertPrice($request->minPrice));
            }

            if ($request->maxPrice) {
                $query->where('discount_price', '<=', PriceHelper::convertPrice($request->maxPrice));
            }

            if ($request->sorting === 'low_to_high') {
                $query->orderBy('discount_price', 'asc');
            } elseif ($request->sorting === 'high_to_low') {
                $query->orderBy('discount_price', 'desc');
            } else {
                $query->latest('id');
            }

            return $query->paginate($perPage);
        });
    }

    public function homeData()
    {
        return [

            // 🔹 Featured products
            'featured_products' => Cache::remember('home_featured_products', 1800, function () {
                return Item::
                    where('status', 1)
                    ->take(8)
                    ->get();
            }),

            // 🔹 Latest products
            'latest_products' => Cache::remember('home_latest_products', 900, function () {
                return Item::where('status', 1)
                    ->latest('id')
                    ->take(8)
                    ->get();
            }),

            // 🔹 Latest reviews
            'latest_reviews' => Cache::remember('home_latest_reviews', 1800, function () {
                return Review::where('status', 1)
                    ->latest('id')
                    ->take(6)
                    ->get();
            }),
        ];
    }

   public function displayPosts($request)
    {
        $key = 'blog_list_' . md5(json_encode($request->all()));

        return Cache::remember($key, 600, function () use ($request) {

            if ($request->has('category')) {
                $categoryId = Bcategory::where('slug', $request->category)->value('id');

                return Post::with('category')
                    ->whereCategoryId($categoryId)
                    ->latest('id')
                    ->paginate(6);
            }

            if ($request->has('search')) {
                return Post::with('category')
                    ->where(function ($q) use ($request) {
                        $q->where('title', 'like', "%{$request->search}%")
                        ->orWhere('details', 'like', "%{$request->search}%");
                    })
                    ->latest('id')
                    ->paginate(6);
            }

            if ($request->has('tag')) {
                return Post::with('category')
                    ->where('tags', 'like', "%{$request->tag}%")
                    ->latest('id')
                    ->paginate(6);
            }

            return Post::with('category')->latest('id')->paginate(6);
        });
    }

    public function displayPost($slug)
    {
        return Cache::remember("post_detail_{$slug}", 1800, function () use ($slug) {

            $tags = Post::pluck('tags')
                ->flatMap(fn($t) => explode(',', $t))
                ->unique()
                ->filter()
                ->values();

            return [
                'posts'       => Post::latest('id')->take(4)->get(),
                'post'        => Post::whereSlug($slug)->firstOrFail(),
                'categories'  => Bcategory::withCount('posts')->whereStatus(1)->get(),
                'tags'        => $tags
            ];
        });
    }

    public function displayPage($slug)
    {
        return Cache::remember("page_{$slug}", 3600, function () use ($slug) {
            return Page::whereSlug($slug)->firstOrFail();
        });
    }


    public function reviewSubmit($request)
    {
        $user = Auth::user();
    
        // Check if the user already has a review for this item
        $existingReview = $user->reviews()->where('item_id', $request->item_id)->first();
    
        if ($existingReview) {
            // Update the existing review
            $existingReview->update([
                'subject' => $request->subject,
                'rating' => $request->rating,
                'review' => $request->review,
                'status' => 1,
            ]);
            return __('Your Review Updated Successfully.');
        }
    
        // Check if the user has purchased the product
        $orders = Order::where('user_id', $user->id)->get();
        $isProductPurchased = false;
    
        foreach ($orders as $order) {
            $cart = json_decode($order->cart, true);
            foreach ($cart as $key => $product) {
                if ($request->item_id == PriceHelper::GetItemId($key)) {
                    $isProductPurchased = true;
                    break 2; // Exit both loops
                }
            }
        }
    
        if (!$isProductPurchased) {
            return [
                'errors' => [
                    0 => __("Buy This Product First"),
                ],
            ];
        }
    
        // Create a new review
        $user->reviews()->create($request->all());
        return __('Your Review Submitted Successfully.');
    }
    


}
