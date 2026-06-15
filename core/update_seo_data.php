<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use App\Models\Category;
use App\Models\Brand;

// Helper to convert comma-separated string to Tagify JSON string
function makeTagifyJson($commaSeparatedString) {
    $tags = array_map(function($val) {
        return ['value' => trim($val)];
    }, explode(',', $commaSeparatedString));
    return json_encode($tags);
}

echo "Starting Dynamic SEO configuration update...\n";

// 1. Update global Site Settings
$setting = Setting::first();
if ($setting) {
    $setting->meta_keywords = "99AutoParts Canada, Canadian online auto parts store, buy aftermarket car parts Canada, 99AutoParts reviews, car parts delivery Canada, shipping car parts province-wide, cheap auto parts Canada, discount auto parts Canada";
    $setting->meta_description = "Shop 99AutoParts Canada for premium aftermarket car parts online. Enjoy fast province-wide delivery, reliable customer reviews, and discount auto parts.";
    $setting->save();
    echo "✔ Global site settings SEO updated successfully.\n";
} else {
    echo "❌ Global site settings row not found.\n";
}

// 2. Dynamically Update Category SEO Metadata based on name matching
$categories = Category::all();
foreach ($categories as $category) {
    $name = $category->name;
    $slug = $category->slug;
    $lowerName = strtolower($name);

    $keywords = "";
    $description = "";

    if (strpos($lowerName, 'ignition') !== false || strpos($lowerName, 'spark') !== false || strpos($lowerName, 'coil') !== false) {
        $keywords = "{$name} Canada, buy ignition parts Canada, replacement spark plugs online, ignition coils Canada, {$name} online store, 99autoparts";
        $description = "Shop premium {$name} at 99AutoParts Canada. Find ignition coils, modules, spark plugs, and tune-up components with fast province-wide delivery.";
    } elseif (strpos($lowerName, 'brake') !== false || strpos($lowerName, 'pad') !== false || strpos($lowerName, 'rotor') !== false || strpos($lowerName, 'shoe') !== false) {
        $keywords = "{$name} Canada, buy brake pads online Canada, aftermarket brake shoes, brake rotors Canada, {$name} replacement parts, 99autoparts";
        $description = "Upgrade your stopping power with high-quality {$name} from 99AutoParts. Enjoy fast shipping on brake pads, shoes, rotors, and calipers in Canada.";
    } elseif (strpos($lowerName, 'shock') !== false || strpos($lowerName, 'strut') !== false || strpos($lowerName, 'suspension') !== false) {
        $keywords = "{$name} Canada, auto suspension parts online, buy struts and shocks Canada, control arms Canada, sway bar links Canada, 99autoparts";
        $description = "Shop premium {$name} at 99AutoParts Canada. Get shocks, struts, hardware, and replacement suspension parts online at discount prices.";
    } elseif (strpos($lowerName, 'oil') !== false || strpos($lowerName, 'fluid') !== false || strpos($lowerName, 'lubricant') !== false || strpos($lowerName, 'grease') !== false) {
        $keywords = "{$name} Canada, buy engine oil online, transfer case oil Canada, automotive lubricants online, high-performance car fluids, 99autoparts";
        $description = "Keep your vehicle running smoothly with premium {$name} from 99AutoParts Canada. Fast shipping on motor oils, greases, and transmission fluids.";
    } elseif (strpos($lowerName, 'filter') !== false || strpos($lowerName, 'pcv') !== false) {
        $keywords = "{$name} Canada, car engine filters online, buy engine air filters, cabin air filters Canada, PCV valves online, 99autoparts";
        $description = "Find high-quality replacement {$name} at 99AutoParts Canada. Keep your engine clean with air filters, oil filters, and cabin filters.";
    } elseif (strpos($lowerName, 'shaft') !== false || strpos($lowerName, 'axle') !== false || strpos($lowerName, 'cv') !== false) {
        $keywords = "{$name} Canada, buy cv shafts online, replacement drive axles, drivetrain parts Canada, front axles online, 99autoparts";
        $description = "Shop premium {$name} at 99AutoParts Canada. Enjoy fast shipping on replacement CV shafts, half axles, and drivetrain parts.";
    } else {
        // Fallback for general categories
        $keywords = "{$name} Canada, replacement auto parts online, cheap car parts Canada, {$name} aftermarket parts, 99autoparts";
        $description = "Shop high-quality {$name} at 99AutoParts Canada. Explore thousands of discount aftermarket auto parts with fast delivery.";
    }

    $category->meta_keywords = makeTagifyJson($keywords);
    $category->meta_descriptions = $description;
    $category->save();
    
    echo "✔ Category '{$name}' (Slug: {$slug}) SEO updated successfully.\n";
}

// 3. Dynamically Update Brand SEO Metadata
echo "Updating Brands SEO configuration...\n";
$brands = Brand::all();
foreach ($brands as $brand) {
    $name = $brand->name;
    $slug = $brand->slug;

    $keywords = "{$name} Canada, buy {$name} parts, {$name} auto parts online, {$name} replacement parts Canada, 99AutoParts {$name}";
    $description = "Shop premium {$name} replacement auto parts online at 99AutoParts Canada. Enjoy fast province-wide shipping and discount prices on all {$name} parts.";

    $brand->meta_keywords = makeTagifyJson($keywords);
    $brand->meta_descriptions = $description;
    $brand->save();

    echo "✔ Brand '{$name}' (Slug: {$slug}) SEO updated successfully.\n";
}

echo "SEO database update completed successfully!\n";
