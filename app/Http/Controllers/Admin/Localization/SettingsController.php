<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Models\Localization\GlossaryTerm;
use App\Providers\LocalizationServiceProvider;
use App\Services\Localization\AI\AITranslationProvider;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends LocalizationController
{
    public function index(TranslationSettings $settings, LanguageRegistry $languages)
    {
        return view('admin.localization.settings', [
            'pageTitle' => trans('localization.settings_title'),
            'settings' => $settings->all(),
            'maskedKey' => $settings->maskedApiKey(),
            'providers' => array_keys(LocalizationServiceProvider::PROVIDERS),
            'languages' => $languages->all(),
            'glossary' => GlossaryTerm::orderBy('term')->get(),
        ]);
    }

    public function update(Request $request, TranslationSettings $settings)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(LocalizationServiceProvider::PROVIDERS))],
            'model' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-\/]*$/'],
            'batch_size' => ['required', 'integer', 'min:5', 'max:100'],
            'timeout' => ['required', 'integer', 'min:15', 'max:300'],
            'max_strings_per_job' => ['required', 'integer', 'min:1', 'max:100000'],
            'tone' => ['required', 'string', 'max:100'],
            'audience' => ['required', 'string', 'max:300'],
            'product_context' => ['required', 'string', 'max:1000'],
            'price_input_per_million' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'price_output_per_million' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'api_key' => ['nullable', 'string', 'max:300'],
            'remove_api_key' => ['nullable', 'boolean'],
        ]);

        $settings->update($data);

        if (!empty($data['remove_api_key'])) {
            $settings->setApiKey(null);
        } elseif (!empty($data['api_key'])) {
            $settings->setApiKey($data['api_key']);
        }

        return back()->with($this->toast(trans('localization.settings_saved')));
    }

    /** Uses the saved key; the key itself never leaves the server. */
    public function test(AITranslationProvider $provider)
    {
        $result = $provider->testConnection();

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'type' => $result['type'] ?? null,
            'models' => $result['models'],
        ], $result['ok'] ? 200 : 422);
    }

    public function storeTerm(Request $request, LanguageRegistry $languages)
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'translation' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', Rule::in($languages->locales())],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        GlossaryTerm::create($data);

        return back()->with($this->toast(trans('localization.glossary_saved')));
    }

    public function deleteTerm(GlossaryTerm $term)
    {
        $term->delete();

        return back()->with($this->toast(trans('localization.glossary_deleted')));
    }
}
