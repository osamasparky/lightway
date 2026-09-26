<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Models\Localization\GlossaryTerm;
use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationProfile;
use App\Providers\LocalizationServiceProvider;
use App\Services\Localization\AI\AITranslationProvider;
use App\Services\Localization\AI\TranslationPromptBuilder;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationProfiles;
use App\Services\Localization\TranslationSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends LocalizationController
{
    public const TABS = ['provider', 'models', 'profiles', 'rules', 'glossary', 'quality', 'limits', 'costs'];

    public function index(Request $request, TranslationSettings $settings, LanguageRegistry $languages, TranslationProfiles $profiles)
    {
        $source = $languages->sourceLocale();
        $targets = array_filter($languages->all(), fn($language) => $language['locale'] !== $source);

        return view('admin.localization.settings', [
            'pageTitle' => trans('localization.settings_title'),
            'tab' => in_array($request->get('tab'), self::TABS) ? $request->get('tab') : 'provider',
            'settings' => $settings->all(),
            'maskedKey' => $settings->maskedApiKey(),
            'models' => $settings->availableModels(),
            'modelPrices' => $settings->modelPrices(),
            'providers' => array_keys(LocalizationServiceProvider::PROVIDERS),
            'languages' => $languages->all(),
            'targets' => $targets,
            'profiles' => TranslationProfile::all()->keyBy('target_locale'),
            'resolved' => collect($targets)->mapWithKeys(fn($language, $locale) => [$locale => $profiles->resolve($locale)])->all(),
            'glossary' => GlossaryTerm::orderBy('term')->get(),
            'baseInstructions' => TranslationPromptBuilder::BASE_INSTRUCTIONS,
        ]);
    }

    /** Saves the fields of one settings tab (each tab has its own form). */
    public function update(Request $request, TranslationSettings $settings)
    {
        $model = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-\/]*$/'];

        $data = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'provider' => ['sometimes', 'required', Rule::in(array_keys(LocalizationServiceProvider::PROVIDERS))],
            'model' => ['sometimes', ...$model],
            'model_custom' => $model,
            'qa_model' => ['sometimes', ...$model],
            'qa_model_custom' => $model,
            'premium_model' => ['sometimes', ...$model],
            'premium_model_custom' => $model,
            'default_quality_mode' => ['sometimes', 'required', Rule::in(TranslationJob::MODES)],
            'batch_size' => ['sometimes', 'required', 'integer', 'min:5', 'max:100'],
            'timeout' => ['sometimes', 'required', 'integer', 'min:15', 'max:300'],
            'max_strings_per_job' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            'tone' => ['sometimes', 'required', 'string', 'max:100'],
            'audience' => ['sometimes', 'required', 'string', 'max:300'],
            'product_context' => ['sometimes', 'required', 'string', 'max:1000'],
            'translation_rules' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'price_input_per_million' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000'],
            'price_output_per_million' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000'],
            'model_prices' => ['sometimes', 'array', 'max:20'],
            'model_prices.*.in' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'model_prices.*.out' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'max_cost_per_job' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'use_translation_memory' => ['sometimes', 'boolean'],
            'auto_approve_passed' => ['sometimes', 'boolean'],
            'expansion_button' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'expansion_navigation' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'expansion_label' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'expansion_title' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'api_key' => ['nullable', 'string', 'max:300'],
            'remove_api_key' => ['nullable', 'boolean'],
        ], [
            'regex' => trans('localization.model_invalid'),
        ]);

        // "Other" in a model dropdown: use the typed model name.
        foreach (['model', 'qa_model', 'premium_model'] as $field) {
            if (($data[$field] ?? null) === '__custom') {
                $data[$field] = trim((string)($data[$field . '_custom'] ?? ''));
            }
            unset($data[$field . '_custom']);
        }

        if (array_key_exists('model_prices', $data)) {
            $prices = [];
            foreach ($data['model_prices'] as $name => $row) {
                if (preg_match('/^[A-Za-z0-9._:\-\/]+$/', (string)$name) and is_numeric($row['in'] ?? null) and is_numeric($row['out'] ?? null)) {
                    $prices[$name] = ['in' => (float)$row['in'], 'out' => (float)$row['out']];
                }
            }
            $data['model_prices'] = $prices ? json_encode($prices) : null;
        }

        foreach (['use_translation_memory', 'auto_approve_passed'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (int)$data[$flag];
            }
        }

        $tab = $data['tab'] ?? 'provider';
        $settings->update(array_diff_key($data, array_flip(['tab', 'api_key', 'remove_api_key'])));

        if (!empty($data['remove_api_key'])) {
            $settings->setApiKey(null);
        } elseif (!empty($data['api_key'])) {
            $settings->setApiKey($data['api_key']);
        }

        return redirect($this->url('/settings?tab=' . $tab))->with($this->toast(trans('localization.settings_saved')));
    }

    /**
     * Tests the key typed in the form (not saved) or, when empty, the saved key.
     * The key is never returned in the response or logged.
     */
    public function test(Request $request, AITranslationProvider $provider, TranslationSettings $settings)
    {
        $data = $request->validate(['api_key' => ['nullable', 'string', 'max:300']]);

        if (!empty($data['api_key'])) {
            $provider = $provider->withKey($data['api_key']);
        }

        $result = $provider->testConnection();

        if ($result['ok'] and count($result['models'])) {
            $settings->update(['available_models' => json_encode(array_values($result['models']))]);
        }

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'type' => $result['type'] ?? null,
            'models' => $result['models'],
        ], $result['ok'] ? 200 : 422);
    }

    /** Create or update the translation profile of one target language. */
    public function saveProfile(Request $request, LanguageRegistry $languages)
    {
        $source = $languages->sourceLocale();
        $model = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-\/]*$/'];

        $data = $request->validate([
            'target_locale' => ['required', Rule::in(array_values(array_diff($languages->locales(), [$source])))],
            'name' => ['required', 'string', 'max:150'],
            'locale_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/'],
            'region' => ['nullable', 'string', 'max:100'],
            'tone' => ['nullable', 'string', 'max:100'],
            'audience' => ['nullable', 'string', 'max:300'],
            'product_context' => ['nullable', 'string', 'max:1000'],
            'rules' => ['nullable', 'string', 'max:3000'],
            'cultural_notes' => ['nullable', 'string', 'max:2000'],
            'quality_mode' => ['nullable', Rule::in(TranslationJob::MODES)],
            'translation_model' => $model,
            'qa_model' => $model,
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = (bool)($data['active'] ?? false);

        TranslationProfile::updateOrCreate(['target_locale' => $data['target_locale']], $data);

        return redirect($this->url('/settings?tab=profiles#profile-' . $data['target_locale']))->with($this->toast(trans('localization.profile_saved')));
    }

    public function deleteProfile(string $locale)
    {
        TranslationProfile::where('target_locale', $locale)->delete();

        return redirect($this->url('/settings?tab=profiles'))->with($this->toast(trans('localization.profile_deleted')));
    }

    public function storeTerm(Request $request, LanguageRegistry $languages)
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'translation' => [Rule::requiredIf(fn() => in_array($request->get('type'), ['preferred', 'technical', 'context', 'forbidden'])), 'nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(GlossaryTerm::TYPES)],
            'locale' => ['nullable', Rule::in($languages->locales())],
            'context' => [Rule::requiredIf(fn() => $request->get('type') === 'context'), 'nullable', 'string', 'max:255'],
            'rule' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (in_array($data['type'], [GlossaryTerm::TYPE_DO_NOT_TRANSLATE, GlossaryTerm::TYPE_BRAND])) {
            $data['translation'] = null;
        }

        GlossaryTerm::create($data + ['active' => true]);

        return redirect($this->url('/settings?tab=glossary'))->with($this->toast(trans('localization.glossary_saved')));
    }

    public function toggleTerm(GlossaryTerm $term)
    {
        $term->update(['active' => !$term->active]);

        return redirect($this->url('/settings?tab=glossary'))->with($this->toast(trans('localization.glossary_saved')));
    }

    public function deleteTerm(GlossaryTerm $term)
    {
        $term->delete();

        return redirect($this->url('/settings?tab=glossary'))->with($this->toast(trans('localization.glossary_deleted')));
    }
}
