<?php

namespace App\Support;

/**
 * The editable abandoned-cart reminder cadence (Admin → Abandoned cart
 * settings). Delays are absolute offsets from the moment a cart was abandoned
 * (last activity), NOT cumulative between stages — matching "after 30 minutes,
 * after 1 day, after 7 days, after 1 month, then stop".
 *
 * Shared by the sender command, the cleanup command and the dashboard so they
 * always agree on the flow.
 */
class AbandonedCartFlow
{
    public const UNIT_MINUTES = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    /** Factory-default cadence used until the admin edits it. */
    public const DEFAULT_STAGES = [
        ['enabled' => true, 'delay' => 30, 'unit' => 'minutes', 'template' => 'abandoned_cart'],
        ['enabled' => true, 'delay' => 1, 'unit' => 'days', 'template' => 'abandoned_cart'],
        ['enabled' => true, 'delay' => 7, 'unit' => 'days', 'template' => 'abandoned_cart'],
        ['enabled' => true, 'delay' => 1, 'unit' => 'months', 'template' => 'abandoned_cart'],
    ];

    public static function enabled(): bool
    {
        return (bool) setting('abandoned.enabled', true);
    }

    /** Raw stored stages (for the settings form), or the defaults. */
    public static function rawStages(): array
    {
        $stages = setting('abandoned.stages');

        return is_array($stages) && $stages !== [] ? $stages : self::DEFAULT_STAGES;
    }

    /**
     * Normalised, enabled stages sorted by delay ascending. Each:
     *   ['minutes' => int, 'template' => string, 'label' => string]
     */
    public static function stages(): array
    {
        $out = [];
        foreach (self::rawStages() as $stage) {
            if (! ($stage['enabled'] ?? true)) {
                continue;
            }
            $minutes = self::toMinutes((int) ($stage['delay'] ?? 0), (string) ($stage['unit'] ?? 'days'));
            if ($minutes <= 0) {
                continue;
            }
            $out[] = [
                'minutes' => $minutes,
                'template' => (string) ($stage['template'] ?? 'abandoned_cart') ?: 'abandoned_cart',
                'label' => self::humanDelay((int) ($stage['delay'] ?? 0), (string) ($stage['unit'] ?? 'days')),
            ];
        }

        usort($out, fn ($a, $b) => $a['minutes'] <=> $b['minutes']);

        return $out;
    }

    public static function stageCount(): int
    {
        return count(self::stages());
    }

    /** Minutes for the FIRST enabled stage — the point a cart is "abandoned". */
    public static function firstDelayMinutes(): int
    {
        return self::stages()[0]['minutes'] ?? 30;
    }

    /** Minutes for the LAST enabled stage — after which a cart exits the flow. */
    public static function lastDelayMinutes(): int
    {
        $stages = self::stages();

        return $stages === [] ? 0 : end($stages)['minutes'];
    }

    public static function toMinutes(int $delay, string $unit): int
    {
        // "months" ≈ 30 days for scheduling purposes.
        $multiplier = $unit === 'months' ? 43200 : (self::UNIT_MINUTES[$unit] ?? 1);

        return max(0, $delay) * $multiplier;
    }

    public static function humanDelay(int $delay, string $unit): string
    {
        $unit = rtrim($unit, 's');

        return $delay.' '.$unit.($delay === 1 ? '' : 's');
    }

    /** Options for the stage template <select> in the settings form. */
    public static function unitOptions(): array
    {
        return ['minutes' => 'Minutes', 'hours' => 'Hours', 'days' => 'Days', 'months' => 'Months'];
    }
}
