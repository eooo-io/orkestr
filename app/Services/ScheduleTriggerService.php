<?php

namespace App\Services;

use App\Jobs\RunScheduledAgentJob;
use App\Models\AgentSchedule;
use Illuminate\Support\Facades\Log;

class ScheduleTriggerService
{
    /**
     * Trigger all event-based schedules matching the given event name.
     */
    public static function trigger(string $eventName, array $payload = []): void
    {
        $schedules = AgentSchedule::event()
            ->enabled()
            ->where('event_name', $eventName)
            ->get();

        foreach ($schedules as $schedule) {
            // Check event_filters match payload if filters are defined
            if (! static::matchesFilters($schedule->event_filters, $payload)) {
                continue;
            }

            RunScheduledAgentJob::dispatch($schedule, $payload, 'event');

            Log::info("Event trigger dispatched for schedule {$schedule->id}", [
                'event_name' => $eventName,
                'schedule_name' => $schedule->name,
            ]);
        }
    }

    /**
     * Check if the payload matches all event filters.
     * Each filter key must exist in payload and match the filter value.
     * Supports simple equality matching.
     */
    protected static function matchesFilters(?array $filters, array $payload): bool
    {
        if (empty($filters)) {
            return true;
        }

        foreach ($filters as $key => $value) {
            $actual = data_get($payload, $key);

            if ($actual === null || $actual != $value) {
                return false;
            }
        }

        return true;
    }
}
