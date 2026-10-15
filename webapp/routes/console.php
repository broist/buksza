<?php

use App\Models\User;
use App\Notifications\ReminderDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('buksza:push-keys', function () {
    if (DB::table('push_credentials')->where('id', 1)->exists()) {
        $this->info('Az értesítési kulcs már létezik.');

        return;
    }
    $keys = VAPID::createVapidKeys();
    DB::table('push_credentials')->insertOrIgnore(['id' => 1, 'public_key' => $keys['publicKey'], 'private_key' => Crypt::encryptString($keys['privateKey']), 'created_at' => now(), 'updated_at' => now()]);
    $this->info('Értesítési kulcs létrehozva és biztonságosan tárolva.');
});
Artisan::command('buksza:reminders', function () {
    $sent = app(ReminderDispatcher::class)->dispatch(new DateTimeImmutable('now'));
    $this->info("Átvett értesítések: $sent.");
});
Schedule::command('buksza:reminders')->everyMinute()->withoutOverlapping(5);

Artisan::command('buksza:setup-link', function () {
    if (User::exists()) {
        $this->info('A saját belépés már létrejött; a meglévő jelszóval lépj be.');

        return;
    }
    $token = config('auth.buksza_setup_token');
    if (! is_string($token) || $token === '') {
        $this->error('Az indítólink nincs beállítva.');

        return 1;
    }
    $this->info('Saját belépés létrehozása (a linket csak te használd):');
    $this->line(config('app.url').'/belepes?setup='.rawurlencode($token));
});
