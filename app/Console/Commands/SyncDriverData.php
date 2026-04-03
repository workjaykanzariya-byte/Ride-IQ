<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DriverSyncService;
use Illuminate\Console\Command;

class SyncDriverData extends Command
{
    protected $signature = 'drivers:sync-data';

    protected $description = 'Sync driver trips and earnings from linked ride providers';

    public function handle(DriverSyncService $driverSyncService): int
    {
        User::query()
            ->where('role', User::ROLE_DRIVER)
            ->chunkById(200, function ($users) use ($driverSyncService): void {
                foreach ($users as $user) {
                    $driverSyncService->dispatchSyncForUser($user);
                }
            });

        $this->info('Driver sync jobs dispatched.');

        return self::SUCCESS;
    }
}
