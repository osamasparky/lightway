<?php

namespace App\Console\Commands;

use App\Services\Localization\TranslationJobManager;
use Illuminate\Console\Command;

/**
 * Re-queues running translation jobs whose next batch was lost (killed worker, flushed queue),
 * so an interrupted job always continues where it stopped.
 */
class ResumeStalledTranslationJobs extends Command
{
    protected $signature = 'localization:resume-stalled {--idle=300 : Seconds without progress before a running job is re-queued}';

    protected $description = 'Continue AI translation jobs that stopped making progress';

    public function handle(TranslationJobManager $jobs): int
    {
        $count = $jobs->resumeStalled(max(60, (int)$this->option('idle')));
        $this->info("Re-queued {$count} stalled translation job(s).");

        return self::SUCCESS;
    }
}
