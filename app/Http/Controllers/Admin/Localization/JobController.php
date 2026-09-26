<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationJobItem;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationJobManager;
use Illuminate\Http\Request;

class JobController extends LocalizationController
{
    public function index(LanguageRegistry $languages, TranslationJobManager $manager)
    {
        return view('admin.localization.jobs', [
            'pageTitle' => trans('localization.jobs_title'),
            'jobs' => TranslationJob::with('creator:id,full_name')->latest('id')->paginate(20),
            'languages' => $languages,
            'queue' => $manager->queueHealth(),
        ]);
    }

    public function show(Request $request, TranslationJob $job, LanguageRegistry $languages, TranslationJobManager $manager)
    {
        // Failed / skipped strings first, then the ones saved with QA issues (to review).
        $failed = TranslationJobItem::where('translation_job_id', $job->id)
            ->where(function ($q) {
                $q->where(fn($q) => $q->whereIn('status', [TranslationJobItem::STATUS_FAILED, TranslationJobItem::STATUS_SKIPPED])->whereNotNull('error'))
                    ->orWhere(fn($q) => $q->where('status', TranslationJobItem::STATUS_DONE)->where('qa_status', 'needs_review'));
            })
            ->orderByRaw("status = 'done'")
            ->orderBy('id')
            ->paginate(25);

        return view('admin.localization.job', [
            'pageTitle' => trans('localization.job_title', ['id' => $job->id]),
            'job' => $job->load('creator:id,full_name'),
            'languages' => $languages,
            'problems' => $failed,
            'queue' => $manager->queueHealth(),
        ]);
    }

    public function progress(TranslationJob $job, TranslationJobManager $manager)
    {
        $job->refresh();

        return response()->json([
            'status' => $job->status,
            'status_label' => trans('localization.job_status_' . $job->status),
            'total' => $job->total,
            'completed' => $job->completed,
            'failed' => $job->failed,
            'remaining' => $job->remaining(),
            'percent' => $job->progressPercent(),
            'batches' => $job->batches,
            'batches_total' => (int)ceil($job->total / max(1, $job->batch_size)),
            'tokens' => $job->totalTokens(),
            'needs_review' => $job->needs_review,
            'memory_hits' => $job->memory_hits,
            'last_error' => $job->last_error,
            'finished' => !$job->isActive(),
            'queue' => $manager->queueHealth(),
        ]);
    }

    public function pause(TranslationJob $job, TranslationJobManager $manager)
    {
        $manager->pause($job);

        return back()->with($this->toast(trans('localization.job_paused')));
    }

    public function resume(TranslationJob $job, TranslationJobManager $manager)
    {
        try {
            $manager->resume($job);
        } catch (\InvalidArgumentException $e) {
            return back()->with($this->toast($e->getMessage(), false));
        }

        return back()->with($this->toast(trans('localization.job_resumed')));
    }

    public function cancel(TranslationJob $job, TranslationJobManager $manager)
    {
        $manager->cancel($job);

        return back()->with($this->toast(trans('localization.job_cancelled')));
    }

    public function retryFailed(TranslationJob $job, TranslationJobManager $manager)
    {
        $count = $manager->retryFailed($job);

        return back()->with($this->toast(trans('localization.job_retrying', ['count' => $count])));
    }
}
