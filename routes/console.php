<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Run app:crawl-order daily
Schedule::command('app:crawl-order')->dailyAt('2:00');

// Run app:crawl-transaction daily
Schedule::command('app:crawl-transaction')->dailyAt('3:00');
