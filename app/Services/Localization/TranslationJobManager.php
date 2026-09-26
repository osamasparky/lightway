<?php

namespace App\Services\Localization;

use App\Jobs\Localization\TranslateBatchJob;
use App\Models\Localization\TranslationEntry;
use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationJobItem;
use App\Services\Localization\AI\AITranslationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and controls background AI translation jobs.
 *
 * Scopes:
 *  - missing:     only strings with no translation
 *  - all:         everything except human work (manual edits and reviewed strings are kept)
 *  - retranslate: every string, including human work (explicit confirmation required)
 */
class TranslationJobManager
{
    public function __construct(
        private LanguageRegistry $languages,
        private TranslationCatalog $catalog,
        private TranslationSettings $settings,
        private AITranslationService $ai
    ) {
    }

    public function candidates(string $target, string $scope, array $groups = []): Builder
    {
        $query = $this->catalog->query($target);

        if (count($groups)) {
            $query->whereIn('s.group', $groups);
        }

        $missing = "(t.id is null or t.value is null or t.value = '')";

        if ($scope === TranslationJob::SCOPE_MISSING) {
            $query->whereRaw($missing);
        } elseif ($scope === TranslationJob::SCOPE_ALL) {
            $query->where(function ($q) use ($missing) {
                $q->whereRaw($missing)->orWhere(function ($q) {
                    $q->where(function ($q) {
                        $q->whereNull('t.source')->orWhere('t.source', '<>', TranslationEntry::SOURCE_MANUAL);
                    })->where(function ($q) {
                        $q->whereNull('t.review_status')->orWhere('t.review_status', '<>', TranslationEntry::REVIEW_REVIEWED);
                    });
                });
            });
        }

        return $query;
    }

    /** Numbers for the wizard: what exists, what this scope would translate, and an estimate. */
    public function preview(string $target, string $scope, array $groups = []): array
    {
        $stats = $this->catalog->stats()[$target] ?? null;

        $selection = $this->candidates($target, $scope, $groups)->reorder();
        $row = DB::query()->fromSub($selection, 'c')
            ->selectRaw('count(*) as strings, coalesce(sum(char_length(c.source_value)), 0) as chars')
            ->first();

        $strings = (int)$row->strings;
        $batchSize = (int)$this->settings->get('batch_size');

        return [
            'total' => $stats['total'] ?? 0,
            'translated' => $stats['translated'] ?? 0,
            'missing' => $stats['missing'] ?? 0,
            'ai' => $stats['ai'] ?? 0,
            'reviewed' => $stats['reviewed'] ?? 0,
            'selected' => $strings,
            'estimate' => $this->ai->estimate($strings, (int)$row->chars, $batchSize),
            'limit' => (int)$this->settings->get('max_strings_per_job'),
            'over_limit' => $strings > (int)$this->settings->get('max_strings_per_job'),
            'batch_size' => $batchSize,
        ];
    }

