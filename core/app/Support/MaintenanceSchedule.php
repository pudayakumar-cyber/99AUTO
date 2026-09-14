<?php

namespace App\Support;

class MaintenanceSchedule
{
    public static function forDescriptions(array $descriptions): array
    {
        $text = strtolower(implode(' ', array_filter($descriptions)));

        // Oil filters and fuel filters are filters, not oil/fluids.
        if (preg_match('/\bfilters?\b/', $text)) {
            return ['category' => 'Filters', 'days' => 180];
        }

        if (preg_match('/\b(oils?|fluids?|lubricants?|grease|coolants?)\b/', $text)) {
            return ['category' => 'Oil / Fluids', 'days' => 90];
        }

        if (preg_match('/\b(brake|brakes|shock|shocks|strut|struts)\b/', $text)) {
            return ['category' => 'Brakes / Shocks', 'days' => 365];
        }

        return ['category' => 'Other', 'days' => 180];
    }
}
