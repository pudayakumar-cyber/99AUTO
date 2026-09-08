<?php

namespace App\Services;

use App\Jobs\SyncKlaviyoProfileConsent;
use App\Models\MarketingConsent;
use App\Models\MarketingConsentEvent;
use App\Models\User;
use App\Support\MarketingIdentity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarketingConsentService
{
    public const EMAIL_TEXT = 'I agree to receive marketing emails from 99 Auto Parts. I can unsubscribe at any time.';

    public const SMS_TEXT = 'I agree to receive recurring marketing text messages from 99 Auto Parts. Message and data rates may apply. Reply STOP to unsubscribe.';

    public function isSubscribed(string $channel, string $identity): bool
    {
        try {
            $identity = MarketingIdentity::normalize($channel, $identity);
        } catch (InvalidArgumentException) {
            return false;
        }

        return MarketingConsent::where('channel', $channel)
            ->where('identity_hash', hash('sha256', $identity))
            ->where('status', 'subscribed')
            ->exists();
    }

    public function setConsent(
        string $channel,
        string $identity,
        bool $subscribed,
        string $source,
        ?User $user = null,
        ?string $consentText = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): MarketingConsent {
        $identity = MarketingIdentity::normalize($channel, $identity);
        $identityHash = hash('sha256', $identity);
        $status = $subscribed ? 'subscribed' : 'unsubscribed';
        $changed = false;

        $consent = DB::transaction(function () use (
            $channel,
            $identity,
            $identityHash,
            $status,
            $source,
            $user,
            $consentText,
            $ipAddress,
            $userAgent,
            &$changed
        ): MarketingConsent {
            $consent = MarketingConsent::where('channel', $channel)
                ->where('identity_hash', $identityHash)
                ->lockForUpdate()
                ->first();

            $changed = ! $consent || $consent->status !== $status;
            $consent ??= new MarketingConsent();
            $consent->fill([
                'user_id' => $user?->id ?? $consent->user_id,
                'channel' => $channel,
                'identity' => $identity,
                'identity_hash' => $identityHash,
                'status' => $status,
                'source' => $source,
                'consent_text' => $consentText,
                'consented_at' => $subscribed ? now() : $consent->consented_at,
                'revoked_at' => $subscribed ? null : now(),
                'last_ip' => $ipAddress,
                'last_user_agent' => $userAgent,
            ])->save();

            if ($changed) {
                MarketingConsentEvent::create([
                    'marketing_consent_id' => $consent->id,
                    'user_id' => $user?->id,
                    'channel' => $channel,
                    'identity' => $identity,
                    'identity_hash' => $identityHash,
                    'action' => $status,
                    'source' => $source,
                    'consent_text' => $consentText,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'occurred_at' => now(),
                ]);
            }

            return $consent;
        });

        if ($changed) {
            SyncKlaviyoProfileConsent::dispatch($consent->id);
        }

        return $consent;
    }

    public static function textFor(string $channel): string
    {
        return $channel === 'sms' ? self::SMS_TEXT : self::EMAIL_TEXT;
    }
}
