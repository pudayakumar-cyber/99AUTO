<?php

namespace Tests\Unit;

use App\Support\MaintenanceSchedule;
use PHPUnit\Framework\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    public function test_it_uses_playbook_intervals_for_supported_categories(): void
    {
        $this->assertSame(
            ['category' => 'Oil / Fluids', 'days' => 90],
            MaintenanceSchedule::forDescriptions(['Automotive Lubricants', 'Engine Oil'])
        );
        $this->assertSame(
            ['category' => 'Filters', 'days' => 180],
            MaintenanceSchedule::forDescriptions(['Cabin Air Filter'])
        );
        $this->assertSame(
            ['category' => 'Brakes / Shocks', 'days' => 365],
            MaintenanceSchedule::forDescriptions(['Rear Brake Pads'])
        );
    }

    public function test_it_uses_the_playbook_default_for_other_parts(): void
    {
        $this->assertSame(
            ['category' => 'Other', 'days' => 180],
            MaintenanceSchedule::forDescriptions(['Control Arm'])
        );
    }
}
