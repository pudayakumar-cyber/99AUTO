<?php

namespace App\Services;

use App\Models\CustomerLifecycleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TradeReviewService
{
    public function __construct(private CustomerLifecycleService $lifecycle) {}

    public function decide(int $profileId, string $decision, string $expected, string $reviewer, string $reference): CustomerLifecycleProfile
    {
        if (!in_array($decision, ['approved', 'rejected'], true)
            || !in_array($expected, ['not_requested', 'pending', 'approved', 'rejected'], true)
            || trim($reviewer) === '' || strlen($reviewer) > 100
            || trim($reference) === '' || strlen($reference) > 100) {
            throw new InvalidArgumentException('Supply a valid decision, expected status, reviewer and verification reference.');
        }
        $profile = DB::transaction(function () use ($profileId, $decision, $expected, $reviewer, $reference) {
            $profile = CustomerLifecycleProfile::whereKey($profileId)->lockForUpdate()->firstOrFail();
            if (!$profile->user_id) {
                throw new RuntimeException('Trade approval requires a registered customer account.');
            }
            $previous = DB::table('trade_review_decisions')->where('profile_id', $profileId)
                ->where('reference', trim($reference))->first();
            if ($previous) {
                if ($previous->decision !== $decision || $previous->reviewer !== trim($reviewer)) {
                    throw new RuntimeException('This reference already records another decision.');
                }
                // A retry must never overwrite a later decision.
                return $profile;
            }
            if ($profile->trade_review_status !== $expected) {
                throw new RuntimeException('Trade status changed. Review it again before applying.');
            }
            DB::table('trade_review_decisions')->insert([
                'profile_id' => $profileId, 'reference' => trim($reference), 'reviewer' => trim($reviewer),
                'previous_status' => $profile->trade_review_status, 'decision' => $decision, 'created_at' => now(),
            ]);
            $profile->forceFill(['trade_review_status' => $decision,
                'buyer_type' => $decision === 'approved' ? 'Trade' : 'DIY'])->save();
            return $profile;
        });
        // A retry can repair a failed queue dispatch without duplicating the decision.
        $this->lifecycle->sync($profile);
        return $profile;
    }
}
