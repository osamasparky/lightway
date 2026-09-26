<div class="table-responsive">
    <table class="table lz-table lz-jobs mb-0">
        <thead>
        <tr>
            <th>{{ trans('localization.date') }}</th>
            <th>{{ trans('localization.languages') }}</th>
            <th class="lz-col-progress">{{ trans('localization.progress') }}</th>
            <th>{{ trans('localization.status') }}</th>
            <th class="text-right">{{ trans('localization.tokens') }}</th>
            <th class="text-right">{{ trans('localization.cost') }}</th>
            <th class="text-right"><span class="sr-only">{{ trans('localization.actions') }}</span></th>
        </tr>
        </thead>
        <tbody>
        @foreach($jobs as $job)
            @php($cost = $job->actualCost())
            <tr>
                <td class="text-nowrap">
                    <a href="{{ getAdminPanelUrl('/localization/jobs/' . $job->id) }}" class="font-weight-bold">#{{ $job->id }}</a>
                    <div class="small text-muted">{{ $job->created_at->format('M j, H:i') }}</div>
                </td>
                <td>
                    <div class="text-nowrap">{{ $registry->label($job->source_locale) }} <i class="fas fa-long-arrow-alt-right text-muted lz-flip"></i> {{ $registry->label($job->target_locale) }}</div>
                    <span class="lz-tag">{{ trans('localization.scope_' . $job->scope) }}</span>
                    <span class="lz-tag">{{ trans('localization.mode_' . ($job->quality_mode ?: 'economy')) }}</span>
                </td>
                <td>
                    @include('admin.localization.partials.progress', ['percent' => $job->progressPercent()])
                    <div class="small text-muted lz-num">{{ number_format($job->total) }} {{ trans('localization.strings') }}@if($job->needs_review) · {{ number_format($job->needs_review) }} {{ trans('localization.job_needs_review') }}@endif</div>
                </td>
                <td>
                    @include('admin.localization.partials.job_status', ['status' => $job->status])
                    <div class="small text-muted text-nowrap">{{ optional($job->creator)->full_name ?? '—' }}</div>
                </td>
                <td class="text-right lz-num">{{ number_format($job->totalTokens()) }}</td>
                <td class="text-right lz-num">{{ $cost !== null ? '$' . number_format($cost, 2) : '—' }}</td>
                <td class="text-right text-nowrap">
                    <a href="{{ getAdminPanelUrl('/localization/jobs/' . $job->id) }}" class="btn btn-sm btn-outline-primary lz-icon-btn" title="{{ trans('localization.view') }}" aria-label="{{ trans('localization.view') }}"><i class="fas fa-eye"></i></a>
                    @can('admin_translation_manager_ai')
                        @if($job->status === 'running')
                            <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/pause') }}" method="post" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary lz-icon-btn" title="{{ trans('localization.pause') }}" aria-label="{{ trans('localization.pause') }}"><i class="fas fa-pause"></i></button></form>
                        @elseif(in_array($job->status, ['paused', 'failed', 'cancelled']))
                            <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/resume') }}" method="post" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-primary lz-icon-btn" title="{{ trans('localization.resume') }}" aria-label="{{ trans('localization.resume') }}"><i class="fas fa-play"></i></button></form>
                        @endif
                        @if($job->failed > 0 and !$job->isActive())
                            <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/retry-failed') }}" method="post" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-warning lz-icon-btn" title="{{ trans('localization.retry_failed_invalid') }}" aria-label="{{ trans('localization.retry_failed_invalid') }}"><i class="fas fa-redo"></i></button></form>
                        @endif
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
