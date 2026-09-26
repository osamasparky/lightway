<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationEntry;
use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationKeyUsage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read/write access to the translation working copy (ltm_translations), measured
 * against the source language: every non-empty source string is one "string to translate".
 */
class TranslationCatalog
{
    public const STATUS_FILTERS = ['missing', 'translated', 'ai', 'needs_review', 'qa_issues', 'outdated', 'suggestions', 'reviewed', 'manual', 'memory'];

    /** SQL: the translation was made from an older version of the source text. */
    private const OUTDATED_SQL = "(t.source_hash is not null and t.source_hash <> sha1(s.value))";
    public const SORTS = ['key', 'status', 'updated'];

    private const STATS_CACHE = 'localization.stats';

    public function __construct(
        private LanguageRegistry $languages,
        private PlaceholderGuard $guard
    ) {
    }

    /**
     * Coverage per configured language.
     *
     * @return array<string, array{total:int, translated:int, missing:int, ai:int, reviewed:int, needs_review:int, percent:int, updated_at:?string}>
     */
    public function stats(): array
    {
        return Cache::remember(self::STATS_CACHE, 600, function () {
            $source = $this->languages->sourceLocale();
            $locales = $this->languages->locales();

            $total = (int)DB::table('ltm_translations')
                ->where('locale', $source)
                ->whereNotNull('value')->where('value', '<>', '')
                ->count(DB::raw('distinct key_hash'));

            $rows = DB::table('ltm_translations as s')
                ->join('ltm_translations as t', function ($join) use ($locales) {
                    $join->on('t.key_hash', '=', 's.key_hash')
                        ->whereIn('t.locale', $locales)
                        ->whereNotNull('t.value')->where('t.value', '<>', '');
                })
                ->where('s.locale', $source)
                ->whereNotNull('s.value')->where('s.value', '<>', '')
                ->groupBy('t.locale')
                ->selectRaw("t.locale,
                    count(distinct t.key_hash) as translated,
                    count(distinct case when t.review_status = 'ai_translated' then t.key_hash end) as ai,
                    count(distinct case when t.review_status = 'reviewed' then t.key_hash end) as reviewed,
                    count(distinct case when t.review_status in ('ai_translated', 'needs_review') then t.key_hash end) as needs_review,
                    count(distinct case when " . self::OUTDATED_SQL . " then t.key_hash end) as outdated,
                    count(distinct case when t.pending_value is not null then t.key_hash end) as suggestions,
                    count(distinct case when t.qa_status = 'needs_review' then t.key_hash end) as qa_issues,
                    max(t.updated_at) as updated_at")
                ->get()
                ->keyBy('locale');

            $stats = [];
            foreach ($locales as $locale) {
                $row = $rows[$locale] ?? null;
                $translated = $locale == $source ? $total : (int)($row->translated ?? 0);

                $stats[$locale] = [
                    'total' => $total,
                    'translated' => $translated,
                    'missing' => max(0, $total - $translated),
                    'ai' => (int)($row->ai ?? 0),
                    'reviewed' => (int)($row->reviewed ?? 0),
                    'needs_review' => (int)($row->needs_review ?? 0),
                    'outdated' => (int)($row->outdated ?? 0),
                    'suggestions' => (int)($row->suggestions ?? 0),
                    'qa_issues' => (int)($row->qa_issues ?? 0),
                    'percent' => $total > 0 ? (int)floor($translated / $total * 100) : 100,
                    'updated_at' => $row->updated_at ?? null,
                ];
            }

            return $stats;
        });
    }

    public function flushStats(): void
    {
        Cache::forget(self::STATS_CACHE);
    }

    /** Last finished AI job per target locale (for the overview table). */
    public function lastAiRuns(): array
    {
        return TranslationJob::query()
            ->whereIn('status', [TranslationJob::STATUS_COMPLETED, TranslationJob::STATUS_RUNNING, TranslationJob::STATUS_PAUSED])
            ->selectRaw('target_locale, max(coalesce(finished_at, updated_at)) as at')
            ->groupBy('target_locale')
            ->pluck('at', 'target_locale')
            ->all();
    }

    /**
     * Source strings joined with the target language translation.
     */
    public function query(string $locale, array $filters = []): Builder
    {
        $source = $this->languages->sourceLocale();

        $query = DB::table('ltm_translations as s')
            ->leftJoin('ltm_translations as t', function ($join) use ($locale) {
                $join->on('t.key_hash', '=', 's.key_hash')->where('t.locale', '=', $locale);
            })
            ->where('s.locale', $source)
            ->whereNotNull('s.value')->where('s.value', '<>', '')
            ->select([
                's.group', 's.key', 's.key_hash', 's.value as source_value',
                't.id as target_id', 't.value as target_value', 't.review_status', 't.source as entry_source',
                't.status as publish_status', 't.updated_at', 't.reviewed_at',
                't.qa_status', 't.qa_issues', 't.pending_value',
                DB::raw(self::OUTDATED_SQL . ' as is_outdated'),
            ]);

        $missing = "(t.id is null or t.value is null or t.value = '')";

        switch ($filters['status'] ?? null) {
            case 'missing':
                $query->whereRaw($missing);
                break;
            case 'translated':
                $query->whereRaw("not $missing");
                break;
            case 'ai':
                $query->whereRaw("not $missing")->where('t.review_status', TranslationEntry::REVIEW_AI);
                break;
            case 'needs_review':
                $query->whereRaw("not $missing")->whereIn('t.review_status', [TranslationEntry::REVIEW_AI, TranslationEntry::REVIEW_NEEDS_REVIEW]);
                break;
            case 'reviewed':
                $query->whereRaw("not $missing")->where('t.review_status', TranslationEntry::REVIEW_REVIEWED);
                break;
            case 'manual':
                $query->whereRaw("not $missing")->where('t.source', TranslationEntry::SOURCE_MANUAL);
                break;
            case 'memory':
                $query->whereRaw("not $missing")->where('t.source', TranslationEntry::SOURCE_MEMORY);
                break;
            case 'qa_issues':
                $query->whereRaw("not $missing")->where('t.qa_status', TranslationEntry::QA_NEEDS_REVIEW);
                break;
            case 'outdated':
                $query->whereRaw("not $missing")->whereRaw(self::OUTDATED_SQL);
                break;
            case 'suggestions':
                $query->whereNotNull('t.pending_value');
                break;
        }

        if (!empty($filters['group'])) {
            $query->where('s.group', $filters['group']);
        }

        if (!empty($filters['q'])) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('s.key', 'like', $like)
                    ->orWhere('s.group', 'like', $like)
                    ->orWhere('s.value', 'like', $like)
                    ->orWhere('t.value', 'like', $like);
            });
        }

        switch ($filters['sort'] ?? 'key') {
            case 'status':
                $query->orderByRaw("$missing desc")->orderBy('t.review_status')->orderBy('s.group')->orderBy('s.key');
                break;
            case 'updated':
                $query->orderByRaw('t.updated_at is null')->orderByDesc('t.updated_at')->orderBy('s.key');
                break;
            default:
                $query->orderBy('s.group')->orderBy('s.key');
        }

        return $query;
    }

    public function paginate(string $locale, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->query($locale, $filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn($row) => $this->decorate($row));
    }

    /** Groups (files) with their missing count for one language. */
    public function groups(string $locale): array
    {
        $source = $this->languages->sourceLocale();

        return DB::table('ltm_translations as s')
            ->leftJoin('ltm_translations as t', function ($join) use ($locale) {
                $join->on('t.key_hash', '=', 's.key_hash')->where('t.locale', '=', $locale);
            })
            ->where('s.locale', $source)
            ->whereNotNull('s.value')->where('s.value', '<>', '')
            ->groupBy('s.group')
            ->orderBy('s.group')
            ->selectRaw("s.group, count(*) as total, sum(case when t.id is null or t.value is null or t.value = '' then 1 else 0 end) as missing")
            ->get()
            ->map(fn($row) => ['group' => $row->group, 'total' => (int)$row->total, 'missing' => (int)$row->missing])
            ->all();
    }

    /** All groups in the working copy (for tools like publish / add keys). */
    public function allGroups(): array
    {
        return DB::table('ltm_translations')->distinct()->orderBy('group')->pluck('group')->all();
    }

    public function find(string $locale, string $hash): ?object
    {
        $row = $this->query($locale)->where('s.key_hash', $hash)->first();

        return $row ? $this->decorate($row) : null;
    }

    /**
     * Save a human translation. Returns the placeholder problem (if any) without saving
     * unless $force is set.
     */
    public function save(string $locale, string $hash, ?string $value, bool $reviewed, ?int $userId, bool $force = false): array
    {
        $row = $this->find($locale, $hash);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $value = $value === null ? null : rtrim($value, "\r\n");

        if ($value !== null and trim($value) !== '' and !$force) {
            if ($problem = $this->guard->check($row->source_value, $value)) {
                return ['ok' => false, 'error' => 'placeholders', 'message' => $problem];
            }
        }

        $entry = TranslationEntry::where('key_hash', $hash)->where('locale', $locale)->first()
            ?? new TranslationEntry(['locale' => $locale, 'group' => $row->group, 'key' => $row->key]);

        $empty = $value === null || trim($value) === '';

        $entry->value = $empty ? null : $value;
        $entry->status = TranslationEntry::STATUS_CHANGED;
        $entry->source = TranslationEntry::SOURCE_MANUAL;
        $entry->review_status = $empty ? null : ($reviewed ? TranslationEntry::REVIEW_REVIEWED : TranslationEntry::REVIEW_TRANSLATED);
        $entry->reviewed_by = $reviewed && !$empty ? $userId : null;
        $entry->reviewed_at = $reviewed && !$empty ? now() : null;
        // A person wrote it against the current source text; old QA findings no longer apply.
        $entry->source_hash = $empty ? null : sha1($row->source_value);
        $entry->qa_status = null;
        $entry->qa_issues = null;
        $entry->save();

        $this->flushStats();

        return ['ok' => true, 'row' => $this->find($locale, $hash)];
    }

    /**
     * Save a machine result (AI or translation memory) for one string, without ever
     * overwriting human work silently:
     *  - missing: only fills strings that are still empty
     *  - all: replaces machine / imported text, keeps human and approved text
     *  - outdated / retranslate: human and approved text stays live; the new text is kept
     *    aside as a suggestion (pending_value) for a person to accept or reject
     *
     * @param array{text:string, status:string, issues:string[], outcome:string} $result
     * @return string outcome: ai | memory | suggestion | kept
     */
    public function saveMachine(string $locale, object $row, array $result, string $scope, ?int $jobId, bool $autoApprove): string
    {
        $entry = TranslationEntry::where('key_hash', $row->key_hash)->where('locale', $locale)->first();
        $hasText = $entry !== null && trim((string)$entry->value) !== '';
        $human = $hasText && ($entry->source === TranslationEntry::SOURCE_MANUAL || $entry->review_status === TranslationEntry::REVIEW_REVIEWED);
        $issues = $result['issues'] ?: null;

        if ($hasText and ($scope === 'missing' or ($scope === 'all' and $human))) {
            return 'kept';
        }

        if ($human) {
            $entry->pending_value = $result['text'];
            $entry->pending_job_id = $jobId;
            $entry->qa_status = $result['status'];
            $entry->qa_issues = $issues;
            $entry->save();
            $this->flushStats();

            return 'suggestion';
        }

        $memory = $result['outcome'] === 'memory';
        $passed = $result['status'] === TranslationEntry::QA_PASSED;

        $entry = $entry ?? new TranslationEntry(['locale' => $locale, 'group' => $row->group, 'key' => $row->key]);
        $entry->value = $result['text'];
        $entry->status = TranslationEntry::STATUS_CHANGED;
        $entry->source = $memory ? TranslationEntry::SOURCE_MEMORY : TranslationEntry::SOURCE_AI;
        $entry->review_status = match (true) {
            $passed and $autoApprove => TranslationEntry::REVIEW_REVIEWED,
            !$passed => TranslationEntry::REVIEW_NEEDS_REVIEW,
            $memory => TranslationEntry::REVIEW_TRANSLATED,
            default => TranslationEntry::REVIEW_AI,
        };
        $entry->translation_job_id = $jobId;
        $entry->reviewed_by = null;
        $entry->reviewed_at = $passed && $autoApprove ? now() : null;
        $entry->source_hash = sha1($row->source_value);
        $entry->qa_status = $result['status'];
        $entry->qa_issues = $issues;
        $entry->pending_value = null;
        $entry->pending_job_id = null;
        $entry->save();

        $this->flushStats();

        return $memory ? 'memory' : 'ai';
    }

    /** Make the AI suggestion the live translation (approved by the person who accepts it). */
    public function acceptSuggestion(string $locale, string $hash, ?int $userId): bool
    {
        $row = $this->find($locale, $hash);
        $entry = TranslationEntry::where('key_hash', $hash)->where('locale', $locale)->first();

        if (!$row or !$entry or $entry->pending_value === null) {
            return false;
        }

        $entry->value = $entry->pending_value;
        $entry->status = TranslationEntry::STATUS_CHANGED;
        $entry->source = TranslationEntry::SOURCE_AI;
        $entry->review_status = TranslationEntry::REVIEW_REVIEWED;
        $entry->reviewed_by = $userId;
        $entry->reviewed_at = now();
        $entry->source_hash = sha1($row->source_value);
        $entry->translation_job_id = $entry->pending_job_id;
        $entry->pending_value = null;
        $entry->pending_job_id = null;
        $entry->qa_status = null;
        $entry->qa_issues = null;
        $entry->save();

        $this->flushStats();

        return true;
    }

    public function rejectSuggestion(string $locale, string $hash): bool
    {
        $count = TranslationEntry::where('key_hash', $hash)->where('locale', $locale)->whereNotNull('pending_value')
            ->update(['pending_value' => null, 'pending_job_id' => null, 'qa_status' => null, 'qa_issues' => null]);

        $this->flushStats();

        return $count > 0;
    }

    /**
     * Reject a machine translation: the string becomes missing again (it can be re-translated).
     * Human-written and approved text is never removed this way.
     */
    public function rejectMachine(string $locale, string $hash): bool
    {
        $count = TranslationEntry::where('key_hash', $hash)->where('locale', $locale)
            ->whereIn('source', [TranslationEntry::SOURCE_AI, TranslationEntry::SOURCE_MEMORY])
            ->where(fn($q) => $q->whereNull('review_status')->orWhere('review_status', '<>', TranslationEntry::REVIEW_REVIEWED))
            ->update([
                'value' => null, 'status' => TranslationEntry::STATUS_CHANGED, 'review_status' => null,
                'source_hash' => null, 'qa_status' => null, 'qa_issues' => null,
            ]);

        $this->flushStats();

        return $count > 0;
    }

    /** Accept translations as reviewed (keeps the text, which now counts for the current source text). */
    public function markReviewed(string $locale, array $hashes, ?int $userId): int
    {
        $source = $this->languages->sourceLocale();
        $count = 0;

        foreach (array_chunk($hashes, 500) as $chunk) {
            $count += DB::table('ltm_translations as t')
                ->join('ltm_translations as s', function ($join) use ($source) {
                    $join->on('s.key_hash', '=', 't.key_hash')->where('s.locale', '=', $source);
                })
                ->where('t.locale', $locale)
                ->whereIn('t.key_hash', $chunk)
                ->whereNotNull('t.value')->where('t.value', '<>', '')
                ->update([
                    't.review_status' => TranslationEntry::REVIEW_REVIEWED,
                    't.reviewed_by' => $userId,
                    't.reviewed_at' => now(),
                    't.source_hash' => DB::raw('sha1(s.value)'),
                    't.qa_status' => null,
                    't.qa_issues' => null,
                    't.updated_at' => now(),
                ]);
        }

        $this->flushStats();

        return $count;
    }

    /** Strings waiting for review (AI or flagged) among the rows matching the filters. */
    private function pendingReviewQuery(string $locale, array $filters): Builder
    {
        return $this->query($locale, ['group' => $filters['group'] ?? null, 'q' => $filters['q'] ?? null, 'status' => 'needs_review'])
            ->reorder();
    }

    public function countPendingReview(string $locale, array $filters = []): int
    {
        return $this->pendingReviewQuery($locale, $filters)->count();
    }

    /**
     * Approve every string waiting for review that matches the filters (all pages, not only
     * the visible one). Rows whose placeholders don't match the source are left for a human.
     *
     * @return array{reviewed:int, skipped:int}
     */
    public function markAllReviewed(string $locale, array $filters, ?int $userId): array
    {
        $approve = [];
        $skipped = 0;

        foreach ($this->pendingReviewQuery($locale, $filters)->select(['s.key_hash', 's.value as source_value', 't.value as target_value'])->cursor() as $row) {
            if ($this->guard->check($row->source_value, $row->target_value)) {
                $skipped++;
            } else {
                $approve[] = $row->key_hash;
            }
        }

        $reviewed = $approve ? $this->markReviewed($locale, $approve, $userId) : 0;

        return ['reviewed' => $reviewed, 'skipped' => $skipped];
    }

    /** Everything a translator needs to know about one key. */
    public function context(string $locale, string $hash): ?array
    {
        $row = $this->find($locale, $hash);
        if (!$row) {
            return null;
        }

        $others = TranslationEntry::where('key_hash', $hash)
            ->whereNotNull('value')->where('value', '<>', '')
            ->orderBy('locale')
            ->get(['locale', 'value', 'review_status'])
            ->map(fn($entry) => [
                'locale' => $entry->locale,
                'label' => $this->languages->label($entry->locale),
                'value' => $entry->value,
                'dir' => ($this->languages->find($entry->locale)['dir'] ?? 'ltr'),
            ])
            ->all();

        $usages = TranslationKeyUsage::where('key_hash', $hash)
            ->orderBy('file')->limit(15)
            ->get(['file', 'line'])
            ->map(fn($usage) => $usage->file . ':' . $usage->line)
            ->all();

        return [
            'key' => $row->group . '.' . $row->key,
            'file' => 'lang/{locale}/' . $row->group . '.php',
            'source' => $row->source_value,
            'translation' => $row->target_value,
            'placeholders' => $this->guard->describe($row->source_value),
            'has_html' => (bool)preg_match('/<[a-z][^>]*>/i', (string)$row->source_value),
            'usages' => $usages,
            'languages' => $others,
        ];
    }

    private function decorate(object $row): object
    {
        $empty = $row->target_value === null || trim((string)$row->target_value) === '';

        $row->state = match (true) {
            $empty => TranslationEntry::REVIEW_MISSING,
            (bool)($row->is_outdated ?? false) => TranslationEntry::REVIEW_OUTDATED,
            default => $row->review_status ?: TranslationEntry::REVIEW_TRANSLATED,
        };
        $row->origin = $empty ? null : ($row->entry_source ?: 'imported');
        $row->unpublished = !$empty && (int)$row->publish_status === TranslationEntry::STATUS_CHANGED;
        $row->placeholders = $this->guard->describe($row->source_value);
        $issues = json_decode((string)($row->qa_issues ?? ''), true);
        $row->issues = is_array($issues) ? $issues : [];

        return $row;
    }
}
