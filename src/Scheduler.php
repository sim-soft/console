<?php

namespace Simsoft\Console;

use DateTimeInterface;

/**
 * Class Scheduler
 *
 * Manages scheduled tasks.
 */
class Scheduler
{
    /** @var Schedule[] Registered schedules. */
    protected array $schedules = [];

    /** @var string|null Path to maintenance mode file. */
    protected ?string $maintenanceFilePath = null;

    /**
     * Register a command to be scheduled.
     *
     * @param string $commandName
     * @param array<string, mixed> $arguments
     * @return Schedule
     */
    public function command(string $commandName, array $arguments = []): Schedule
    {
        $schedule = new Schedule($commandName, $arguments);
        $this->schedules[] = $schedule;
        return $schedule;
    }

    /**
     * Set the maintenance mode file path.
     *
     * When this file exists, the application is considered in maintenance mode
     * and only tasks with `evenInMaintenanceMode()` will run.
     *
     * @param string $path
     * @return $this
     */
    public function maintenanceFile(string $path): static
    {
        $this->maintenanceFilePath = $path;
        return $this;
    }

    /**
     * Check if the application is in maintenance mode.
     *
     * Checks (in order):
     * 1. APP_MAINTENANCE=true env var
     * 2. Configured maintenance file exists
     *
     * @return bool
     */
    public function isInMaintenanceMode(): bool
    {
        if (getenv('APP_MAINTENANCE') === 'true' || getenv('APP_MAINTENANCE') === '1') {
            return true;
        }

        if ($this->maintenanceFilePath && file_exists($this->maintenanceFilePath)) {
            return true;
        }

        return false;
    }

    /**
     * Get all registered schedules.
     *
     * @return Schedule[]
     */
    public function getSchedules(): array
    {
        return $this->schedules;
    }

    /**
     * Get schedules that are due to run.
     *
     * Respects maintenance mode: when active, only tasks with
     * `evenInMaintenanceMode()` are returned.
     *
     * @param DateTimeInterface|string $currentTime
     * @return Schedule[]
     */
    public function getDueSchedules(DateTimeInterface|string $currentTime = 'now'): array
    {
        $inMaintenance = $this->isInMaintenanceMode();

        return array_filter(
            $this->schedules,
            function (Schedule $schedule) use ($currentTime, $inMaintenance) {
                if (!$schedule->isDue($currentTime)) {
                    return false;
                }

                if ($inMaintenance && !$schedule->isRunInMaintenanceMode()) {
                    return false;
                }

                return true;
            }
        );
    }
}
