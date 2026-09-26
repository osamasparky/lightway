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
    public const STATUS_FILTERS = ['missing', 'translated', 'ai', 'needs_review', 'reviewed', 'manual'];
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

        $empty = $value === null or trim($value) === '';

        $entry->value = $empty ? null : $value;
        $entry->status = TranslationEntry::STATUS_CHANGED;
        $entry->source = TranslationEntry::SOURCE_MANUAL;
        $entry->review_status = $empty ? null : ($reviewed ? TranslationEntry::REVIEW_REVIEWED : TranslationEntry::REVIEW_TRANSLATED);
        $entry->reviewed_by = $reviewed && !$empty ? $userId : null;
        $entry->reviewed_at = $reviewed && !$empty ? now() : null;
        $entry->save();

        $this->flushStats();

        return ['ok' => true, 'row' => $this->find($locale, $hash)];
    }

    /** Accept translations as reviewed (keeps the text). */
    public function markReviewed(string $locale, array $hashes, ?int $userId): int
    {
        $count = TranslationEntry::where('locale', $locale)
            ->whereIn('key_hash', $hashes)
            ->whereNotNull('value')->where('value', '<>', '')
            ->update([
                'review_status' => TranslationEntry::REVIEW_REVIEWED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

        $this->flushStats();

        return $count;
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

        $row->state = $empty ? TranslationEntry::REVIEW_MISSING : ($row->review_status ?: TranslationEntry::REVIEW_TRANSLATED);
        $row->origin = $empty ? null : ($row->entry_source ?: 'imported');
        $row->unpublished = !$empty && (int)$row->publish_status === TranslationEntry::STATUS_CHANGED;
        $row->placeholders = $this->guard->describe($row->source_value);

        return $row;
    }
}
