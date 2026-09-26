<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Services\Localization\AI\AITranslationException;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationMemoryService;
use App\Services\Localization\TranslationService;
use App\Services\Localization\TranslationSettings;
use Illuminate\Http\Request;

class WorkspaceController extends LocalizationController
{
    public function show(Request $request, string $locale, LanguageRegistry $languages, TranslationCatalog $catalog, TranslationSettings $settings)
    {
        $language = $this->language($locale);
        $source = $languages->find($languages->sourceLocale());

        $filters = [
            'status' => in_array($request->get('status'), TranslationCatalog::STATUS_FILTERS) ? $request->get('status') : null,
            'group' => $request->get('group') ?: null,
            'q' => mb_substr(trim((string)$request->get('q')), 0, 200) ?: null,
            'sort' => in_array($request->get('sort'), TranslationCatalog::SORTS) ? $request->get('sort') : 'key',
        ];

        return view('admin.localization.workspace', [
            'pageTitle' => $language['name'] . ' · ' . trans('localization.title'),
            'language' => $language,
            'sourceLanguage' => $source,
            'isSource' => $language['locale'] === $source['locale'],
            'stats' => $catalog->stats()[$language['locale']] ?? null,
            'rows' => $catalog->paginate($language['locale'], $filters),
            'groups' => $catalog->groups($language['locale']),
            'filters' => $filters,
            'aiReady' => $settings->isReady(),
            'pendingReview' => $language['locale'] === $source['locale'] ? 0 : $catalog->countPendingReview($language['locale'], $filters),
        ]);
    }

    public function context(string $locale, string $hash, TranslationCatalog $catalog, TranslationMemoryService $memory)
    {
        $this->language($locale);
        $context = $catalog->context($locale, $hash);

        abort_unless($context, 404);

        // Translation memory: how the same text is already translated (approved) elsewhere.
        $context['memory'] = $memory->suggestions($locale, $hash);

        return response()->json($context);
    }

    /** Accept / reject the AI suggestion kept aside for a human or approved translation. */
    public function suggestion(Request $request, string $locale, TranslationCatalog $catalog)
    {
        $this->language($locale);

        $data = $request->validate([
            'hash' => 'required|string|size:40',
            'action' => 'required|in:accept,reject',
        ]);

        $ok = $data['action'] === 'accept'
            ? $catalog->acceptSuggestion($locale, $data['hash'], auth()->id())
            : $catalog->rejectSuggestion($locale, $data['hash']);

        abort_unless($ok, 404);

        return response()->json($this->rowPayload($catalog->find($locale, $data['hash']), $catalog, $locale));
    }

    /** Reject a machine translation: the string becomes missing again. Human text is never removed here. */
    public function reject(Request $request, string $locale, TranslationCatalog $catalog)
    {
        $this->language($locale);

        $data = $request->validate(['hash' => 'required|string|size:40']);

        if (!$catalog->rejectMachine($locale, $data['hash'])) {
            return response()->json(['message' => trans('localization.reject_only_machine')], 422);
        }

        return response()->json($this->rowPayload($catalog->find($locale, $data['hash']), $catalog, $locale));
    }

    public function save(Request $request, string $locale, TranslationCatalog $catalog)
    {
        $this->language($locale);

        $data = $request->validate([
            'hash' => 'required|string|size:40',
            'value' => 'nullable|string|max:20000',
            'reviewed' => 'nullable|boolean',
            'force' => 'nullable|boolean',
        ]);

        $reviewed = !empty($data['reviewed']) && auth()->user()->can('admin_translation_manager_review');

        $result = $catalog->save($locale, $data['hash'], $data['value'] ?? null, $reviewed, auth()->id(), !empty($data['force']));

        if (!$result['ok']) {
            return response()->json([
                'error' => $result['error'],
                'message' => $result['error'] === 'placeholders'
                    ? trans('localization.placeholder_warning', ['problem' => $result['message']])
                    : trans('localization.not_found'),
            ], $result['error'] === 'not_found' ? 404 : 422);
        }

        return response()->json($this->rowPayload($result['row'], $catalog, $locale));
    }

    public function review(Request $request, string $locale, TranslationCatalog $catalog)
    {
        $this->language($locale);

        $data = $request->validate([
            'hashes' => 'required|array|max:200',
            'hashes.*' => 'string|size:40',
        ]);

        $count = $catalog->markReviewed($locale, $data['hashes'], auth()->id());

        return response()->json([
            'reviewed' => $count,
            'state_label' => trans('localization.state_reviewed'),
            'stats' => $catalog->stats()[$locale] ?? null,
        ]);
    }

    /** "Approve all": every string waiting for review that matches the current file/search filters. */
    public function reviewAll(Request $request, string $locale, TranslationCatalog $catalog, LanguageRegistry $languages)
    {
        $this->language($locale);
        abort_if($locale === $languages->sourceLocale(), 422);

        $data = $request->validate([
            'group' => 'nullable|string|max:255',
            'q' => 'nullable|string|max:200',
        ]);

        $result = $catalog->markAllReviewed($locale, $data, auth()->id());

        return response()->json([
            'reviewed' => $result['reviewed'],
            'skipped' => $result['skipped'],
            'message' => trans('localization.approved_all_done', ['count' => number_format($result['reviewed'])])
                . ($result['skipped'] ? ' ' . trans('localization.approved_all_skipped', ['count' => number_format($result['skipped'])]) : ''),
            'stats' => $catalog->stats()[$locale] ?? null,
        ]);
    }

    /**
     * One AI suggestion for the editor, through the full pipeline (context, glossary,
     * token protection, validation and the language's QA mode). Nothing is saved until
     * the admin accepts it.
     */
    public function suggest(Request $request, string $locale, TranslationCatalog $catalog, TranslationService $pipeline, LanguageRegistry $languages)
    {
        $this->language($locale);

        $data = $request->validate(['hash' => 'required|string|size:40']);
        $row = $catalog->find($locale, $data['hash']);
        abort_unless($row, 404);

        try {
            $result = $pipeline->process([[
                'hash' => $row->key_hash,
                'group' => $row->group,
                'key' => $row->key,
                'source' => $row->source_value,
                'current' => $row->target_value,
            ]], $languages->sourceLocale(), $locale, null, !empty($row->target_value) ? TranslationService::MODE_IMPROVE : TranslationService::MODE_TRANSLATE);
        } catch (AITranslationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->type === AITranslationException::NOT_CONFIGURED ? 409 : 502);
        }

        $outcome = $result['results'][$row->key_hash] ?? null;

        if ($outcome and $outcome['text'] !== null) {
            return response()->json(['suggestion' => $outcome['text'], 'qa_status' => $outcome['status'], 'issues' => $outcome['issues']]);
        }

        return response()->json(['message' => $outcome['error'] ?? trans('localization.ai_no_result')], 422);
    }

    private function rowPayload(object $row, TranslationCatalog $catalog, string $locale): array
    {
        return [
            'hash' => $row->key_hash,
            'value' => $row->target_value,
            'state' => $row->state,
            'state_label' => trans('localization.state_' . $row->state),
            'origin' => $row->origin,
            'origin_label' => $row->origin ? trans('localization.origin_' . $row->origin) : '',
            'unpublished' => $row->unpublished,
            'issues' => $row->issues,
            'pending' => $row->pending_value,
            'stats' => $catalog->stats()[$locale] ?? null,
        ];
    }
}
