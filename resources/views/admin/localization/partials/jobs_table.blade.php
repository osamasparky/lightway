<div class="table-responsive">
    <table class="table lz-table mb-0">
        <thead>
        <tr>
            <th>{{ trans('localization.date') }}</th>
            <th>{{ trans('localization.languages') }}</th>
            <th>{{ trans('localization.scope') }}</th>
            <th class="text-right">{{ trans('localization.strings') }}</th>
            <th class="lz-col-progress">{{ trans('localization.progress') }}</th>
            <th>{{ trans('localization.status') }}</th>
            <th>{{ trans('localization.user') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($jobs as $job)
            <tr>
                <td class="text-nowrap"><a href="{{ getAdminPanelUrl('/localization/jobs/' . $job->id) }}">#{{ $job->id }}</a> <span class="small text-muted">{{ $job->created_at->format('M j, H:i') }}</span></td>
                <td class="text-nowrap">{{ $registry->label($job->source_locale) }} <i class="fas fa-long-arrow-alt-right text-muted lz-flip"></i> {{ $registry->label($job->target_locale) }}</td>
                <td>{{ trans('localization.scope_' . $job->scope) }}</td>
                <td class="text-right lz-num">{{ number_format($job->total) }}</td>
                <td>@include('admin.localization.partials.progress', ['percent' => $job->progressPercent()])</td>
                <td>@include('admin.localization.partials.job_status', ['status' => $job->status])</td>
                <td class="small">{{ optional($job->creator)->full_name ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
