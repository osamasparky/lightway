<?php

namespace App\Jobs\Localization;

use App\Models\Localization\TranslationEntry;
use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationJobItem;
use App\Services\Localization\AI\AITranslationException;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationFileSync;
use App\Services\Localization\TranslationService;
use App\Services\Localization\TranslationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Translates the next batch of a TranslationJob through the TranslationService pipeline,
 * then queues itself for the next one.
 *
 * Idempotent and resumable: only pending items are sent, every result is saved per item,
 * and a lock keeps two workers from running the same job at once. Pause / cancel take
 * effect between batches; a lost batch is re-queued by `localization:resume-stalled`.
 */
class TranslateBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;
    public $timeout = 600;

    public function __construct(public int $translationJobId)
    {
    }

    public function handle(TranslationService $pipeline, TranslationCatalog $catalog, TranslationFileSync $files, TranslationSettings $settings): void
    {
        $lock = Cache::lock('localization-job:' . $this->translationJobId, 660);

        if (!$lock->get()) {
            return; // another batch of this job is running and will queue the next one
        }

        try {
            $next = $this->runBatch($pipeline, $catalog, $files, $settings);
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
    private function runBatch(TranslationService $pipeline, TranslationCatalog $catalog, TranslationFileSync $files, TranslationSettings $settings): ?int
    {
        $job = TranslationJob::find($this->translationJobId);

        if (!$job or $job->status !== TranslationJob::STATUS_RUNNING) {
            return null;
        }

        $items = $this->nextBatch($job);

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
                // Retries tell the AI why its last attempt was rejected.
                'previous_problem' => $item->attempts > 0 ? $item->error : null,
            ];
        }

        if (empty($payload)) {
            $this->refreshCounters($job);
            return 0;
        }

        $mode = in_array($job->scope, [TranslationJob::SCOPE_RETRANSLATE, TranslationJob::SCOPE_OUTDATED])
            ? TranslationService::MODE_IMPROVE
            : TranslationService::MODE_TRANSLATE;

        try {
            $result = $pipeline->process($payload, $job->source_locale, $job->target_locale, $job->quality_mode, $mode, [
                'translation_model' => $job->model,
                'qa_model' => $job->qa_model,
            ]);
        } catch (AITranslationException $e) {
            return $this->handleProviderError($job, $items, $e);
        }

        $autoApprove = (int)$settings->get('auto_approve_passed') === 1;

        foreach ($items as $item) {
            if ($item->status !== TranslationJobItem::STATUS_PENDING) {
                continue;
            }

            $outcome = $result['results'][$item->key_hash] ?? null;

            if ($outcome and $outcome['text'] !== null) {
                // Economy output is never auto-approved: only strings a QA model also approved.
                $approve = $autoApprove && ($job->usesAiQa() || $outcome['outcome'] === 'memory');
                $saved = $catalog->saveMachine($job->target_locale, $rows[$item->key_hash], $outcome, $job->scope, $job->id, $approve);

                $item->update([
                    'status' => $saved === TranslationJobItem::OUTCOME_KEPT ? TranslationJobItem::STATUS_SKIPPED : TranslationJobItem::STATUS_DONE,
                    'outcome' => $saved,
                    'qa_status' => $outcome['status'],
                    'qa_issues' => $outcome['issues'] ?: null,
                    'error' => $saved === TranslationJobItem::OUTCOME_KEPT ? 'Kept the translation a person entered while the job was running' : null,
                    'attempts' => $item->attempts + ($outcome['outcome'] === 'memory' ? 0 : 1),
                ]);
            } else {
                $attempts = $item->attempts + 1;
                $item->update([
                    'attempts' => $attempts,
                    'error' => $outcome['error'] ?? 'No translation returned',
                    'qa_status' => TranslationEntry::QA_FAILED,
                    'qa_issues' => $outcome['issues'] ?? null,
                    'status' => $attempts >= TranslationJobItem::MAX_ATTEMPTS ? TranslationJobItem::STATUS_FAILED : TranslationJobItem::STATUS_PENDING,
                ]);
            }
        }

        $job->increment('batches');
        $job->prompt_tokens += $result['prompt_tokens'];
        $job->completion_tokens += $result['completion_tokens'];
        $job->qa_prompt_tokens += $result['qa_prompt_tokens'];
        $job->qa_completion_tokens += $result['qa_completion_tokens'];
        $job->api_requests += $result['requests'];
        $job->last_error = null;
        $this->refreshCounters($job);
        $catalog->flushStats();

        if (!empty($result['qa_error'])) {
            // This batch is saved (flagged for review); stop before the next one until the QA model is fixed.
            $job->update(['status' => TranslationJob::STATUS_PAUSED, 'last_error' => $result['qa_error']]);
            return null;
        }

        return 0;
    }

    /** Pending items of one language file (the first pending item's), so a batch shares its context. */
    private function nextBatch(TranslationJob $job)
    {
        $first = TranslationJobItem::where('translation_job_id', $job->id)
            ->where('status', TranslationJobItem::STATUS_PENDING)
            ->orderBy('id')
            ->first();

        if (!$first) {
            return collect();
        }

        return TranslationJobItem::where('translation_job_id', $job->id)
            ->where('status', TranslationJobItem::STATUS_PENDING)
            ->where('group', $first->group)
            ->orderBy('id')
            ->limit(max(1, $job->batch_size))
            ->get();
    }

    private function handleProviderError(TranslationJob $job, $items, AITranslationException $e): ?int
    {
        $job->update(['last_error' => $e->getMessage()]);

        if (!$e->isRetryable()) {
            // Bad key, no quota, wrong model, request rejected: stop until an admin fixes it and resumes.
            $job->update(['status' => TranslationJob::STATUS_PAUSED]);
            return null;
        }

        if ($e->type === AITranslationException::RATE_LIMIT) {
            return $e->retryAfter ?? 30; // waiting is not a failed attempt
        }

        if ($e->type === AITranslationException::TOO_LONG and $job->batch_size > 5) {
            // The answer did not fit: smaller batches from now on, same strings again.
            $job->update(['batch_size' => max(5, intdiv($job->batch_size, 2))]);
            return 0;
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

        // Exponential backoff: 15s, 30s, 60s ... capped at 5 minutes.
        return min(300, 15 * (2 ** max(0, $maxAttempts - 1)));
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
                ->whereIn('outcome', [TranslationJobItem::OUTCOME_AI, TranslationJobItem::OUTCOME_MEMORY])
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
        $job->needs_review = TranslationJobItem::where('translation_job_id', $job->id)
            ->where('status', TranslationJobItem::STATUS_DONE)->where('qa_status', TranslationEntry::QA_NEEDS_REVIEW)->count();
        $job->memory_hits = TranslationJobItem::where('translation_job_id', $job->id)
            ->where('outcome', TranslationJobItem::OUTCOME_MEMORY)->count();
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
