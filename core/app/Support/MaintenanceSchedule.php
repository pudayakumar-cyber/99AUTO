<?php

namespace App\Support;

class MaintenanceSchedule
{
    public static function forDescriptions(array $descriptions): array
    {
        $text = strtolower(implode(' ', array_filter($descriptions)));

        if (preg_match('/\b(oil|fluid|lubricant|grease|coolant)\b/', $text)) {
            return ['category' => 'Oil / Fluids', 'days' => 90];
        }

        if (preg_match('/\b(filter|filters)\b/', $text)) {
            return ['category' => 'Filters', 'days' => 180];
        }

        if (preg_match('/\b(brake|brakes|shock|shocks|strut|struts)\b/', $text)) {
            return ['category' => 'Brakes / Shocks', 'days' => 365];
        }

        return ['category' => 'Other', 'days' => 180];
    }
}
