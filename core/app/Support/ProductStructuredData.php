<?php

namespace App\Support;

use App\Models\Item;

final class ProductStructuredData
{
    public static function supports(Item $item): bool
    {
        return in_array(mb_strtolower(trim((string) optional($item->brand)->name)), ['liqui moly', 'lucas oil'], true);
    }

    public static function for(Item $item, string $url, ?string $imageUrl, ?float $price, ?string $currency): array
    {
        if (! self::supports($item)) {
            return [];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $item->display_name,
            'url' => $url,
        ];

        if ($imageUrl) {
            $schema['image'] = $imageUrl;
        }
        if (filled($item->sku)) {
            $schema['sku'] = trim((string) $item->sku);
        }
        if (filled(optional($item->brand)->name)) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => trim((string) $item->brand->name),
            ];
        }
        // The storefront can change currency per session. Omit an offer if its
        // active currency is not an ISO code instead of publishing a guessed CAD price.
        if ($item->item_type === 'normal' && $price !== null && is_finite($price) && $price >= 0
            && preg_match('/^[A-Z]{3}$/', (string) $currency)) {
            // The main price includes each raw first option, but the selector hides
            // stock='0' options. Do not advertise a price for an unselectable choice.
            foreach ($item->attributes as $attribute) {
                if (! isset($attribute->options[0]) || $attribute->options[0]->stock == '0') {
                    return $schema;
                }
            }
            $schema['offers'] = [
                '@type' => 'Offer',
                'url' => $url,
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability' => $item->is_stock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        return $schema;
    }
}
