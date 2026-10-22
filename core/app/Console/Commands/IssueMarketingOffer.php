<?php

namespace App\Console\Commands;

use App\Services\MarketingOfferIssuer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class IssueMarketingOffer extends Command
{
    protected $signature = 'marketing:issue-offer {user : Registered customer ID}
        {offer : welcome, cart or winback}
        {--reference= : Stable signup/cart/campaign reference; reuse it for retries}
        {--expires-at= : Exact ISO 8601 expiry with timezone offset}
        {--issue : Write the offer; without this flag only validate the request format}';

    protected $description = 'Issue one customer-bound marketing offer without sending or subscribing anyone';

    public function handle(MarketingOfferIssuer $issuer): int
    {
        $expiry = (string) $this->option('expires-at');
        if (! ctype_digit((string) $this->argument('user')) || (int) $this->argument('user') < 1
            || ! in_array($this->argument('offer'), ['welcome', 'cart', 'winback'], true)
            || trim((string) $this->option('reference')) === ''
            || strlen((string) $this->option('reference')) > 255
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $expiry)) {
            $this->error('Provide a positive customer ID, valid offer, stable reference and ISO expiry with timezone.');
            return self::FAILURE;
        }
        try {
            $expiresAt = CarbonImmutable::parse($expiry);
            if ($expiresAt->format('Y-m-d\TH:i:sP') !== str_replace('Z', '+00:00', $expiry)) {
                throw new \InvalidArgumentException('Invalid calendar date.');
            }
            if (! $this->option('issue')) {
                $this->info('Format valid. No database changes or messages. Use --issue to check customer eligibility and issue the offer.');
                return self::SUCCESS;
            }
            $coupon = $issuer->issue((int) $this->argument('user'), $this->argument('offer'), $this->option('reference'), $expiresAt);
            $this->table(['Coupon ID', 'Code', 'Expiry', 'Active', 'Remaining'], [[
                $coupon->id, $coupon->code_name, $coupon->expires_at?->toAtomString(), $coupon->status, $coupon->no_of_times,
            ]]);
            $this->info('No email/SMS sent. An existing offer retains its original expiry and remaining usage.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Offer could not be issued. Check customer eligibility, expiry and required migrations.');
            return self::FAILURE;
        }
    }
}
