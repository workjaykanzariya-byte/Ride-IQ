<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('drivers:sync-data')->everyTenMinutes();
