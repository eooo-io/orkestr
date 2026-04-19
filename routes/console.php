<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schedules:process')->everyMinute()->withoutOverlapping();
Schedule::command('orkestr:run-schedules')->everyMinute()->withoutOverlapping();
Schedule::job(new \App\Jobs\RecomputeAgentReputationJob())->dailyAt('03:00');
Schedule::job(new \App\Jobs\ExtractMemoryPatternsJob())->dailyAt('03:30');
Schedule::job(new \App\Jobs\SuggestSkillPropagationsJob())->dailyAt('04:00');
