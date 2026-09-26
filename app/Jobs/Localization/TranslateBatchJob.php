<?php

namespace App\Jobs\Localization;

use App\Models\Localization\TranslationEntry;
use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationJobItem;
use App\Services\Localization\AI\AITranslationException;
use App\Services\Localization\AI\AITranslationService;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationFileSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Translates the next batch of a TranslationJob, then queues itself for the next one.
 * Idempotent: only pending items are sent, results are saved per item, and a lock keeps
 * two workers from running the same job at once. Pause/cancel take effect between batches.
 */
class TranslateBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;
    public $timeout = 360;

    public function __construct(public int $translationJobId)
    {
    }

    public function handle(AITranslationService $ai, TranslationCatalog $catalog, TranslationFileSync $files): void
    {
        $lock = Cache::lock('localization-job:' . $this->translationJobId, 400);

        if (!$lock->get()) {
            return; // another batch of this job is running and will queue the next one
        }

        try {
            $next = $this->runBatch($ai, $catalog, $files);
        } finally {
            $lock->release();
        }

        if ($next !== null) {
            static::dispatch($this->translationJobId)->delay($next > 0 ? now()->addSeconds($next) : null);
        }
    }

    /**
     * @return int|null seconds until the next batch, or null to stop
     */
    private function runBatch(AITranslationService $ai, TranslationCatalog $catalog, TranslationFileSync $files): ?int
    {
        $job = TranslationJob::find($this->translationJobId);

        if (!$job or $job->status !== TranslationJob::STATUS_RUNNING) {
            return null;
        }

        $items = TranslationJobItem::where('translation_job_id', $job->id)
            ->where('status', TranslationJobItem::STATUS_PENDING)
            ->orderBy('id')
            ->limit($job->batch_size)
            ->get();

        if ($items->isEmpty()) {
            $this->finish($job, $catalog, $files);
            return null;
        }

        $rows = $catalog->query($job->target_locale)
            ->whereIn('s.key_hash', $items->pluck('key_hash'))
            ->get()
            ->keyBy('key_hash');

        $payload = [];
        foreach ($items as $item) {
            $row = $rows[$item->key_hash] ?? null;

            if (!$row) {
                $item->update(['status' => TranslationJobItem::STATUS_SKIPPED, 'error' => 'The source string no longer exists']);
                continue;
            }

            $payload[] = [
                'hash' => $item->key_hash,
                'group' => $row->group,
                'key' => $row->key,
                'source' => $row->source_value,
                'current' => $row->target_value,
            ];
        }

        if (empty($payload)) {
            $this->refreshCounters($job);
            return 0;
        }

        try {
            $result = $ai->translate($payload, $job->source_locale, $job->target_locale, $job->scope === TranslationJob::SCOPE_RETRANSLATE);
        } catch (AITranslationException $e) {
            return $this->handleProviderError($job, $items, $e);
        }

        foreach ($items as $item) {
            if ($item->status !== TranslationJobItem::STATUS_PENDING) {
                continue;
            }

            if (isset($result['translations'][$item->key_hash])) {
                $saved = $this->save($job, $rows[$item->key_hash], $result['translations'][$item->key_hash]);
                $item->update([
                    'status' => $saved ? TranslationJobItem::STATUS_DONE : TranslationJobItem::STATUS_SKIPPED,
                    'error' => $saved ? null : 'Kept the translation a person entered while the job was running',
                    'attempts' => $item->attempts + 1,
                ]);
            } else {
                $attempts = $item->attempts + 1;
                $item->update([
                    'attempts' => $attempts,
                    'error' => $result['errors'][$item->key_hash] ?? 'No translation returned',
                    'status' => $attempts >= TranslationJobItem::MAX_ATTEMPTS ? TranslationJobItem::STATUS_FAILED : TranslationJobItem::STATUS_PENDING,
                ]);
            }
        }

        $job->increment('batches');
        $job->increment('prompt_tokens', $result['prompt_tokens']);
        $job->increment('completion_tokens', $result['completion_tokens']);
        $job->last_error = null;
        $this->refreshCounters($job);
        $catalog->flushStats();

        return 0;
    }

    /** Write one AI translation unless a person changed the string meanwhile. */
    private function save(TranslationJob $job, object $row, string $text): bool
    {
        $entry = TranslationEntry::where('key_hash', $row->key_hash)->where('locale', $job->target_locale)->first();

        if ($entry and trim((string)$entry->value) !== '') {
            $human = $entry->source === TranslationEntry::SOURCE_MANUAL or $entry->review_status === TranslationEntry::REVIEW_REVIEWED;

            if ($job->scope === TranslationJob::SCOPE_MISSING) {
                return false;
            }
            if ($job->scope === TranslationJob::SCOPE_ALL and $human) {
                return false;
            }
        }

        $entry = $entry ?? new TranslationEntry(['locale' => $job->target_locale, 'group' => $row->group, 'key' => $row->key]);
        $entry->value = $text;
        $entry->status = TranslationEntry::STATUS_CHANGED;
        $entry->source = TranslationEntry::SOURCE_AI;
        $entry->review_status = TranslationEntry::REVIEW_AI;
        $entry->translation_job_id = $job->id;
        $entry->reviewed_by = null;
        $entry->reviewed_at = null;
        $entry->save();

        return true;
    }

    private function handleProviderError(TranslationJob $job, $items, AITranslationException $e): ?int
    {
        $job->update(['last_error' => $e->getMessage()]);

        if (!$e->isRetryable()) {
            // Bad key, no quota, wrong model, request too large: stop until an admin fixes it and resumes.
            $job->update(['status' => TranslationJob::STATUS_PAUSED]);
            return null;
        }

        if ($e->type === AITranslationException::RATE_LIMIT) {
            return $e->retryAfter ?? 30; // waiting is not a failed attempt
        }

        $maxAttempts = 0;
        foreach ($items as $item) {
            $attempts = $item->attempts + 1;
            $maxAttempts = max($maxAttempts, $attempts);
            $item->update([
                'attempts' => $attempts,
                'error' => $e->getMessage(),
                'status' => $attempts >= TranslationJobItem::MAX_ATTEMPTS ? TranslationJobItem::STATUS_FAILED : TranslationJobItem::STATUS_PENDING,
            ]);
        }

        $this->refreshCounters($job);

        return min(120, 15 * $maxAttempts);
    }

    private function finish(TranslationJob $job, TranslationCatalog $catalog, TranslationFileSync $files): void
    {
        $this->refreshCounters($job);

        $job->update([
            'status' => ($job->completed === 0 and $job->failed > 0) ? TranslationJob::STATUS_FAILED : TranslationJob::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);

        if ($job->publish_on_finish and $job->completed > 0) {
            $groups = TranslationJobItem::where('translation_job_id', $job->id)
                ->where('status', TranslationJobItem::STATUS_DONE)
                ->distinct()->pluck('group');

            foreach ($groups as $group) {
                try {
                    $files->publish($group);
                } catch (Throwable $e) {
                    $job->update(['last_error' => 'Publishing "' . $group . '" failed: ' . mb_substr($e->getMessage(), 0, 200)]);
                }
            }
        }

        $catalog->flushStats();
    }

    private function refreshCounters(TranslationJob $job): void
    {
        $counts = TranslationJobItem::where('translation_job_id', $job->id)
            ->groupBy('status')
            ->selectRaw('status, count(*) as c')
            ->pluck('c', 'status');

        $job->completed = (int)($counts[TranslationJobItem::STATUS_DONE] ?? 0) + (int)($counts[TranslationJobItem::STATUS_SKIPPED] ?? 0);
        $job->failed = (int)($counts[TranslationJobItem::STATUS_FAILED] ?? 0);
        $job->save();
    }

    /** Worker crash (timeout, fatal): pause the job with the reason instead of losing it. */
    public function failed(Throwable $e): void
    {
        TranslationJob::where('id', $this->translationJobId)
            ->where('status', TranslationJob::STATUS_RUNNING)
            ->update([
                'status' => TranslationJob::STATUS_PAUSED,
                'last_error' => 'The background worker stopped: ' . mb_substr(preg_replace('/\bsk-[A-Za-z0-9_\-]{4,}/', 'sk-…', $e->getMessage()), 0, 250),
            ]);
    }
}
