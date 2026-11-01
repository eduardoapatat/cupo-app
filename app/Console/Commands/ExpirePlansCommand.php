<?php

namespace App\Console\Commands;

use App\Actions\Plans\ExpireUnusedClasses;
use App\Models\PlanPurchase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plans:expire')]
#[Description('Take away the classes left in the plans that expired, leaving a movement that says how many')]
class ExpirePlansCommand extends Command
{
    /**
     * Execute the console command. It runs every night, after the plans of
     * the day before expired at midnight.
     */
    public function handle(ExpireUnusedClasses $expireUnusedClasses): int
    {
        $expired = 0;

        PlanPurchase::query()
            ->whereNull('cancelled_at')
            ->where('expires_at', '<=', now())
            ->where('credits_left', '>', 0)
            ->select('id')
            ->lazyById()
            ->each(function (PlanPurchase $purchase) use ($expireUnusedClasses, &$expired): void {
                $expired += $expireUnusedClasses->handle($purchase);
            });

        $this->info("Classes expired: {$expired}.");

        return self::SUCCESS;
    }
}
