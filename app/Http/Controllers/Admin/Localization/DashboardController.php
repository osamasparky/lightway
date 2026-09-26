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
                'languages' => count($stats),
                'keys' => $stats[$source]['total'] ?? 0,
                'missing' => array_sum(array_column($targets, 'missing')),
                'ai' => array_sum(array_column($targets, 'ai')),
                'reviewed' => array_sum(array_column($targets, 'reviewed')),
                'needs_review' => array_sum(array_column($targets, 'needs_review')),
                'jobs' => TranslationJob::count(),
            ],
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
