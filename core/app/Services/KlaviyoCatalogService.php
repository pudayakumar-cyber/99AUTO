<?php

namespace App\Services;

use App\Models\Item;
use App\Support\StorefrontImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class KlaviyoCatalogService
{
    public function authorized(?string $providedToken): bool
    {
        $configuredToken = trim((string) config('services.klaviyo.catalog_feed_token'));

        return $configuredToken !== ''
            && is_string($providedToken)
            && hash_equals($configuredToken, $providedToken);
    }

    public function feedUrl(): ?string
    {
        $token = trim((string) config('services.klaviyo.catalog_feed_token'));

        return $token === ''
            ? null
            : url('/integrations/klaviyo/catalog?token='.rawurlencode($token));
    }

    public function query(): Builder
    {
        return Item::query()
            ->with(['brand', 'category', 'subcategory', 'childcategory', 'galleries'])
            ->where('status', 1)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('id');
    }

    public function map(Item $item): array
    {
        $price = (float) $item->discount_price > 0
            ? (float) $item->discount_price
            : (float) $item->previous_price;
        $compareAtPrice = (float) $item->previous_price > $price
            ? (float) $item->previous_price
            : null;
        $image = StorefrontImage::url($item->photo)
            ?: StorefrontImage::url($item->thumbnail)
            ?: StorefrontImage::url(optional($item->galleries->first())->photo);
        $categories = collect([
            optional($item->category)->name,
            optional($item->subcategory)->name,
            optional($item->childcategory)->name,
        ])->filter(fn ($name) => trim((string) $name) !== '')->values()->all();

        $image = $image ?: url('/core/public/storage/images/placeholder.png');

        return [
            'id' => (string) $item->id,
            'title' => trim((string) $item->display_name) ?: 'Product '.$item->id,
            'link' => url('/product/'.ltrim($item->slug, '/').'?item_id='.$item->id),
            'image_link' => $image,
            'description' => $this->description($item) ?: 'Automotive product',
            'sku' => trim((string) ($item->sku ?: $item->product_part_number ?: $item->prod_number)),
            'brand' => trim((string) optional($item->brand)->name),
            'categories' => $categories,
            'price' => round(max(0, $price), 2),
            'compare_at_price' => $compareAtPrice !== null ? round($compareAtPrice, 2) : null,
            'currency' => strtoupper((string) config('services.klaviyo.catalog_currency', 'CAD')),
            'inventory_quantity' => max(0, (int) $item->stock),
            'inventory_policy' => 1,
            'in_stock' => $item->item_type !== 'normal' || (int) $item->stock > 0,
            'published' => true,
            'updated_at' => optional($item->updated_at)->toAtomString(),
        ];
    }

    private function description(Item $item): string
    {
        $description = (string) ($item->sort_details ?: $item->details ?: '');
        $description = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/\s+/u', ' ', $description) ?: '';

        return Str::limit(trim($description), 300, '');
    }
}
