<?php

namespace App\Services;

use App\Models\CartRecoveryLink;
use App\Models\Item;
use App\Support\KlaviyoUrl;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class CartRecoveryService
{
    public function snapshot(array $cart): array
    {
        if ($cart === [] || count($cart) > 100) {
            throw new InvalidArgumentException('Cart size is not supported for recovery.');
        }
        $items = [];
        foreach ($cart as $key => $line) {
            $id = explode('-', (string) $key)[0];
            $quantity = filter_var($line['qty'] ?? null, FILTER_VALIDATE_INT);
            if (! ctype_digit($id) || (int) $id < 1 || $quantity === false || $quantity < 1 || $quantity > 1000
                || ($line['item_type'] ?? $line['type'] ?? 'normal') !== 'normal') {
                throw new InvalidArgumentException('Cart contains an unsupported line.');
            }
            $options = $line['options_id'] ?? [];
            if (! is_array($options) || count($options) > 20) {
                throw new InvalidArgumentException('Invalid product options.');
            }
            foreach ($options as $option) {
                if (! ctype_digit((string) $option) || (int) $option < 1) {
                    throw new InvalidArgumentException('Invalid product option.');
                }
            }
            $items[] = ['id' => (int) $id, 'qty' => $quantity, 'options' => array_values(array_unique(array_map('intval', $options)))];
        }

        return $items;
    }

    public function create(array $items): array
    {
        $token = bin2hex(random_bytes(32));
        $expires = now()->addDays(7);
        CartRecoveryLink::create(['token_hash' => hash('sha256', $token), 'items' => $items, 'expires_at' => $expires]);

        return ['url' => KlaviyoUrl::to('/cart/recover/'.$token), 'expires' => $expires->timestamp];
    }

    public function find(string $token): ?CartRecoveryLink
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        return CartRecoveryLink::where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
    }

    /** Optional marketing data must never make add-to-cart or checkout fail. */
    public function eventProperties(Session $session): array
    {
        if (! config('services.klaviyo.enabled')) {
            return ['CartRecoveryAvailable' => false];
        }
        try {
            $cart = $session->get('cart', []);
            $items = $this->snapshot($cart);
            $fingerprint = hash('sha256', json_encode($cart, JSON_THROW_ON_ERROR));
            $saved = $session->get('klaviyo_cart_recovery');
            if (! is_array($saved) || ($saved['fingerprint'] ?? null) !== $fingerprint
                || ($saved['expires'] ?? 0) <= now()->addHours(1)->timestamp) {
                $saved = $this->create($items) + ['fingerprint' => $fingerprint];
                $session->put('klaviyo_cart_recovery', $saved);
            }
            $products = Item::whereIn('id', array_column($items, 'id'))->get()->keyBy('id');
            $eventItems = [];
            foreach (array_values($cart) as $index => $line) {
                $product = $products->get($items[$index]['id']);
                if (! $product) {
                    throw new InvalidArgumentException('Cart product no longer exists.');
                }
                $eventItems[] = [
                    'ProductID' => (string) $product->id,
                    'ProductName' => $line['name'],
                    'Quantity' => $items[$index]['qty'],
                    'Price' => (float) $line['main_price'] + (float) ($line['attribute_price'] ?? 0),
                    'ImageURL' => KlaviyoUrl::image($product->photo) ?: KlaviyoUrl::to('/core/public/storage/images/placeholder.png'),
                    'URL' => KlaviyoUrl::to('/product/'.$product->slug.'?item_id='.$product->id),
                ];
            }

            return ['CartRecoveryAvailable' => true, 'CheckoutURL' => $saved['url'], 'Items' => $eventItems,
                'ItemNames' => array_column($eventItems, 'ProductName'),
                '$value' => array_sum(array_map(fn ($item) => $item['Price'] * $item['Quantity'], $eventItems))];
        } catch (Throwable $exception) {
            // Do not log tokens, cart contents or customer identifiers.
            Log::warning('Klaviyo cart recovery unavailable.', ['exception' => $exception::class]);

            return ['CartRecoveryAvailable' => false];
        }
    }

    /** Rebuild from current catalog values; never reuse a saved price or payment state. */
    public function restore(array $items): array
    {
        $products = Item::with('attributes.options')->whereIn('id', array_column($items, 'id'))->get()->keyBy('id');
        $cart = [];
        $skipped = 0;
        $usedProducts = [];
        $usedOptions = [];
        foreach ($items as $line) {
            $item = $products->get($line['id']);
            $qty = (int) $line['qty'];
            if (! $item || (int) $item->status !== 1 || $item->item_type !== 'normal'
                || $qty < 1 || $qty > 1000 || $item->stock < ($usedProducts[$item->id] ?? 0) + $qty) {
                $skipped++;
                continue;
            }
            $selected = [];
            $names = [];
            foreach ($item->attributes as $attribute) {
                $options = $attribute->options->whereIn('id', $line['options']);
                if (($attribute->options->isNotEmpty() && $options->count() !== 1)
                    || $options->contains(fn ($option) => $option->stock < ($usedOptions[$option->id] ?? 0) + $qty)) {
                    $skipped++;
                    continue 2;
                }
                foreach ($options as $option) {
                    $selected[] = $option;
                    $names[] = $attribute->name;
                }
            }
            if (count($selected) !== count($line['options'])) {
                $skipped++;
                continue;
            }
            $optionNames = array_map(fn ($option) => $option->name, $selected);
            $optionPrices = array_map(fn ($option) => (float) $option->price, $selected);
            $key = $item->id.'-'.str_replace(' ', '', implode(',', $optionNames));
            $cart[$key] = [
                'options_id' => array_map(fn ($option) => $option->id, $selected),
                'attribute' => ['names' => $names, 'option_name' => $optionNames, 'option_price' => $optionPrices],
                'attribute_price' => array_sum($optionPrices), 'name' => $item->name, 'slug' => $item->slug,
                'qty' => ($cart[$key]['qty'] ?? 0) + $qty,
                'price' => (float) $item->discount_price + array_sum($optionPrices),
                'main_price' => (float) $item->discount_price, 'photo' => $item->photo,
                'type' => 'normal', 'item_type' => 'normal', 'item_l_n' => null, 'item_l_k' => null,
            ];
            $usedProducts[$item->id] = ($usedProducts[$item->id] ?? 0) + $qty;
            foreach ($selected as $option) {
                $usedOptions[$option->id] = ($usedOptions[$option->id] ?? 0) + $qty;
            }
        }

        return ['cart' => $cart, 'skipped' => $skipped];
    }
}
