<?php
namespace App\Console\Commands;

use App\Services\ZohoBillingService;
use Illuminate\Console\Command;

class SyncMembershipPlans extends Command
{
    protected $signature = 'membership:sync-plans';
    protected $description = 'Sync membership plans from Zoho';
    public function handle(ZohoBillingService $zoho): int { $this->info('Synced '.$zoho->syncPlansFromZoho().' plans'); return self::SUCCESS; }
}
