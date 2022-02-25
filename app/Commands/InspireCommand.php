<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use function Termwind\{render};

class InspireCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'inspire {name=Artisan}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display an inspiring quote';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $inspire = Http::get('https://zenquotes.io/api/random')->json();
        render("<div class='text-cyan-500'>{$inspire[0]['h']}</div>");
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
