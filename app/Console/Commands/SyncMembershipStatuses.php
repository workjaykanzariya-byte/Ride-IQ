<?php
namespace App\Console\Commands;

use App\Models\UserSubscription;
use Illuminate\Console\Command;

class SyncMembershipStatuses extends Command
{
    protected $signature = 'membership:sync-statuses';
    protected $description = 'Auto-disable expired memberships and flag failed payments';
    public function handle(): int { UserSubscription::where('status','active')->whereNotNull('end_date')->where('end_date','<',now())->update(['status'=>'expired']); return self::SUCCESS; }
}
