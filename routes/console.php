<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('drivers:sync-data')->everyTenMinutes();

Schedule::command('membership:sync-plans')->daily();
Schedule::command('membership:sync-statuses')->hourly();
