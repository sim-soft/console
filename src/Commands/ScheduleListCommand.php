<?php

namespace Simsoft\Console\Commands;

use Simsoft\Console\Command;
use Simsoft\Console\Scheduler;

/**
 * Class ScheduleListCommand
 *
 * Lists all registered scheduled tasks.
 */
class ScheduleListCommand extends Command
{
    public static string $name = 'schedule:list';
    public static string $description = 'List all scheduled tasks';

    /**
     * Constructor.
     *
     * @param Scheduler $scheduler
     */
    public function __construct(protected Scheduler $scheduler)
    {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function handle(): void
    {
        $schedules = $this->scheduler->getSchedules();

        if (empty($schedules)) {
            $this->info('No scheduled tasks registered.');
            return;
        }

        $this->table(
            ['Expression', 'Command', 'Description'],
            array_map(fn($schedule) => [
                $schedule->getExpression(),
                $schedule->getCommandName(),
                $schedule->getDescription() ?? '-',
            ], $schedules)
        );
    }
}
