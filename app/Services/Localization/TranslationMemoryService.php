<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationEntry;
use Illuminate\Support\Facades\DB;

/**
 * Translation memory built from the working copy itself: approved (reviewed) or
 * human-written translations of the exact same source text in the same target language.
 * Outdated translations never count.
 *
 * A match is reused without calling the AI only when it is unambiguous: one approved
 * wording for that text, and for very short strings ("Apply", "Open") at least one
 * match from the same file, since short labels depend on context. Other matches are
 * passed to the AI as hints.
 */
class TranslationMemoryService
{
    public function __construct(private LanguageRegistry $languages)
    {
    }

    /**
     * @param array<int, array{hash:string, group:string, source:string}> $items
     * @return array{reuse: array<string,string>, hints: array<string, string[]>}
     */
    public function lookup(string $target, array $items): array
    {
        $texts = array_values(array_unique(array_column($items, 'source')));
        if (empty($texts)) {
            return ['reuse' => [], 'hints' => []];
        }

        $matches = [];
        foreach (array_chunk($texts, 200) as $chunk) {
            foreach ($this->approved($target, $chunk, array_column($items, 'hash')) as $row) {
                $matches[$row->source][] = $row;
            }
        }

        $reuse = [];
        $hints = [];
        foreach ($items as $item) {
            $rows = $matches[$item['source']] ?? [];
            if (!$rows) {
                continue;
            }

            $wordings = array_values(array_unique(array_map(fn($r) => $r->target, $rows)));
            $short = count(preg_split('/\s+/u', trim(strip_tags($item['source'])), -1, PREG_SPLIT_NO_EMPTY)) <= 2;
            $sameFile = array_filter($rows, fn($r) => $r->group === $item['group']);

            if (count($wordings) === 1 and (!$short or $sameFile)) {
                $reuse[$item['hash']] = $wordings[0];
            } else {
                $hints[$item['hash']] = array_slice($wordings, 0, 3);
            }
        }

        return ['reuse' => $reuse, 'hints' => $hints];
    }

    /** Approved translations of one string's exact source text elsewhere (for the editor). */
    public function suggestions(string $target, string $hash): array
    {
        $source = $this->languages->sourceLocale();
        $text = DB::table('ltm_translations')->where('locale', $source)->where('key_hash', $hash)->value('value');

        if ($text === null or $text === '') {
            return [];
        }

        return collect($this->approved($target, [$text], [$hash]))
            ->groupBy('target')
            ->map(fn($rows, $wording) => [
                'text' => $wording,
                'keys' => $rows->map(fn($r) => $r->group . '.' . $r->key)->take(3)->values()->all(),
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5)
            ->all();
    }

    private function approved(string $target, array $texts, array $excludeHashes): array
    {
        $source = $this->languages->sourceLocale();

        return DB::table('ltm_translations as s')
            ->join('ltm_translations as t', function ($join) use ($target) {
                $join->on('t.key_hash', '=', 's.key_hash')->where('t.locale', '=', $target);
            })
            ->where('s.locale', $source)
            ->whereIn('s.value', $texts)
            ->whereNotIn('s.key_hash', $excludeHashes ?: [''])
            ->whereNotNull('t.value')->where('t.value', '<>', '')
            ->where(function ($q) {
                $q->where('t.review_status', TranslationEntry::REVIEW_REVIEWED)
                    ->orWhere('t.source', TranslationEntry::SOURCE_MANUAL);
            })
            ->where(function ($q) {
                $q->whereNull('t.source_hash')->orWhereRaw('t.source_hash = sha1(s.value)');
            })
            ->limit(2000)
            ->get(['s.value as source', 't.value as target', 's.group', 's.key'])
            ->all();
    }
}
