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
    private string $fileContents;
    private string $fileName;
    private array $contentArr = [];
    private bool $shouldAddTab = false;

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
        $this->setFileNameFromUser();
        $this->setFileContents();
        $this->contentArr = explode(PHP_EOL, $this->fileContents);
        $lineNumber = $this->getLineNumber();

        if ($lineNumber === 0) $this->error('No class found');
        elseif ($this->writeToFile($gistBody, $lineNumber)) render('<p class="bg-green p-1">Successful</p>');
        else render('<p class="bg-red p-1">Failure! Something went wrong</p>');
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

    private function getGistFromUser(): string
    {
        $selectedGist = $this->choice('Select a gist', $this->gistList);
        $this->gistFileName = trim(explode('|', $selectedGist)[0]);
        $gistRawUrl = trim(explode('|', $selectedGist)[1]);
        return Http::get($gistRawUrl)->body();
    }

    private function setFileNameFromUser(): void
    {
        $path = getcwd() .'/'. $this->ask('Input file name');
        if ($this->isFileName($path)) {
            File::ensureDirectoryExists(File::dirname($path));
            $this->fileName = $path;
        } else {
            File::ensureDirectoryExists($path);
            $this->fileName = $path.'/'.$this->gistFileName;
        }
    }

    public function isFileName($path): bool
    {
        $splitPath = explode('/', $path);
        return str_contains($splitPath[count($splitPath) - 1], '.');
    }

    private function getLineNumber(): int
    {
        if (!File::exists($this->fileName)) return 0;
        if (trim($this->fileContents) === '') return 0;
        $lineNumber = $this->getLineNumberFromUser();

        if ($lineNumber === -1) {
            $isFileAClass = $this->choice('Is the target file a class?', [1 => 'true', 2 => 'false'], 2);
            $isFileAClass = $isFileAClass === 'true';
            if ($isFileAClass) $this->shouldAddTab = true;
            return $this->getLastLineOfFile($isFileAClass);
        }

        return $lineNumber;
    }

    public function getLineNumberFromUser(): int
    {
        while (true) {
            $lineNumber = $this->ask('Input line number') ?? -1;
            if (filter_var($lineNumber, FILTER_VALIDATE_INT) && $lineNumber !== 0 && $lineNumber == -1) return (int)$lineNumber;
            $this->error('Line number has to be a positive integer value');
        }
    }


    private function getLastLineOfFile(bool $isFileAClass = false): int
    {
        if (!$isFileAClass) return count($this->contentArr);

        for ($i=count($this->contentArr) - 1; $i >= 0; $i--) {
            if (trim($this->contentArr[$i]) === '}') return ++$i;
            if (str_ends_with(trim($this->contentArr[$i]), '}')) {
                $this->contentArr[$i] = substr($this->contentArr[$i], 0, -1);
                array_splice($this->contentArr, ++$i, 0, '}');
                return ++$i;
            }
        }
        return 0;
    }

    private function setFileContents(): void
    {
        $this->fileContents = File::get($this->fileName);
    }

    private function writeToFile($body, $lineNumber): bool
    {
        if ($lineNumber === 0) return File::put($this->fileName, $body);

        if ($this->shouldAddTab) $body = $this->addTabOnEachLine($body);
        array_splice($this->contentArr, $lineNumber-1, 0, $body);
        $body = implode(PHP_EOL, $this->contentArr);
        return File::put($this->fileName, $body);
    }

    public function addTabOnEachLine(string $body): string
    {
        $body = explode(PHP_EOL, $body);
        for($i=0; $i<count($body); $i++) {
            $body[$i] = "\t{$body[$i]}";
        }
        return implode(PHP_EOL, $body);
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

