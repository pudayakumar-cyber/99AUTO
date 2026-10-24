<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StripeCheckoutAttemptStore
{
    /** Reuse the provider intent, including after a response was lost. */
    public function provision(string $id, callable $create, callable $retrieve): object
    {
        $row = DB::table('stripe_checkout_attempts')->where('id', $id)->first();
        if (! $row) {
            throw new RuntimeException('Checkout attempt not found.');
        }
        $snapshot = json_decode(Crypt::decryptString($row->snapshot), true, 512, JSON_THROW_ON_ERROR);
        if ($row->provider_intent_id !== null) {
            $intent = $retrieve($row->provider_intent_id);
        } else {
            // Stripe can prune idempotency keys after 24 hours. An unknown result
            // must be reconciled manually rather than risking another charge.
            if (\Carbon\CarbonImmutable::parse($row->created_at)->lte(now()->subHours(23))) {
                throw new RuntimeException('This payment attempt requires reconciliation before retrying.');
            }
            $intent = $create([
                'amount' => (int) $row->amount,
                'currency' => $row->currency,
                'metadata' => ['checkout_attempt_id' => $id, 'order_number' => $snapshot['transaction_number']],
                'automatic_payment_methods' => ['enabled' => true],
            ], ['idempotency_key' => 'checkout-'.$id]);
        }
        if (! is_object($intent) || ! is_string($intent->id ?? null)
            || ($row->provider_intent_id !== null && $row->provider_intent_id !== $intent->id)
            || (int) ($intent->amount ?? -1) !== (int) $row->amount
            || ($intent->currency ?? null) !== $row->currency
            || ($intent->metadata['checkout_attempt_id'] ?? null) !== $id) {
            throw new RuntimeException('Provider payment does not match the saved checkout.');
        }
        $this->bind($id, $intent->id);
        return $intent;
    }

    public function prepare(string $id, array $snapshot, int $amount, string $currency): array
    {
        if (! \Illuminate\Support\Str::isUuid($id) || $amount < 1
            || ! preg_match('/^[a-z]{3}$/', $currency)
            || ! isset($snapshot['user_id'], $snapshot['cart']) || (int) $snapshot['user_id'] < 0) {
            throw new InvalidArgumentException('Invalid checkout attempt.');
        }
        $identity = $snapshot;
        unset($identity['transaction_number']); // Random order placeholder is not checkout content.
        $hash = hash('sha256', json_encode([$identity, $amount, $currency], JSON_THROW_ON_ERROR));
        // A UUID primary key arbitrates concurrent retries; no provider call happens here.
        DB::table('stripe_checkout_attempts')->insertOrIgnore([
            'id' => $id, 'user_id' => (int) $snapshot['user_id'], 'amount' => $amount,
            'currency' => $currency, 'snapshot_hash' => $hash,
            'snapshot' => Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('stripe_checkout_attempts')->where('id', $id)->first();
        if (! $row || ! hash_equals($row->snapshot_hash, $hash)) {
            throw new RuntimeException('Checkout changed while a payment attempt is unresolved.');
        }
        return json_decode(Crypt::decryptString($row->snapshot), true, 512, JSON_THROW_ON_ERROR);
    }

    public function bind(string $id, string $intentId): void
    {
        DB::transaction(function () use ($id, $intentId) {
            $row = DB::table('stripe_checkout_attempts')->where('id', $id)->lockForUpdate()->first();
            if (! $row || ($row->provider_intent_id !== null && $row->provider_intent_id !== $intentId)) {
                throw new RuntimeException('Payment attempt cannot be rebound.');
            }
            DB::table('stripe_checkout_attempts')->where('id', $id)->update([
                'provider_intent_id' => $intentId, 'updated_at' => now(),
            ]);
        });
    }

    public function recover(string $intentId, int $userId): ?array
    {
        // Guest recovery needs a separate capability; never authorize all user_id=0 rows.
        if ($userId < 1) {
            return null;
        }
        $row = DB::table('stripe_checkout_attempts')->where('provider_intent_id', $intentId)
            ->where('user_id', $userId)->first();
        return $row ? json_decode(Crypt::decryptString($row->snapshot), true, 512, JSON_THROW_ON_ERROR) : null;
    }
}
