<?php

namespace Tests\Unit\Support;

use App\Support\ArenaSchedule;
use Tests\TestCase;

class ArenaScheduleTest extends TestCase
{
    public function test_falls_back_to_global_settings_when_the_weekday_is_missing(): void
    {
        $day = ArenaSchedule::forWeekday(null, 1, true, 120, 3);

        $this->assertTrue($day['open']);
        $this->assertSame(120, $day['cooldown_minutes']);
        $this->assertSame(3, $day['daily_limit']);
    }

    public function test_from_validated_treats_string_zero_as_closed(): void
    {
        $schedule = ArenaSchedule::fromValidated([
            1 => [
                'open' => '0',
                'cooldown_minutes' => '15',
                'daily_limit' => '5',
            ],
        ]);

        $this->assertFalse($schedule[1]['open']);
        $this->assertSame(15, $schedule[1]['cooldown_minutes']);
        $this->assertSame(5, $schedule[1]['daily_limit']);
        $this->assertFalse($schedule[2]['open']);
    }

    public function test_uses_brasilia_weekday_when_utc_already_changed_day(): void
    {
        $this->travelTo('2026-09-15 02:00:00');

        $this->assertSame(1, ArenaSchedule::todayWeekday());
        $this->assertSame('Segunda', ArenaSchedule::todayLabel());
    }

    public function test_toggle_today_only_changes_the_current_weekday(): void
    {
        $this->travelTo('2026-09-14 15:00:00');

        $schedule = ArenaSchedule::week(null, true, 120, 3);
        $updated = ArenaSchedule::toggleToday($schedule, false, true, 120, 3);

        $this->assertFalse($updated[1]['open']);
        $this->assertTrue($updated[2]['open']);
    }

    public function test_cooldown_label_describes_hours_and_no_wait(): void
    {
        $this->assertSame('sem espera', ArenaSchedule::cooldownLabel(0));
        $this->assertSame('1 hora', ArenaSchedule::cooldownLabel(60));
        $this->assertSame('2 horas', ArenaSchedule::cooldownLabel(120));
        $this->assertSame('15 minutos', ArenaSchedule::cooldownLabel(15));
    }
}
