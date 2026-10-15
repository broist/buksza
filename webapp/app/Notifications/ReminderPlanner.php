<?php

namespace App\Notifications;

use DateTimeImmutable;
use DateTimeZone;

final class ReminderPlanner
{
    public function due(DateTimeImmutable $now, array $settings, bool $annaReported): array
    {
        $zone = new DateTimeZone('Europe/Budapest');
        $local = $now->setTimezone($zone);
        $date = $local->format('Y-m-d');
        $inWindow = function (string $time) use ($local, $zone, $date) {
            $target = new DateTimeImmutable($date.' '.$time.':00', $zone);
            $seconds = $local->getTimestamp() - $target->getTimestamp();

            return $seconds >= 0 && $seconds < 600;
        };
        $daily = $settings['daily_enabled'] && $inWindow($settings['daily_time']);
        $anna = $settings['anna_enabled'] && ! $annaReported && $local->format('d') === $local->format('t') && $inWindow($settings['anna_time']);
        $events = [];
        if ($daily && $anna && $settings['daily_time'] === $settings['anna_time']) {
            $events[] = ['date' => $date, 'kinds' => ['daily', 'anna']];
        } else {
            if ($daily) {
                $events[] = ['date' => $date, 'kinds' => ['daily']];
            }
            if ($anna) {
                $events[] = ['date' => $date, 'kinds' => ['anna']];
            }
        }

        return $events;
    }

    public function payload(string $date, array $kinds, ?array $insights = null): array
    {
        $anna = in_array('anna', $kinds);
        $daily = in_array('daily', $kinds);

        $payload = [
            'title' => $daily && $anna ? 'Esti és hónap végi emlékeztető' : ($anna ? 'Anna hónap végi egyenlege' : 'Esti pénzügyi emlékeztető'),
            'body' => $daily && $anna ? 'Írd be a mai költéseket és Anna hónap végi egyenlegét.' : ($anna ? 'Add meg Anna hónap végi számlaegyenlegét.' : 'Szánj egy percet a mai költések rögzítésére.'),
            'tag' => 'buksza-'.$date.'-'.implode('-', $kinds),
            'url' => $anna && ! $daily ? '/szamlak?year='.substr($date, 0, 4).'&month='.(int) substr($date, 5, 2).'&anna=1' : '/esti-rogzites?date='.$date.($anna ? '&anna=1' : ''),
        ];
        if ($daily && $insights) {
            $budgets = count($insights['budgets']);
            $changes = count($insights['changes']);
            if ($budgets || $changes) {
                $payload['body'] .= ' Figyelmeztetések: '.$budgets.' kategóriakeret, '.$changes.' rendszeres összegváltozás. Részletek az appban.';
            }
        }

        return $payload;
    }
}
