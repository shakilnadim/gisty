<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use function Termwind\render;

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
    private array $gistList = [];
    private string $gistFileName;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        if($this->option('cacheClear')) Cache::forget($this->cacheKey);

        $gists = $this->getGists();
        $this->makeGistList($gists);
        $gistBody = $this->getGistFromUser() . PHP_EOL;
        $fileName = $this->getFileNameFromUser();
        File::put($fileName, $gistBody);
        render('<p class="bg-green p-1">Successful</p>');
    }

    public function getGists(): Collection
    {
        return Cache::rememberForever(
            $this->cacheKey,
            fn() => Http::withToken(env('GH_PERSONAL_ACCESS_TOKEN'))->get('https://api.github.com/gists')->collect()
        );
    }

    public function makeGistList(Collection $gists): void
    {
        $i = 1;
        foreach ($gists as $gist) {
            foreach ($gist['files'] as $file) {
                $this->gistList[$i] = $file['filename'] . " | {$file['raw_url']}";
                $i++;
            }
        }
    }

    public function getGistFromUser(): string
    {
        $selectedGist = $this->choice('Select a gist', $this->gistList);
        $this->gistFileName = trim(explode('|', $selectedGist)[0]);
        $gistRawUrl = trim(explode('|', $selectedGist)[1]);
        return Http::get($gistRawUrl)->body();
    }

    private function getFileNameFromUser(): string
    {
        $path = getcwd() .'/'. $this->ask('input file name');
        if ($this->isFileName($path)) {
            File::ensureDirectoryExists(File::dirname($path));
            return $path;
        }
        File::ensureDirectoryExists($path);
        return $path.'/'.$this->gistFileName;
    }

    public function isFileName($path): bool
    {
        $splitPath = explode('/', $path);
        return str_contains($splitPath[count($splitPath) - 1], '.');
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

