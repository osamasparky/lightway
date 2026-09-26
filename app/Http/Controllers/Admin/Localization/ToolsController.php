<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationFileSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

/**
 * The file/working-copy functions of the original Translation Manager
 * (import, find, publish, add keys/groups, delete keys, add/remove languages).
 */
class ToolsController extends LocalizationController
{
    public function import(Request $request, TranslationFileSync $files)
    {
        $data = $request->validate(['replace' => 'nullable|boolean']);
        $counter = $files->import(!empty($data['replace']));

        return back()->with($this->toast(trans('localization.imported', ['count' => $counter])));
    }

    public function find(TranslationFileSync $files)
    {
        $found = $files->findInCode();

        return back()->with($this->toast(trans('localization.found', ['count' => $found])));
    }

    public function publish(Request $request, TranslationFileSync $files, TranslationCatalog $catalog)
    {
        $data = $request->validate(['group' => ['required', 'string', 'max:255', Rule::in(array_merge(['*'], $catalog->allGroups()))]]);

        $files->publish($data['group']);

        return back()->with($this->toast($data['group'] === '*'
            ? trans('localization.published_all')
            : trans('localization.published', ['group' => $data['group']])));
    }

    /** New or existing group; one key per line, optionally "key = source text". */
    public function addKeys(Request $request, TranslationFileSync $files, LanguageRegistry $languages)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'keys' => ['required', 'string', 'max:20000'],
        ]);

        $group = TranslationFileSync::sanitizeName($data['group']);
        abort_if($group === '', 422);

        $keys = [];
        $values = [];
        foreach (preg_split('/\r\n|\n/', $data['keys']) as $line) {
            [$key, $value] = array_pad(array_map('trim', explode('=', $line, 2)), 2, null);
            if ($key !== '') {
                $keys[] = $key;
                if ($value !== null and $value !== '') {
                    $values[$key] = $value;
                }
            }
        }

        $added = $files->addKeys($group, $keys);

        // Source text entered with the key: store it in the source language right away.
        if (count($values)) {
            $source = $languages->sourceLocale();
            foreach ($values as $key => $value) {
                \App\Models\Localization\TranslationEntry::updateOrCreate(
                    ['locale' => $source, 'group' => $group, 'key' => $key],
                    ['value' => $value, 'status' => \App\Models\Localization\TranslationEntry::STATUS_CHANGED, 'source' => 'manual', 'review_status' => 'reviewed']
                );
            }
            app(TranslationCatalog::class)->flushStats();
        }

        return back()->with($this->toast(trans('localization.keys_added', ['count' => $added, 'group' => $group])));
    }

    public function deleteKey(Request $request, TranslationFileSync $files)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:1000'],
        ]);

        $deleted = $files->deleteKey($data['group'], $data['key']);

        return back()->with($this->toast(trans('localization.key_deleted', ['count' => $deleted])));
    }

    public function addLocale(Request $request, TranslationFileSync $files)
    {
        $data = $request->validate(['locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,3}([_-][A-Za-z]{2,4})?$/']]);

        $files->addLocale($data['locale']);

        return back()->with($this->toast(trans('localization.locale_added', ['locale' => $data['locale']])));
    }

    public function removeLocale(Request $request, TranslationFileSync $files, LanguageRegistry $languages)
    {
        $data = $request->validate(['locale' => ['required', 'string', 'max:10']]);

        // Never drop the source language or a language the site currently offers.
        if ($data['locale'] === $languages->sourceLocale() or $languages->has($data['locale'])) {
            return back()->with($this->toast(trans('localization.locale_in_use'), false));
        }

        $files->removeLocale($data['locale']);

        return back()->with($this->toast(trans('localization.locale_removed', ['locale' => $data['locale']])));
    }

    public function scanUsage()
    {
        Artisan::call('localization:scan-usage');

        return back()->with($this->toast(trim(Artisan::output())));
    }
}
