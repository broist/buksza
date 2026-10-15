<?php

namespace Tests\Unit;

use App\Notifications\ReminderPlanner;
use PHPUnit\Framework\TestCase;

class ReminderPlannerTest extends TestCase
{
    public function test_titles_describe_the_reminder_and_keep_the_matching_destination(): void
    {
        $planner = new ReminderPlanner;
        foreach ([
            [['daily'], 'Esti pénzügyi emlékeztető', '/esti-rogzites?date=2026-10-31'],
            [['anna'], 'Anna hónap végi egyenlege', '/szamlak?year=2026&month=10&anna=1'],
            [['daily', 'anna'], 'Esti és hónap végi emlékeztető', '/esti-rogzites?date=2026-10-31&anna=1'],
        ] as [$kinds, $title, $url]) {
            $payload = $planner->payload('2026-10-31', $kinds);
            self::assertSame($title, $payload['title']);
            self::assertSame($url, $payload['url']);
        }
    }

    private function settings(array $extra = []): array
    {
        return ['daily_enabled' => true, 'anna_enabled' => true, 'daily_time' => '19:00', 'anna_time' => '19:00', ...$extra];
    }

    public function test_budapest_1900_in_winter_summer_and_both_dst_change_dates(): void
    {
        $planner = new ReminderPlanner;
        foreach (['2026-01-14 18:00:00', '2026-07-14 17:00:00', '2026-03-29 17:00:00', '2026-10-25 18:00:00'] as $utc) {
            $now = new \DateTimeImmutable($utc.' UTC');
            self::assertSame(['daily'], $planner->due($now, $this->settings(), false)[0]['kinds']);
            self::assertSame([], $planner->due($now->modify('-1 second'), $this->settings(), false));
            self::assertCount(1, $planner->due($now->modify('+9 minutes 59 seconds'), $this->settings(), false));
            self::assertSame([], $planner->due($now->modify('+10 minutes'), $this->settings(), false));
        }
    }

    public function test_all_month_lengths_leap_year_and_combining_or_splitting_reminders(): void
    {
        $planner = new ReminderPlanner;
        foreach (['2026-01-31', '2026-02-28', '2028-02-29', '2026-04-30', '2026-12-31'] as $date) {
            $now = new \DateTimeImmutable($date.' 19:00:00 Europe/Budapest');
            self::assertSame(['daily', 'anna'], $planner->due($now, $this->settings(), false)[0]['kinds']);
            self::assertSame(['daily'], $planner->due($now, $this->settings(), true)[0]['kinds']);
            self::assertSame(['daily'], $planner->due($now->modify('-1 day'), $this->settings(), false)[0]['kinds']);
            self::assertSame([], $planner->due($now, $this->settings(['daily_enabled' => false, 'anna_enabled' => false]), false));
        }
        $settings = $this->settings(['anna_time' => '20:00']);
        self::assertSame(['daily'], $planner->due(new \DateTimeImmutable('2026-01-31 19:00 Europe/Budapest'), $settings, false)[0]['kinds']);
        self::assertSame(['anna'], $planner->due(new \DateTimeImmutable('2026-01-31 20:00 Europe/Budapest'), $settings, false)[0]['kinds']);
    }
}
