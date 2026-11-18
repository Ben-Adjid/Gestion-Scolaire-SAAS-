<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Chaque nuit, les abonnements échus passent en « expire ».
Schedule::command('abonnements:expirer')->dailyAt('00:10');

// Relances à J-7 et J-3 (e-mail et WhatsApp).
Schedule::command('abonnements:relances')->dailyAt('08:00');

// Sauvegarde quotidienne de la base (rotation de 14 jours).
Schedule::command('sauvegarde:executer')->dailyAt('02:00');

Schedule::command('resume:hebdomadaire')->weeklyOn(1, '07:00');
Schedule::command('sante:alertes')->dailyAt('09:00');
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:00');
