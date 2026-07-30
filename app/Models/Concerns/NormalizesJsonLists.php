<?php

namespace App\Models\Concerns;

/** Helper for models storing arrays in JSON columns: filters empties, strips null. */
trait NormalizesJsonLists
{
    protected function normalized(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
    }
}
