<div class="table-responsive">
    <table class="table lz-table lz-jobs mb-0">
        <thead>
        <tr>
            <th>{{ trans('localization.date') }}</th>
            <th>{{ trans('localization.languages') }}</th>
            <th class="lz-col-progress">{{ trans('localization.progress') }}</th>
            <th>{{ trans('localization.status') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($jobs as $job)
            <tr>
                <td class="text-nowrap">
                    <a href="{{ getAdminPanelUrl('/localization/jobs/' . $job->id) }}" class="font-weight-bold">#{{ $job->id }}</a>
                    <div class="small text-muted">{{ $job->created_at->format('M j, H:i') }}</div>
                </td>
                <td>
                    <div class="text-nowrap">{{ $registry->label($job->source_locale) }} <i class="fas fa-long-arrow-alt-right text-muted lz-flip"></i> {{ $registry->label($job->target_locale) }}</div>
                    <span class="lz-tag">{{ trans('localization.scope_' . $job->scope) }}</span>
                </td>
                <td>
                    @include('admin.localization.partials.progress', ['percent' => $job->progressPercent()])
                    <div class="small text-muted lz-num">{{ number_format($job->total) }} {{ trans('localization.strings') }}</div>
                </td>
                <td>
                    @include('admin.localization.partials.job_status', ['status' => $job->status])
                    <div class="small text-muted text-nowrap">{{ optional($job->creator)->full_name ?? '—' }}</div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
