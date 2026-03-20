<?php

namespace App\Services\Observability;

use App\Models\AlertIncident;
use App\Models\AlertRule;
use App\Models\CustomMetric;
use App\Services\Notifications\NotificationDispatchService;

class AlertEngine
{
    public function __construct(
        private MetricEvaluator $evaluator,
        private NotificationDispatchService $notifier,
    ) {}

    /**
     * Check all active alert rules and fire incidents for any breached thresholds.
     *
     * @return AlertIncident[]
     */
    public function check(): array
    {
        $rules = AlertRule::active()->with('notificationChannel')->get();
        $fired = [];

        foreach ($rules as $rule) {
            if ($this->isInCooldown($rule)) {
                continue;
            }

            $metric = CustomMetric::where('organization_id', $rule->organization_id)
                ->where('slug', $rule->metric_slug)
                ->first();

            if (! $metric) {
                continue;
            }

            $currentValue = $this->evaluator->evaluateScalar($metric, $rule->window_minutes);

            if ($this->isBreached($rule, $currentValue)) {
                $fired[] = $this->fire($rule, $currentValue);
            }
        }

        return $fired;
    }

    /**
     * Fire an alert incident for a breached rule.
     */
    public function fire(AlertRule $rule, float $currentValue): AlertIncident
    {
        $incident = AlertIncident::create([
            'alert_rule_id' => $rule->id,
            'metric_value' => $currentValue,
            'threshold_value' => $rule->threshold,
            'status' => 'firing',
        ]);

        $rule->update(['last_triggered_at' => now()]);

        // Dispatch notification if a channel is configured
        if ($rule->notificationChannel && $rule->notificationChannel->enabled) {
            $this->notifier->dispatch(
                $rule->notificationChannel,
                'alert.fired',
                [
                    'alert_name' => $rule->name,
                    'severity' => $rule->severity,
                    'metric_slug' => $rule->metric_slug,
                    'current_value' => $currentValue,
                    'threshold' => (float) $rule->threshold,
                    'condition' => $rule->condition,
                    'incident_id' => $incident->uuid,
                ],
            );
        }

        return $incident;
    }

    /**
     * Check whether a rule is currently in its cooldown period.
     */
    public function isInCooldown(AlertRule $rule): bool
    {
        if (! $rule->last_triggered_at) {
            return false;
        }

        return $rule->last_triggered_at->addMinutes($rule->cooldown_minutes)->isFuture();
    }

    /**
     * Determine if the current value breaches the threshold based on the condition.
     */
    private function isBreached(AlertRule $rule, float $currentValue): bool
    {
        $threshold = (float) $rule->threshold;

        return match ($rule->condition) {
            'gt' => $currentValue > $threshold,
            'lt' => $currentValue < $threshold,
            'gte' => $currentValue >= $threshold,
            'lte' => $currentValue <= $threshold,
            'eq' => abs($currentValue - $threshold) < 0.0001,
            default => false,
        };
    }
}
