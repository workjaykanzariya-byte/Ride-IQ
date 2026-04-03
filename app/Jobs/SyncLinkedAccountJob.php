<?php

namespace App\Jobs;

use App\Models\LinkedAccount;
use App\Services\DriverSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLinkedAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $linkedAccountId)
    {
    }

    public function handle(DriverSyncService $driverSyncService): void
    {
        $account = LinkedAccount::query()->find($this->linkedAccountId);

        if (! $account || ! $account->is_connected) {
            return;
        }

        $driverSyncService->syncLinkedAccount($account);
    }
}