    /**
     * @throws InvalidArgumentException with a user-facing reason
     */
    public function start(string $target, string $scope, array $groups, bool $publish, ?int $userId): TranslationJob
    {
        $source = $this->languages->sourceLocale();

        if (!$this->settings->isReady()) {
            throw new InvalidArgumentException(trans('localization.err_not_configured'));
        }
        if (!$this->languages->has($target) or $target === $source) {
            throw new InvalidArgumentException(trans('localization.err_bad_target'));
        }
        if (!in_array($scope, [TranslationJob::SCOPE_MISSING, TranslationJob::SCOPE_ALL, TranslationJob::SCOPE_RETRANSLATE])) {
            throw new InvalidArgumentException(trans('localization.err_bad_scope'));
        }
        if (TranslationJob::where('target_locale', $target)->whereIn('status', [TranslationJob::STATUS_PENDING, TranslationJob::STATUS_RUNNING, TranslationJob::STATUS_PAUSED])->exists()) {
            throw new InvalidArgumentException(trans('localization.err_job_active'));
        }

        $preview = $this->preview($target, $scope, $groups);

        if ($preview['selected'] < 1) {
            throw new InvalidArgumentException(trans('localization.err_nothing_to_translate'));
        }
        if ($preview['over_limit']) {
            throw new InvalidArgumentException(trans('localization.err_over_limit', ['limit' => $preview['limit']]));
        }

        $job = DB::transaction(function () use ($source, $target, $scope, $groups, $publish, $userId) {
            $job = TranslationJob::create([
                'source_locale' => $source,
                'target_locale' => $target,
                'scope' => $scope,
                'groups' => count($groups) ? array_values($groups) : null,
                'status' => TranslationJob::STATUS_RUNNING,
                'provider' => (string)$this->settings->get('provider'),
                'model' => (string)$this->settings->get('model'),
                'batch_size' => (int)$this->settings->get('batch_size'),
                'publish_on_finish' => $publish,
                'created_by' => $userId,
                'started_at' => now(),
            ]);

            // One row per string, in one statement (scales to thousands of strings).
            $select = $this->candidates($target, $scope, $groups)
                ->reorder()
                ->orderBy('s.group')->orderBy('s.key')
                ->select([
                    DB::raw((int)$job->id . ' as translation_job_id'),
                    's.group', 's.key', 's.key_hash',
                    DB::raw("'pending' as status"), DB::raw('0 as attempts'),
                    DB::raw('now() as created_at'), DB::raw('now() as updated_at'),
                ]);

            DB::statement(
                'insert ignore into translation_job_items (translation_job_id, `group`, `key`, key_hash, status, attempts, created_at, updated_at) ' . $select->toSql(),
                $select->getBindings()
            );

            $job->update(['total' => TranslationJobItem::where('translation_job_id', $job->id)->count()]);

            return $job;
        });

        TranslateBatchJob::dispatch($job->id);

        return $job;
    }

    public function pause(TranslationJob $job): void
    {
        if (in_array($job->status, [TranslationJob::STATUS_RUNNING, TranslationJob::STATUS_PENDING])) {
            $job->update(['status' => TranslationJob::STATUS_PAUSED]);
        }
    }

    public function resume(TranslationJob $job): void
    {
        if (in_array($job->status, [TranslationJob::STATUS_PAUSED, TranslationJob::STATUS_FAILED])) {
            $job->update(['status' => TranslationJob::STATUS_RUNNING, 'last_error' => null, 'finished_at' => null]);
            TranslateBatchJob::dispatch($job->id);
        }
    }

    public function cancel(TranslationJob $job): void
    {
        if ($job->isActive()) {
            $job->update(['status' => TranslationJob::STATUS_CANCELLED, 'finished_at' => now()]);
            TranslationJobItem::where('translation_job_id', $job->id)
                ->where('status', TranslationJobItem::STATUS_PENDING)
                ->update(['status' => TranslationJobItem::STATUS_SKIPPED]);
        }
    }

    /** Put failed strings back in the queue and run the job again. */
    public function retryFailed(TranslationJob $job): int
    {
        $count = TranslationJobItem::where('translation_job_id', $job->id)
            ->where('status', TranslationJobItem::STATUS_FAILED)
            ->update(['status' => TranslationJobItem::STATUS_PENDING, 'attempts' => 0, 'error' => null]);

        if ($count > 0) {
            $job->update([
                'status' => TranslationJob::STATUS_RUNNING,
                'failed' => max(0, $job->failed - $count),
                'finished_at' => null,
                'last_error' => null,
            ]);
            TranslateBatchJob::dispatch($job->id);
        }

        return $count;
    }

    /**
     * Is anything processing the queue? Waiting batch jobs older than two minutes mean no worker.
     */
    public function queueHealth(): array
    {
        if (config('queue.default') === 'sync') {
            return ['driver' => 'sync', 'waiting' => 0, 'stalled' => false];
        }

        if (config('queue.default') !== 'database') {
            return ['driver' => config('queue.default'), 'waiting' => null, 'stalled' => false];
        }

        $row = DB::table(config('queue.connections.database.table', 'jobs'))
            ->where('payload', 'like', '%TranslateBatchJob%')
            ->whereNull('reserved_at')
            ->selectRaw('count(*) as waiting, min(available_at) as oldest')
            ->first();

        return [
            'driver' => 'database',
            'waiting' => (int)$row->waiting,
            'stalled' => $row->oldest !== null and (time() - (int)$row->oldest) > 120,
        ];
    }
}
