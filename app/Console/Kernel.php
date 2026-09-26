<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Background work (AI translation batches, queued mail) without a permanent worker:
        // with `* * * * * php artisan schedule:run` in cron, the queue is drained every minute.
        if (config('queue.default') === 'database') {
            $schedule->command('queue:work --stop-when-empty --max-time=55 --sleep=3')
                ->everyMinute()
                ->withoutOverlapping(5)
                ->runInBackground();
        }

        // Where translation keys are used (context in the Translation Manager).
        $schedule->command('localization:scan-usage')->dailyAt('03:30')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
