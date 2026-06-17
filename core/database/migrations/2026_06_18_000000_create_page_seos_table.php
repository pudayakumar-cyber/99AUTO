<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePageSeosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('page_seos', function (Blueprint $table) {
            $table->id();
            $table->string('page_name')->unique(); // 'home', 'blog', etc.
            $table->string('display_name'); // 'Home Page', 'Blog Page', etc.
            $table->string('title')->nullable(); // Meta Title
            $table->text('meta_keywords')->nullable(); // Meta Keywords (comma-separated or JSON)
            $table->text('meta_description')->nullable(); // Meta Description
            $table->timestamps();
        });

        // Seed default pages
        $defaultPages = [
            [
                'page_name' => 'home',
                'display_name' => 'Home Page',
                'title' => '99 Auto Parts: Canada’s Online AutoParts Store',
                'meta_keywords' => '99AutoParts Canada, Canadian online auto parts store, buy aftermarket car parts Canada, 99AutoParts reviews',
                'meta_description' => 'Shop 99AutoParts Canada for premium aftermarket car parts online. Enjoy fast province-wide delivery, reliable customer reviews, and discount auto parts.'
            ],
            [
                'page_name' => 'catalog',
                'display_name' => 'Catalog / Shop Page',
                'title' => 'Shop Auto Parts & Accessories Online - 99AutoParts',
                'meta_keywords' => 'buy car parts Canada, aftermarket auto parts online, discount car parts, replacement auto parts Canada',
                'meta_description' => 'Browse and buy high-quality replacement auto parts and accessories online. 99AutoParts offers a huge selection of discount parts for all vehicle makes.'
            ],
            [
                'page_name' => 'blog',
                'display_name' => 'Blog Page',
                'title' => 'DIY Auto Parts Repair Guides & Car Tips - 99AutoParts Blog',
                'meta_keywords' => 'car maintenance tips Canada, auto parts blog, DIY car repair guides, aftermarket parts advice, car parts tutorial',
                'meta_description' => 'Read the latest car maintenance tips, DIY auto repair guides, and aftermarket parts advice to keep your vehicle running smoothly in Canada.'
            ],
            [
                'page_name' => 'brand',
                'display_name' => 'Brand Directory Page',
                'title' => 'Top Aftermarket Car Parts Brands - 99AutoParts',
                'meta_keywords' => 'car parts brands Canada, aftermarket auto brands, OEM auto parts brand directory, buy Dorman Canada, Moog suspension Canada',
                'meta_description' => 'Browse the full directory of aftermarket car parts brands at 99AutoParts Canada. Shop premium parts from Dorman, Moog, Bosch, Raybestos, and more.'
            ],
            [
                'page_name' => 'track_order',
                'display_name' => 'Track Order Page',
                'title' => 'Track Your Auto Parts Order - 99AutoParts',
                'meta_keywords' => 'track order 99autoparts, track car parts delivery canada, vehicle parts shipping tracking',
                'meta_description' => 'Track your 99AutoParts order status and shipping delivery details in real-time province-wide across Canada.'
            ],
            [
                'page_name' => 'contact',
                'display_name' => 'Contact Us Page',
                'title' => 'Contact 99AutoParts - Customer Support Canada',
                'meta_keywords' => 'contact 99autoparts, customer service auto parts Canada, buy car parts help',
                'meta_description' => 'Contact the 99AutoParts support team for any inquiries regarding orders, parts fitment, or shipping details. We are here to help.'
            ],
            [
                'page_name' => 'faq',
                'display_name' => 'FAQ Page',
                'title' => 'Frequently Asked Questions - 99AutoParts Canada',
                'meta_keywords' => '99autoparts FAQ, auto parts delivery questions, car parts return policy Canada',
                'meta_description' => 'Find answers to common questions about auto parts fitment, return policies, order tracking, and shipping across Canada.'
            ],
            [
                'page_name' => 'cart',
                'display_name' => 'Shopping Cart Page',
                'title' => 'Your Shopping Cart - 99AutoParts',
                'meta_keywords' => 'shopping cart 99autoparts, buy car parts online',
                'meta_description' => 'Review items in your shopping cart before completing your purchase. Get ready for fast delivery on aftermarket auto parts.'
            ],
            [
                'page_name' => 'checkout',
                'display_name' => 'Checkout Page',
                'title' => 'Secure Checkout - 99AutoParts',
                'meta_keywords' => 'secure checkout 99autoparts, place order car parts',
                'meta_description' => 'Complete your purchase securely. Enter your billing and shipping information for fast delivery across Canada.'
            ],
            [
                'page_name' => 'compare',
                'display_name' => 'Compare Products Page',
                'title' => 'Compare Auto Parts - 99AutoParts',
                'meta_keywords' => 'compare car parts online, compare auto parts specifications',
                'meta_description' => 'Compare specifications, pricing, and features of different aftermarket auto parts to find the perfect fit for your vehicle.'
            ]
        ];

        // Format keywords into Tagify JSON format for consistency with the admin panel tags input
        foreach ($defaultPages as $page) {
            $tags = [];
            foreach (explode(',', $page['meta_keywords']) as $kw) {
                $tags[] = ['value' => trim($kw)];
            }
            $page['meta_keywords'] = json_encode($tags);
            $page['created_at'] = now();
            $page['updated_at'] = now();

            DB::table('page_seos')->insert($page);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('page_seos');
    }
}
