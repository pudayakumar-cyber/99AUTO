<?php

namespace App\Http\Controllers\Front;

use Illuminate\{
    Http\Request,
    Support\Facades\Session
};

use App\{
    Models\Item,
    Models\Setting,
    Models\Subscriber,
    Helpers\EmailHelper,
    Http\Controllers\Controller,
    Http\Requests\ReviewRequest,
    Http\Requests\SubscribeRequest,
    Repositories\Front\FrontRepository
};
use App\Jobs\EmailSendJob;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\CampaignItem;
use App\Models\Category;
use App\Models\Fcategory;
use App\Models\HomeCutomize;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Models\Post;
use App\Models\Service;
use App\Models\Slider;
use App\Models\TrackOrder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FrontendController extends Controller
{

    /**
     * Constructor Method.
     *
     * @param  \App\Repositories\Front\FrontRepository $repository
     *
     */
    protected $repository;
    public function __construct(FrontRepository $repository)
    {
        $this->repository = $repository;
        $setting = Setting::first();
        if ($setting->recaptcha == 1) {
            Config::set('captcha.sitekey', $setting->google_recaptcha_site_key);
            Config::set('captcha.secret', $setting->google_recaptcha_secret_key);
        }

        $this->middleware('localize');
    }

    public function homePageData()
    {
        return Cache::remember('homepage_data', 600, function () {

            $home_customize = HomeCutomize::first();

            return [
                // sliders
                'sliders' => Slider::where('home_page', setting('theme'))->get(),

                // campaign items
                'campaign_items' => CampaignItem::with('item')
                    ->whereStatus(1)
                    ->whereIsFeature(1)
                    ->latest('id')
                    ->get(),

                // services
                'services' => Service::latest('id')->get(),

                // blog posts
                'posts' => Post::with('category')->latest('id')->take(8)->get(),

                // brands
//                'brands' => Brand::whereStatus(1)->whereIsPopular(1)->limit(2)->get(),
'brands' => Brand::whereStatus(1)->whereIsPopular(1)->orderBy('id')->get(),
                // banners
                'hero_banner'   => $home_customize->hero_banner != '[]'
                    ? json_decode($home_customize->hero_banner, true)
                    : null,

                'banner_first'  => json_decode($home_customize->banner_first, true),
                'banner_secend' => json_decode($home_customize->banner_secend, true),
                'banner_third'  => json_decode($home_customize->banner_third, true),
            ];
        });
    }

    // -------------------------------- HOME ----------------------------------------

    public function index()
    {
        $setting = Setting::first();
        $cacheKey = 'homepage_full_payload_' . ($setting->theme ?? 'default');

        return view('front.index', Cache::remember($cacheKey, 600, function () use ($setting) {
            return $this->buildHomepagePayload($setting);
        }));


        $home_customize = HomeCutomize::first();

        // feature category
        $feature_category_ids = json_decode($home_customize->feature_category, true);
        $feature_category_title = $feature_category_ids['feature_title'];
        $feature_category = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!in_array($feature_category_ids['category_id' . $i], $feature_category)) {
                if ($feature_category_ids['category_id' . $i]) {
                    $feature_category[] = $feature_category_ids['category_id' . $i];
                }
            }
        }

        $feature_categories = [];
        foreach ($feature_category as $key => $cat) {
            $featureCat = Category::find($cat);
            if ($featureCat) {
                $feature_categories[] = $featureCat;
            }
        }
        $feature_category_items = [];
        if (count($feature_categories)) {
            $index = null;
            foreach ($feature_categories as $key => $data) {
                if ($data->id == $feature_category_ids['category_id1']) {
                    $index = $key;
                }
            }
            if ($index === null) {
                $index = 0;
            }
            $category = $feature_categories[$index]->id;
            $subcategory = $feature_category_ids['subcategory_id1'];
            $childcategory = $feature_category_ids['childcategory_id1'];

            $feature_category_items = Item::when($category, function ($query, $category) {
                return $query->where('category_id', $category);
            })
                ->when($subcategory, function ($query, $subcategory) {
                    return $query->where('subcategory_id', $subcategory);
                })
                ->when($childcategory, function ($query, $childcategory) {
                    return $query->where('childcategory_id', $childcategory);
                })
                ->whereStatus(1)->take(10)->orderby('id', 'desc')->get();
        }


        // feature category end
        $home_customize = HomeCutomize::first();
        // popular category

        $popular_category_ids = json_decode($home_customize->popular_category, true);
        $popular_category_title = $popular_category_ids['popular_title'];

        $popular_category = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!in_array($popular_category_ids['category_id' . $i], $popular_category)) {
                if ($popular_category_ids['category_id' . $i]) {
                    $popular_category[] = $popular_category_ids['category_id' . $i];
                }
            }
        }
        $popular_categories = [];
        foreach ($popular_category as $key => $cat) {
            $popularCat = Category::find($cat);
            if ($popularCat) {
                $popular_categories[] = $popularCat;
            }
        }

        $popular_category_items = [];

        if (count($popular_categories) > 0) {
            $index = null;
            foreach ($popular_categories as $key => $data) {
                if ($data->id == $popular_category_ids['category_id1']) {
                    $index = $key;
                }
            }
            $pupular_cateogry_home4 = null;
            if ($setting->theme == 'theme4') {
                $pupular_cateogries_home4 = json_decode($home_customize->home_4_popular_category, true);
                $pupular_cateogry_home4 = [];
                foreach ((array) $pupular_cateogries_home4 as $home4category) {
                    $home4Cat = Category::with('items')->find($home4category);
                    if ($home4Cat) {
                        $pupular_cateogry_home4[] = $home4Cat;
                    }
                }
            }

            // dd($pupular_cateogry_home4);
            if ($index === null) {
                $index = 0;
            }
            $category = $popular_categories[$index]->id;
            $subcategory = $popular_category_ids['subcategory_id1'];
            $childcategory = $popular_category_ids['childcategory_id1'];

            $popular_category_items = Item::when($category, function ($query, $category) {
                return $query->where('category_id', $category);
            })
                ->when($subcategory, function ($query, $subcategory) {
                    return $query->where('subcategory_id', $subcategory);
                })
                ->when($childcategory, function ($query, $childcategory) {
                    return $query->where('childcategory_id', $childcategory);
                })
                ->whereStatus(1)->get();
        }




        // two column category
        $two_column_category_ids = json_decode($home_customize->two_column_category, true);

        $two_column_category = [];
        for ($i = 1; $i <= 3; $i++) {
            if (isset($two_column_category_ids['category_id' . $i]) && !in_array($two_column_category_ids['category_id' . $i], $two_column_category)) {
                if ($two_column_category_ids['category_id' . $i]) {
                    $two_column_category[] = $two_column_category_ids['category_id' . $i];
                }
            }
        }

        $two_column_categories = Category::whereStatus(1)->whereIn('id', $two_column_category)->orderby('id', 'desc')->get();

        $two_column_category_items1 = [];
        if ($two_column_category_ids['category_id1']) {
            $two_column_category_items1 = Item::where('category_id', $two_column_category_ids['category_id1'])->orderby('id', 'desc')->whereStatus(1)->take(10)->get();
        }
        if ($two_column_category_ids['subcategory_id1']) {
            $two_column_category_items1 = Item::where('subcategory_id', $two_column_category_ids['subcategory_id1'])->whereStatus(1)->where('category_id', $two_column_category_ids['category_id1'])->orderby('id', 'desc')->take(10)->get();
        }
        if ($two_column_category_ids['childcategory_id1']) {
            $two_column_category_items1 = Item::where('childcategory_id', $two_column_category_ids['childcategory_id1'])->whereStatus(1)->where('category_id', $two_column_category_ids['category_id1'])->orderby('id', 'desc')->take(10)->get();
        }

        $two_column_category_items2 = [];
        if ($two_column_category_ids['category_id2']) {
            $two_column_category_items2 = Item::where('category_id', $two_column_category_ids['category_id2'])->orderby('id', 'desc')->whereStatus(1)->take(10)->get();
        }
        if ($two_column_category_ids['subcategory_id2']) {
            $two_column_category_items2 = Item::where('subcategory_id', $two_column_category_ids['subcategory_id2'])->whereStatus(1)->where('category_id', $two_column_category_ids['category_id2'])->orderby('id', 'desc')->take(10)->get();
        }
        if ($two_column_category_ids['childcategory_id2']) {
            $two_column_category_items2 = Item::where('childcategory_id', $two_column_category_ids['childcategory_id2'])->whereStatus(1)->where('category_id', $two_column_category_ids['category_id2'])->orderby('id', 'desc')->take(10)->get();
        }

        $two_column_category_items3 = [];
        if (isset($two_column_category_ids['category_id3'])) {
            if ($two_column_category_ids['category_id3']) {
                $two_column_category_items3 = Item::where('category_id', $two_column_category_ids['category_id3'])->orderby('id', 'desc')->whereStatus(1)->take(10)->get();
            }
            if ($two_column_category_ids['subcategory_id3']) {
                $two_column_category_items3 = Item::where('subcategory_id', $two_column_category_ids['subcategory_id3'])->whereStatus(1)->where('category_id', $two_column_category_ids['category_id3'])->orderby('id', 'desc')->take(10)->get();
            }
            if ($two_column_category_ids['childcategory_id3']) {
                $two_column_category_items3 = Item::where('childcategory_id', $two_column_category_ids['childcategory_id3'])->whereStatus(1)->where('category_id', $two_column_category_ids['category_id3'])->orderby('id', 'desc')->take(10)->get();
            }
        }




        $two_column_categoriess = [];
        foreach ($two_column_categories as $key => $two_category) {
            if ($key == 0) {
                $two_column_categoriess[$key]['name'] = $two_category;
                $two_column_categoriess[$key]['items'] = $two_column_category_items1;
            } elseif ($key == 1) {
                $two_column_categoriess[$key]['name'] = $two_category;
                $two_column_categoriess[$key]['items'] = $two_column_category_items2;
            } else {
                $two_column_categoriess[$key]['name'] = $two_category;
                $two_column_categoriess[$key]['items'] = $two_column_category_items3;
            }
        }


        if ($setting->theme == 'theme1') {
            $sliders = Slider::where('home_page', 'theme1')->get();
        } elseif ($setting->theme == 'theme2') {
            $sliders = Slider::where('home_page', 'theme2')->get();
        } elseif ($setting->theme == 'theme3') {
            $sliders = Slider::where('home_page', 'theme3')->get();
        } else {
            $sliders = Slider::where('home_page', 'theme4')->get();
        }


        // {"title1":"Watchtt","subtitle1":"50% OFF","url1":"#","title2":"Man","subtitle2":"40% OFF","url2":"#","img1":"1637766462banner-h2-4-1.jpeg","img2":"1637766420banner-h2-4-1.jpeg"}
        return view('front.index', array_merge($homeData, [

            // KEEP ONLY WHAT IS NOT YET CACHED
            'feature_category_items'   => $feature_category_items,
            'feature_categories'       => $feature_categories,
            'feature_category_title'   => $feature_category_title,

            'popular_category_items'   => $popular_category_items,
            'popular_categories'       => $popular_categories,
            'popular_category_title'   => $popular_category_title,
            'sliders'  => $sliders,
            'services' => Service::orderby('id', 'desc')->get(),
            'campaign_items' => CampaignItem::with('item')->whereStatus(1)->whereIsFeature(1)->orderby('id', 'desc')->get(),
            'banner_first'   => json_decode($home_customize->banner_first, true),
            'products' => Item::with('category')->whereStatus(1),
//            'brands'   => Brand::whereStatus(1)->whereIsPopular(1)->limit(2)->get(),
'brands' => Brand::whereStatus(1)->whereIsPopular(1)->orderBy('id')->get(),
            'two_column_categoriess'   => $two_column_categoriess,
            'hero_banner'   => $home_customize->hero_banner != '[]' ? json_decode($home_customize->hero_banner, true) : null,
            'posts'    => Post::with('category')->orderby('id', 'desc')->take(8)->get(),
            'banner_secend'  => json_decode($home_customize->banner_secend, true),
            'banner_third'   => json_decode($home_customize->banner_third, true),
            'home_page4_banner' => json_decode($home_customize->home_page4, true),
            'pupular_cateogry_home4' => isset($pupular_cateogry_home4) ? $pupular_cateogry_home4 : []
        ]));

        // return view('front.index', [
        //     'hero_banner'   => $home_customize->hero_banner != '[]' ? json_decode($home_customize->hero_banner, true) : null,
        //     'banner_first'   => json_decode($home_customize->banner_first, true),
        //     'sliders'  => $sliders,
        //     'campaign_items' => CampaignItem::with('item')->whereStatus(1)->whereIsFeature(1)->orderby('id', 'desc')->get(),
        //     'services' => Service::orderby('id', 'desc')->get(),
        //     'posts'    => Post::with('category')->orderby('id', 'desc')->take(8)->get(),
        //     // 'brands'   => Brand::whereStatus(1)->limit(2)->get(),
        //     'banner_secend'  => json_decode($home_customize->banner_secend, true),
        //     'banner_third'   => json_decode($home_customize->banner_third, true),
        //     'brands'   => Brand::whereStatus(1)->whereIsPopular(1)->limit(2)->get(),
        //     'products' => Item::with('category')->whereStatus(1),
        //     'home_page4_banner' => json_decode($home_customize->home_page4, true),
        //     'pupular_cateogry_home4' => isset($pupular_cateogry_home4) ? $pupular_cateogry_home4 : [],
        //     // feature category
        //     'feature_category_items' => $feature_category_items,
        //     'feature_categories' => $feature_categories,
        //     'feature_category_title' => $feature_category_title,

        //     // feature category
        //     'popular_category_items' => $popular_category_items,
        //     'popular_categories' => $popular_categories,
        //     'popular_category_title' => $popular_category_title,

        //     // two column category
        //     'two_column_categoriess' => $two_column_categoriess,

        // ]);
    }



    public function review_submit()
    {
        return view('back.overlay.index');
    }

    public function slider_o_update(Request $request)
    {
        $setting = Setting::find(1);
        $setting->overlay = $request->slider_overlay;
        $setting->save();
        return redirect()->back();
    }


    private function buildHomepagePayload($setting): array
    {
        $home_customize = HomeCutomize::first();

        $feature_category_ids = json_decode($home_customize->feature_category, true) ?: [];
        $feature_category_title = $feature_category_ids['feature_title'] ?? null;
        $featureCategoryIds = [];
        for ($i = 1; $i <= 4; $i++) {
            $categoryId = $feature_category_ids['category_id' . $i] ?? null;
            if ($categoryId && !in_array($categoryId, $featureCategoryIds, true)) {
                $featureCategoryIds[] = $categoryId;
            }
        }

        $featureCategoryMap = Category::whereIn('id', $featureCategoryIds)->get()->keyBy('id');
        $feature_categories = collect($featureCategoryIds)
            ->map(fn ($id) => $featureCategoryMap->get($id))
            ->filter()
            ->values();

        $feature_category_items = collect();
        if ($feature_categories->isNotEmpty()) {
            $selectedFeatureCategoryId = $feature_category_ids['category_id1'] ?? $feature_categories->first()->id;
            $feature_category_items = $this->homepageCategoryItems(
                $selectedFeatureCategoryId,
                $feature_category_ids['subcategory_id1'] ?? null,
                $feature_category_ids['childcategory_id1'] ?? null
            );
        }

        $popular_category_ids = json_decode($home_customize->popular_category, true) ?: [];
        $popular_category_title = $popular_category_ids['popular_title'] ?? null;
        $popularCategoryIds = [];
        for ($i = 1; $i <= 4; $i++) {
            $categoryId = $popular_category_ids['category_id' . $i] ?? null;
            if ($categoryId && !in_array($categoryId, $popularCategoryIds, true)) {
                $popularCategoryIds[] = $categoryId;
            }
        }

        $popularCategoryMap = Category::whereIn('id', $popularCategoryIds)->get()->keyBy('id');
        $popular_categories = collect($popularCategoryIds)
            ->map(fn ($id) => $popularCategoryMap->get($id))
            ->filter()
            ->values();

        $popular_category_items = collect();
        if ($popular_categories->isNotEmpty()) {
            $selectedPopularCategoryId = $popular_category_ids['category_id1'] ?? $popular_categories->first()->id;
            $popular_category_items = $this->homepageCategoryItems(
                $selectedPopularCategoryId,
                $popular_category_ids['subcategory_id1'] ?? null,
                $popular_category_ids['childcategory_id1'] ?? null
            );
        }

        $pupular_cateogry_home4 = collect();
        if (($setting->theme ?? null) === 'theme4') {
            $home4CategoryIds = array_values(array_filter((array) json_decode($home_customize->home_4_popular_category, true)));
            $home4CategoryMap = Category::whereIn('id', $home4CategoryIds)->get()->keyBy('id');

            $pupular_cateogry_home4 = collect($home4CategoryIds)
                ->map(function ($id) use ($home4CategoryMap) {
                    $category = $home4CategoryMap->get($id);
                    if (!$category) {
                        return null;
                    }

                    return $category->setRelation('items', $this->homepageCategoryItems($id));
                })
                ->filter()
                ->values();
        }

        $two_column_category_ids = json_decode($home_customize->two_column_category, true) ?: [];
        $twoColumnCategoryIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $categoryId = $two_column_category_ids['category_id' . $i] ?? null;
            if ($categoryId && !in_array($categoryId, $twoColumnCategoryIds, true)) {
                $twoColumnCategoryIds[] = $categoryId;
            }
        }

        $twoColumnCategoryMap = Category::whereStatus(1)
            ->whereIn('id', $twoColumnCategoryIds)
            ->get()
            ->keyBy('id');

        $two_column_categoriess = [];
        foreach ($twoColumnCategoryIds as $index => $categoryId) {
            $category = $twoColumnCategoryMap->get($categoryId);
            if (!$category) {
                continue;
            }

            $position = $index + 1;
            $two_column_categoriess[] = [
                'name' => $category,
                'items' => $this->homepageCategoryItems(
                    $two_column_category_ids['category_id' . $position] ?? null,
                    $two_column_category_ids['subcategory_id' . $position] ?? null,
                    $two_column_category_ids['childcategory_id' . $position] ?? null
                ),
            ];
        }

        $productBaseQuery = $this->homepageProductQuery();
        $theme = $setting->theme ?? 'theme1';

        return [
            'feature_category_items' => $feature_category_items,
            'feature_categories' => $feature_categories,
            'feature_category_title' => $feature_category_title,
            'popular_category_items' => $popular_category_items,
            'popular_categories' => $popular_categories,
            'popular_category_title' => $popular_category_title,
            'sliders' => Slider::where('home_page', $theme)->get(),
            'services' => Service::orderBy('id', 'desc')->get(),
            'campaign_items' => CampaignItem::with([
                'item' => function ($query) {
                    $query->with('category')->withAvg('reviews', 'rating');
                },
            ])->whereStatus(1)->whereIsFeature(1)->orderBy('id', 'desc')->get(),
            'banner_first' => json_decode($home_customize->banner_first, true),
            'brands' => Brand::whereStatus(1)->whereIsPopular(1)->orderBy('id')->get(),
            'two_column_categoriess' => $two_column_categoriess,
            'hero_banner' => $home_customize->hero_banner != '[]' ? json_decode($home_customize->hero_banner, true) : null,
            'posts' => Post::with('category')->orderBy('id', 'desc')->take(8)->get(),
            'banner_secend' => json_decode($home_customize->banner_secend, true),
            'banner_third' => json_decode($home_customize->banner_third, true),
            'home_page4_banner' => json_decode($home_customize->home_page4, true),
            'pupular_cateogry_home4' => $pupular_cateogry_home4,
            'featured_products' => (clone $productBaseQuery)->where('is_type', 'feature')->latest('id')->take(10)->get(),
            'flash_deal_products' => (clone $productBaseQuery)->where('is_type', 'flash_deal')->whereNotNull('date')->latest('id')->take(10)->get(),
            'recent_products' => (clone $productBaseQuery)->latest('id')->take(10)->get(),
            'best_seller_products' => (clone $productBaseQuery)->where('is_type', 'best')->latest('id')->take(10)->get(),
            'top_rated_products' => (clone $productBaseQuery)->where('is_type', 'top')->latest('id')->take(10)->get(),
        ];
    }

    private function homepageProductQuery()
    {
        return Item::with('category')
            ->withAvg('reviews', 'rating')
            ->whereStatus(1);
    }

    private function homepageCategoryItems($categoryId = null, $subcategoryId = null, $childcategoryId = null, int $limit = 10)
    {
        return $this->homepageProductQuery()
            ->when($categoryId, function ($query, $categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->when($subcategoryId, function ($query, $subcategoryId) {
                return $query->where('subcategory_id', $subcategoryId);
            })
            ->when($childcategoryId, function ($query, $childcategoryId) {
                return $query->where('childcategory_id', $childcategoryId);
            })
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function product($slug)
    {

        $item = Item::with('category')->whereStatus(1)->whereSlug($slug)->firstOrFail();
        $video = explode('=', $item->video);
        return view('front.catalog.product', [
            'item'          => $item,
            'reviews'       => $item->reviews()->where('status', 1)->paginate(3),
            'galleries'     => $item->galleries,
            'video'         => $item->video ? end($video) : '',
            'sec_name'      => isset($item->specification_name) ? json_decode($item->specification_name, true) : [],
            'sec_details'   => isset($item->specification_description) ? json_decode($item->specification_description, true) : [],
            'attributes'    => $item->attributes,
            'related_items' => $item->category->items()->whereStatus(1)->where('id', '!=', $item->id)->take(8)->get()
        ]);
    }



    public function brands()
    {
        if (Setting::first()->is_brands == 0) {
            return back();
        }
        return view('front.brand', [
            'brands' => Brand::whereStatus(1)->get()
        ]);
    }


    public function blog(Request $request)
    {

        $tagz = '';
        $tags = null;
        $name = Post::pluck('tags')->toArray();
        foreach ($name as $nm) {
            $tagz .= $nm . ',';
        }
        $tags = array_unique(explode(',', $tagz));

        if (Setting::first()->is_blog == 0) return back();

        if ($request->ajax()) return view('front.blog.list', ['posts' => $this->repository->displayPosts($request)]);

        return view('front.blog.index', [
            'posts' => $this->repository->displayPosts($request),
            'recent_posts'       => Post::orderby('id', 'desc')->take(4)->get(),
            'categories' => \App\Models\Bcategory::withCount('posts')->whereStatus(1)->get(),
            'tags'       => array_filter($tags)
        ]);
    }

    public function blogDetails($id)
    {
        $items = $this->repository->displayPost($id);

        return view('front.blog.show', [
            'post' => $items['post'],
            'categories' => $items['categories'],
            'tags' => $items['tags'],
            'posts' => $items['posts'],

        ]);
    }


    // -------------------------------- FAQ ----------------------------------------

    public function faq()
    {
        if (Setting::first()->is_faq == 0) {
            return back();
        }
        $fcategories =  Fcategory::whereStatus(1)->withCount('faqs')->latest('id')->get();
        return view('front.faq.index', ['fcategories' => $fcategories]);
    }

    public function show($slug)
    {
        if (Setting::first()->is_faq == 0) {
            return back();
        }
        $category =  Fcategory::whereSlug($slug)->first();
        return view('front.faq.show', ['category' => $category]);
    }

    // -------------------------------- FAQ ----------------------------------------

    // -------------------------------- CAMPAIGN ----------------------------------------

    public function compaignProduct()
    {
        if (Setting::first()->is_campaign == 0) {
            return back();
        }
        $compaign_items =  CampaignItem::whereStatus(1)->orderby('id', 'desc')->get();
        return view('front.campaign', ['campaign_items' => $compaign_items]);
    }

    // -------------------------------- CAMPAIGN ----------------------------------------


    // -------------------------------- CURRENCY ----------------------------------------
    public function currency($id)
    {
        Session::put('currency', $id);
        return back();
    }
    // -------------------------------- CURRENCY ----------------------------------------


    // -------------------------------- LANGUAGE ----------------------------------------
    public function language($id)
    {
        Session::put('language', $id);
        return back();
    }
    // -------------------------------- LANGUAGE ----------------------------------------


    // -------------------------------- FAQ ----------------------------------------

    public function page($slug)
    {
        return view('front.page', [
            'page' => $this->repository->displayPage($slug)
        ]);
    }

    // -------------------------------- CONTACT ----------------------------------------

    public function contact()
    {
        if (Setting::first()->is_contact == 0) {
            return back();
        }
        return view('front.contact');
    }

    public function contactEmail(Request $request)
    {
        $setting = Setting::first();

        $request->validate([
            'g-recaptcha-response' => $setting->recaptcha == 1 ? 'required|captcha' : '',
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email|max:50',
            'phone' => 'required|max:50',
            'message' => 'required|max:250',
            'honeypot'   => 'max:0',
        ]);
        
        $input = $request->all();



       
        $name  = $input['first_name'] . ' ' . $input['last_name'];
        $subject = "Email From " . $name;
        $to = $setting->contact_email;
        $phone = $request->phone;
        $from = $request->email;
        $messageContent = $request->message;
        $msg = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>New Contact Inquiry</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:20px;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);">

<!-- Header -->
<tr>
<td style="background:#d71920; padding:20px; text-align:center;">
<img src="' . asset('storage/images/OM_17668398019e1RBgt0.png') . '">
<p style="color:#ffffff; margin:5px 0 0; font-size:14px;">
Huge Selection of Quality Auto Parts
</p>
</td>
</tr>

<!-- Body -->
<tr>
<td style="padding:30px; color:#333333;">

<h2 style="margin-top:0; color:#111111;">
New Contact Form Submission
</h2>

<p style="font-size:15px; line-height:1.6;">
You have received a new inquiry from the website contact form.
</p>

<!-- Contact Info Box -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0; background:#f9f9f9; border-left:4px solid #d71920;">
<tr>
<td style="padding:15px;">

<p style="margin:0 0 10px; font-size:14px;">
<strong>Name:</strong> '.$name.'
</p>

<p style="margin:0 0 10px; font-size:14px;">
<strong>Email:</strong> '.$from.'
</p>

<p style="margin:0 0 10px; font-size:14px;">
<strong>Phone:</strong> '.$phone.'
</p>

<p style="margin:0; font-size:14px;">
<strong>Message:</strong><br>
<span style="color:#555555;">'.nl2br(e($messageContent)).'</span>
</p>

</td>
</tr>
</table>

<p style="font-size:13px; color:#666666;">
This email was sent from the 99AutoParts contact page.
</p>

<p style="margin-bottom:0;">
Regards,<br>
<strong>99 Auto Parts System</strong>
</p>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="background:#111111; padding:15px; text-align:center;">
<p style="color:#aaaaaa; font-size:12px; margin:0;">
© 99 Auto Parts. All rights reserved.
</p>
</td>
</tr>

</table>
</td>
</tr>
</table>
</body>
</html>
';

        $emailData = [
            'to' => $to,
            'subject' => $subject,
            'body' => $msg,
        ];

        

        $setting = Setting::first();
        if ($setting->is_queue_enabled == 1) {
            dispatch(new EmailSendJob($emailData));
        } else {
            $email = new EmailHelper();
             $email->sendCustomMail($emailData);
        }


        Session::flash('success', __('Thank you for contacting with us, we will get back to you shortly.'));
        return redirect()->back();
    }

    // -------------------------------- REVIEW ----------------------------------------

    public function reviews()
    {
        return view('front.reviews');
    }

    public function topReviews()
    {
        return view('front.top-reviews');
    }

    public function reviewSubmit(ReviewRequest $request)
    {
        return response()->json($this->repository->reviewSubmit($request));
    }



    // -------------------------------- SUBSCRIBE ----------------------------------------

    public function subscribeSubmit(SubscribeRequest $request)
    {
        Subscriber::create($request->all());
        return response()->json(__('You Have Subscribed Successfully.'));
    }


    // ---------------------------- TRACK ORDER ----------------------------------------//
    public function trackOrder()
    {
        return view('front.track_order');
    }

    public function track(Request $request)
    {
        $order = Order::where('transaction_number', $request->order_number)->first();

        if ($order) {
            return view('user.order.track', [
                'numbers' => 3,
                'track_orders' => TrackOrder::whereOrderId($order->id)->get()->toArray()
            ]);
        } else {
            return view('user.order.track', [
                'numbers' => 3,
                'error' => 1,
            ]);
        }
    }


    public function maintainance()
    {
        $setting = Setting::first();
        if ($setting->is_maintainance == 0) {
            return redirect(route('front.index'));
        }
        return view('front.maintainance');
    }



    public function finalize()
    {
  
        Artisan::call('migrate', ['--seed' => true]);
        copy(str_replace('core', '', base_path() . "updater/composer.json"), base_path('composer.json'));
        copy(str_replace('core', '', base_path() . "updater/composer.lock"), base_path('composer.lock'));

        $exists = PaymentSetting::where("unique_keyword", "paytabs")->exists();
        if (!$exists) {
            $jsonString = '{"profile_id":"159330","client_secret":"SNJ9BGGL9W-JKLRTKJ6DR-MTMZ2GMTNW","check_sandbox":1}';
            $gateway = new PaymentSetting();
            $gateway->name = "Paytabs";
            $gateway->unique_keyword = "paytabs";
            $gateway->information = $jsonString;
            $gateway->text = "Paytabs is the faster & safer way to send money. Make an online payment via Paytabs.";
            $gateway->status = 0;

            $gateway->save();
        }


        $menu = Menu::where('language_id',1)->exists();
  
        if ($menu == false) {
            $menu = new Menu();
            $menu->language_id = 1;
            $menu->menus = '[{"text":"Home","href":"","icon":"empty","target":"_self","title":"","type":"home"},{"text":"Shop","href":"","icon":"empty","target":"_self","title":"","type":"shop"},{"text":"Campaign","href":"","icon":"empty","target":"_self","title":"","type":"campaign"},{"type":"blog","text":"Blog","href":"","target":"_self"},{"type":"pages","text":"Pages","href":"","target":"_self","children":[{"type":"7","text":"About Us","href":"","target":"_self"},{"type":"14","text":"How It Works","href":"","target":"_self"},{"type":"10","text":"Privacy Policy","href":"","target":"_self"},{"type":"11","text":"Terms & Service","href":"","target":"_self"},{"type":"12","text":"Return Policy","href":"","target":"_self"}]},{"text":"Contact","href":"","icon":"empty","target":"_self","title":"","type":"contact"}]';
            $menu->created_at = Carbon::now();
            $menu->save();
        }

        $setting = Setting::first();
        $setting->version = '6.1';
        $setting->save();


        $sourcePath = 'assets/images';
        $destinationPath = storage_path('app/public/images');

        

        // Ensure the destination exists
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true, true);
        }

        if (File::exists($sourcePath)) {
            // Move files and folders
        File::moveDirectory($sourcePath, $destinationPath, true);
        }
        

        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        // storage:link 
        Artisan::call('storage:unlink');
        Artisan::call('storage:link');

        return redirect(route('front.index'));
    }
}
