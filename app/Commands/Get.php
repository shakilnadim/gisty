<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Get extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'get {--C|cacheClear : Clear cache before displaying gist list}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Copies gist to file';

    private string $cacheKey = 'gists';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        if($this->option('cacheClear')) Cache::forget($this->cacheKey);
        $gists = $this->getGists();
        $gistList = [];
        $i = 1;
        foreach ($gists as $gist) {
            foreach ($gist['files'] as $file) {
                $gistList[$i] = $file['filename'] . " | {$file['raw_url']}";
                $i++;
            }
        }
        $selectedGist = $this->choice('Select a gist', $gistList);
        $this->info($selectedGist);
    }

    public function getGists(): Collection
    {
        return Cache::rememberForever(
            $this->cacheKey,
            fn() => Http::withToken(env('GH_PERSONAL_ACCESS_TOKEN'))->get('https://api.github.com/gists')->collect()
        );
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}

