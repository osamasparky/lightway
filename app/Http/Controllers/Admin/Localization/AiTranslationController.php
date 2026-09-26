<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Models\Localization\TranslationJob;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationJobManager;
use App\Services\Localization\TranslationSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AiTranslationController extends LocalizationController
{
    public function create(Request $request, LanguageRegistry $languages, TranslationCatalog $catalog, TranslationSettings $settings, TranslationJobManager $jobs)
    {
        $source = $languages->sourceLocale();
        $targets = array_filter($languages->all(), fn($language) => $language['locale'] !== $source);
        $selected = $request->get('locale');

        return view('admin.localization.ai', [
            'pageTitle' => trans('localization.ai_title'),
            'sourceLanguage' => $languages->find($source),
            'targets' => $targets,
            'selected' => isset($targets[$selected]) ? $selected : array_key_first($targets),
            'scope' => in_array($request->get('scope'), ['missing', 'all', 'retranslate']) ? $request->get('scope') : 'missing',
            'groups' => array_column($catalog->groups($source), 'group'),
            'stats' => $catalog->stats(),
            'settings' => $settings->all(),
            'aiReady' => $settings->isReady(),
            'queue' => $jobs->queueHealth(),
            'activeJobs' => TranslationJob::whereIn('status', ['pending', 'running', 'paused'])->pluck('id', 'target_locale'),
        ]);
    }

    public function preview(Request $request, TranslationJobManager $jobs)
    {
        $data = $this->validated($request);

        return response()->json($jobs->preview($data['target'], $data['scope'], $data['groups'] ?? []));
    }

    public function store(Request $request, TranslationJobManager $jobs)
    {
        $data = $this->validated($request, true);

        try {
            $job = $jobs->start($data['target'], $data['scope'], $data['groups'] ?? [], !empty($data['publish']), auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with($this->toast($e->getMessage(), false));
        }

        return redirect($this->url('/jobs/' . $job->id))->with($this->toast(trans('localization.job_started')));
    }

    private function validated(Request $request, bool $confirming = false): array
    {
        $locales = app(LanguageRegistry::class)->locales();

        $rules = [
            'target' => ['required', 'string', Rule::in($locales)],
            'scope' => ['required', Rule::in([TranslationJob::SCOPE_MISSING, TranslationJob::SCOPE_ALL, TranslationJob::SCOPE_RETRANSLATE])],
            'groups' => ['nullable', 'array', 'max:100'],
            'groups.*' => ['string', 'max:255'],
            'publish' => ['nullable', 'boolean'],
        ];

        if ($confirming) {
            $rules['confirm_cost'] = ['accepted'];

            // Overwriting human translations needs its own explicit confirmation.
            // ("accepted" is checked even when the field is absent, so only add it for that scope.)
            if ($request->get('scope') === TranslationJob::SCOPE_RETRANSLATE) {
                $rules['confirm_overwrite'] = ['accepted'];
            }
        }

        return $request->validate($rules);
    }
}
