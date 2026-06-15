<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use App\Models\Category;

// Helper to convert comma-separated string to Tagify JSON string
function makeTagifyJson($commaSeparatedString) {
    $tags = array_map(function($val) {
        return ['value' => trim($val)];
    }, explode(',', $commaSeparatedString));
    return json_encode($tags);
}

echo "Updating SEO configuration...\n";

// 1. Update global Site Settings
$setting = Setting::first();
if ($setting) {
    $setting->meta_keywords = "99AutoParts Canada, Canadian online auto parts store, buy aftermarket car parts Canada, 99AutoParts reviews, car parts delivery Canada, shipping car parts province-wide, cheap auto parts Canada, discount car parts Canada";
    $setting->meta_description = "Shop 99AutoParts Canada for premium aftermarket car parts online. Enjoy fast province-wide delivery, reliable customer reviews, and discount auto parts.";
    $setting->save();
    echo "✔ Global site settings SEO updated successfully.\n";
} else {
    echo "❌ Global site settings row not found.\n";
}

// 2. Update Categories SEO Metadata
$categoryData = [
    32 => [
        'name' => '99Auto Suspension Parts',
        'keywords' => '99Auto Suspension Parts, auto suspension parts Canada, car suspension kits online, buy control arms Canada, struts and shocks Canada, sway bar links Canada, ball joints Canada',
        'description' => 'Shop premium aftermarket suspension parts at 99AutoParts. Buy control arms, struts, shocks, sway bar links, and complete suspension kits online in Canada.'
    ],
    30 => [
        'name' => 'BRM',
        'keywords' => 'brm brake parts, bremsen brake pads, buy brake rotors Canada, aftermarket brake kits, brake calipers Canada, cheap brake pads Canada, braking parts Canada',
        'description' => 'Upgrade your stopping power with premium BRM/Bremsen brake parts from 99AutoParts. Get brake pads, rotors, and calipers with fast delivery in Canada.'
    ],
    29 => [
        'name' => 'ROT',
        'keywords' => 'rotors Canada, buy brake rotors online, aftermarket brake rotors, performance rotors Canada, cheap rotors Canada, replacement rotors Canada',
        'description' => 'Shop high-quality aftermarket brake rotors at 99AutoParts. Find premium passenger and heavy-duty rotors with fast shipping across Canada.'
    ],
    31 => [
        'name' => '99',
        'keywords' => '99 autoparts, replacement car parts Canada, aftermarket auto parts online, cheap car parts Canada, auto accessories online Canada',
        'description' => 'Buy discount auto parts online at 99AutoParts Canada. Browse thousands of aftermarket parts for all vehicle makes and models.'
    ],
    23 => [
        'name' => 'Vehicles & Accessories',
        'keywords' => 'car accessories Canada, vehicle parts online, automotive accessories Canada, aftermarket car accessories, buy car parts Canada',
        'description' => 'Shop high-quality vehicle accessories and auto parts online at 99AutoParts. Get discount car accessories with province-wide shipping.'
    ]
];

foreach ($categoryData as $id => $data) {
    $category = Category::find($id);
    if ($category) {
        $category->meta_keywords = makeTagifyJson($data['keywords']);
        $category->meta_descriptions = $data['description'];
        $category->save();
        echo "✔ Category '{$data['name']}' (ID: {$id}) SEO updated successfully.\n";
    } else {
        echo "⚠ Category '{$data['name']}' (ID: {$id}) not found in database.\n";
    }
}

echo "SEO database update completed successfully!\n";
