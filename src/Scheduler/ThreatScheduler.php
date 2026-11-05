<?php

namespace App\Scheduler;

use App\Message\TriggerScheduledFetchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class ThreatScheduler implements ScheduleProviderInterface
{
    private string $timezone;

    public function __construct(string $timezone = 'Africa/Tunis')
    {
        $this->timezone = $timezone;
    }

    public function getSchedule(): Schedule
    {
        $tz = new \DateTimeZone($this->timezone);
        
        return (new Schedule())
            ->with(
                RecurringMessage::cron('0 8 * * 1', new TriggerScheduledFetchMessage(), $tz)
            );
    }
}
