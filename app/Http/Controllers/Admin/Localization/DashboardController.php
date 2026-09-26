<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Models\Localization\TranslationEntry;
use App\Models\Localization\TranslationJob;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationFileSync;
use App\Services\Localization\TranslationJobManager;
use App\Services\Localization\TranslationSettings;

class DashboardController extends LocalizationController
{
    public function index(LanguageRegistry $languages, TranslationCatalog $catalog, TranslationFileSync $files, TranslationJobManager $jobs, TranslationSettings $settings)
    {
        $stats = $catalog->stats();
        $source = $languages->sourceLocale();
        $targets = array_diff_key($stats, [$source => true]);

        $activity = TranslationEntry::query()
            ->whereNotNull('source')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['group', 'key', 'locale', 'value', 'source', 'review_status', 'updated_at']);

        return view('admin.localization.index', [
            'pageTitle' => trans('localization.title'),
            'languages' => $languages->all(),
            'source' => $source,
            'stats' => $stats,
            'lastAi' => $catalog->lastAiRuns(),
            'totals' => [
                'strings' => $stats[$source]['total'] ?? 0,
                'translated' => array_sum(array_column($targets, 'translated')),
                'missing' => array_sum(array_column($targets, 'missing')),
                'needs_review' => array_sum(array_column($targets, 'needs_review')),
                'outdated' => array_sum(array_column($targets, 'outdated')),
                'failed' => array_sum(array_map(fn($locale) => $jobs->invalidCount($locale), array_keys($targets))),
            ],
            'targets' => array_keys($targets),
            'recentJobs' => TranslationJob::with('creator:id,full_name')->latest('id')->limit(5)->get(),
            'activity' => $activity,
            'unpublished' => $files->unpublishedGroups(),
            'groups' => $catalog->allGroups(),
            'fileLocales' => $files->locales(),
            'queue' => $jobs->queueHealth(),
            'aiReady' => $settings->isReady(),
        ]);
    }
}
