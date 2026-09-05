<?php

namespace App\Services;

use App\Jobs\SyncKlaviyoLifecycleProfile;
use App\Models\CustomerLifecycleOrder;
use App\Models\CustomerLifecycleProfile;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Support\MaintenanceSchedule;
use App\Support\MarketingIdentity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class CustomerLifecycleService
{
    public function recordOrder(Order $order, bool $sync = true): ?CustomerLifecycleProfile
    {
        if (! str_starts_with((string) $order->transaction_number, 'ORD-')) {
            return null;
        }

        $identity = $this->identityFromOrder($order);
        if ($identity === null) {
            return null;
        }

        $profile = DB::transaction(function () use ($order, $identity): CustomerLifecycleProfile {
            $profile = CustomerLifecycleProfile::query()
                ->lockForUpdate()
                ->firstOrNew(['identity_hash' => $identity['identity_hash']]);

            $profile->fill(array_filter([
                'user_id' => $identity['user_id'],
                'email' => $identity['email'],
                'phone' => $identity['phone'],
                'first_name' => $identity['first_name'],
                'last_name' => $identity['last_name'],
                'address1' => $identity['address1'],
                'address2' => $identity['address2'],
                'city' => $identity['city'],
                'region' => $identity['region'],
                'country' => $identity['country'],
                'postal_code' => $identity['postal_code'],
            ], static fn ($value): bool => $value !== null && $value !== ''));
            $profile->save();

            if ($order->order_status === 'Canceled') {
                CustomerLifecycleOrder::where('order_id', $order->id)->delete();
            } else {
                $schedule = $this->maintenanceSchedule($order);
                $purchasedAt = Carbon::parse($order->created_at ?: now());

                CustomerLifecycleOrder::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'customer_lifecycle_profile_id' => $profile->id,
                        'purchase_category' => $schedule['category'],
                        'maintenance_interval_days' => $schedule['days'],
                        'maintenance_due_date' => $purchasedAt->copy()->addDays($schedule['days'])->toDateString(),
                        'purchased_at' => $purchasedAt,
                        'delivered_at' => $order->order_status === 'Delivered' ? now() : null,
                    ]
                );
            }

            $this->recalculate($profile, $this->businessName($order));

            return $profile->fresh();
        });

        if ($sync) {
            $this->sync($profile);
        }

        return $profile;
    }

    public function ensureUserProfile(User $user, bool $sync = true): CustomerLifecycleProfile
    {
        $email = $this->email($user->email);
        $identityHash = $this->identityHash($email, $user->id);

        $profile = CustomerLifecycleProfile::firstOrNew(['identity_hash' => $identityHash]);
        $profile->fill(array_filter([
            'user_id' => $user->id,
            'email' => $email,
            'phone' => $this->phone($user->phone),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'address1' => $user->bill_address1 ?: $user->ship_address1,
            'address2' => $user->bill_address2 ?: $user->ship_address2,
            'city' => $user->bill_city ?: $user->ship_city,
            'region' => $user->state?->name,
            'country' => $user->bill_country ?: $user->ship_country,
            'postal_code' => $user->bill_zip ?: $user->ship_zip,
        ], static fn ($value): bool => $value !== null && $value !== ''));

        if (! $profile->exists) {
            $profile->buyer_type = 'DIY';
            $profile->lifecycle_status = 'prospect';
        }

        $profile->save();
        $this->ensureReferralCode($profile);

        if ($sync) {
            $this->sync($profile);
        }

        return $profile->fresh();
    }

    public function updateVehicle(User $user, array $vehicle): CustomerLifecycleProfile
    {
        $profile = $this->ensureUserProfile($user, false);
        $profile->fill([
            'primary_vehicle_year' => (int) $vehicle['year'],
            'primary_vehicle_make' => trim((string) $vehicle['make']),
            'primary_vehicle_model' => trim((string) $vehicle['model']),
        ])->save();

        $this->sync($profile);

        return $profile->fresh();
    }

    public function sync(CustomerLifecycleProfile $profile): void
    {
        if (config('services.klaviyo.enabled')) {
            SyncKlaviyoLifecycleProfile::dispatch($profile->id);
        }
    }

    private function recalculate(CustomerLifecycleProfile $profile, ?string $businessName): void
    {
        $orders = $profile->orders()->orderByDesc('purchased_at');
        $latest = (clone $orders)->first();
        $totalOrders = (clone $orders)->count();

        $profile->total_orders = $totalOrders;
        $profile->last_purchase_category = $latest?->purchase_category;
        $profile->last_purchase_at = $latest?->purchased_at;
        $profile->next_maintenance_due_date = $latest?->maintenance_due_date;

        if ($latest === null) {
            $profile->lifecycle_status = 'prospect';
        } elseif ($latest->purchased_at->lt(now()->subDays(120))) {
            $profile->lifecycle_status = 'lapsed';
        } else {
            $profile->lifecycle_status = $totalOrders > 1 ? 'repeat_customer' : 'customer';
        }

        if ($profile->trade_review_status === 'approved') {
            $profile->buyer_type = 'Trade';
        } else {
            $profile->buyer_type = 'DIY';
            if ($profile->trade_review_status === 'not_requested'
                && ($totalOrders >= 2 || $this->looksLikeBusiness($businessName))) {
                $profile->trade_review_status = 'pending';
            }
        }

        if ($profile->orders()->whereNotNull('delivered_at')->exists()) {
            $profile->referral_status = 'eligible';
        }

        $profile->save();
        $this->ensureReferralCode($profile);
    }

    private function maintenanceSchedule(Order $order): array
    {
        $cart = $this->decode($order->cart);
        $itemIds = collect($cart)
            ->map(fn ($item, $key) => $item['id'] ?? $item['item_id'] ?? explode('-', (string) $key)[0])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $items = Item::with('category:id,name')
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $schedules = collect($cart)->map(function ($cartItem, $key) use ($items): array {
            $itemId = $cartItem['id'] ?? $cartItem['item_id'] ?? explode('-', (string) $key)[0];
            $item = is_numeric($itemId) ? $items->get((int) $itemId) : null;

            return MaintenanceSchedule::forDescriptions([
                $cartItem['name'] ?? null,
                $cartItem['category_name'] ?? null,
                $item?->name,
                $item?->tags,
                $item?->category?->name,
            ]);
        });

        return $schedules->sortBy('days')->first() ?: MaintenanceSchedule::forDescriptions([]);
    }

    private function identityFromOrder(Order $order): ?array
    {
        $billing = $this->decode($order->billing_info);
        $shipping = $this->decode($order->shipping_info);
        $user = $order->user_id ? User::find($order->user_id) : null;
        $email = $this->email($billing['bill_email'] ?? $shipping['ship_email'] ?? $user?->email);
        $phone = $this->phone($billing['bill_phone'] ?? $shipping['ship_phone'] ?? $user?->phone);

        if ($email === null && ! $user) {
            return null;
        }

        return [
            'identity_hash' => $this->identityHash($email, $user?->id),
            'user_id' => $user?->id,
            'email' => $email,
            'phone' => $phone,
            'first_name' => trim((string) ($billing['bill_first_name'] ?? $shipping['ship_first_name'] ?? $user?->first_name)) ?: null,
            'last_name' => trim((string) ($billing['bill_last_name'] ?? $shipping['ship_last_name'] ?? $user?->last_name)) ?: null,
            'address1' => $billing['bill_address1'] ?? $shipping['ship_address1'] ?? $user?->bill_address1 ?? $user?->ship_address1,
            'address2' => $billing['bill_address2'] ?? $shipping['ship_address2'] ?? $user?->bill_address2 ?? $user?->ship_address2,
            'city' => $billing['bill_city'] ?? $shipping['ship_city'] ?? $user?->bill_city ?? $user?->ship_city,
            'region' => $billing['bill_state'] ?? $shipping['ship_state'] ?? $user?->state?->name,
            'country' => $billing['bill_country'] ?? $shipping['ship_country'] ?? $user?->bill_country ?? $user?->ship_country,
            'postal_code' => $billing['bill_zip'] ?? $shipping['ship_zip'] ?? $user?->bill_zip ?? $user?->ship_zip,
        ];
    }

    private function identityHash(?string $email, ?int $userId): string
    {
        return hash('sha256', $email !== null ? 'email:'.$email : 'user:'.$userId);
    }

    private function ensureReferralCode(CustomerLifecycleProfile $profile): void
    {
        if ($profile->referral_code !== null) {
            return;
        }

        $profile->referral_code = '99A-'.strtoupper(base_convert((string) $profile->id, 10, 36))
            .'-'.strtoupper(substr($profile->identity_hash, 0, 6));
        $profile->save();
    }

    private function businessName(Order $order): ?string
    {
        $billing = $this->decode($order->billing_info);
        $shipping = $this->decode($order->shipping_info);

        return $billing['bill_company']
            ?? $shipping['ship_company']
            ?? ($order->user_id ? User::find($order->user_id)?->bill_company : null)
            ?? ($order->user_id ? User::find($order->user_id)?->ship_company : null);
    }

    private function looksLikeBusiness(?string $name): bool
    {
        return $name !== null && preg_match(
            '/\b(ltd|inc|corp|corporation|motors|automotive|auto repair|garage|fleet|shop)\b/i',
            $name
        ) === 1;
    }

    private function email($email): ?string
    {
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function phone($phone): ?string
    {
        if (trim((string) $phone) === '') {
            return null;
        }

        try {
            return MarketingIdentity::phone((string) $phone);
        } catch (Throwable) {
            return null;
        }
    }

    private function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?: [];
    }
}
