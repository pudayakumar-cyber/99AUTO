<?php

namespace App\Console\Commands;

use App\Models\CartRecoveryLink;
use Illuminate\Console\Command;

class PruneCartRecoveryLinks extends Command
{
    protected $signature = 'klaviyo:prune-cart-links';

    protected $description = 'Delete expired cart recovery links';

    public function handle(): int
    {
        $count = CartRecoveryLink::where('expires_at', '<=', now())->delete();
        $this->info($count.' expired cart recovery links removed.');

        return self::SUCCESS;
    }
}
