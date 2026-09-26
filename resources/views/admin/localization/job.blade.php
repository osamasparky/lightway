@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

@php
    $canControl = auth()->user()->can('admin_translation_manager_ai');
    $batchesTotal = (int)ceil($job->total / max(1, $job->batch_size));
@endphp

@section('content')
    <section class="section lz">
        <div class="section-header lz-header">
            <div>
                <h1>{{ trans('localization.job_title', ['id' => $job->id]) }}</h1>
                <p class="lz-subtitle">
                    {{ $languages->label($job->source_locale) }} <i class="fas fa-long-arrow-alt-right lz-flip"></i> {{ $languages->label($job->target_locale) }}
                    · {{ trans('localization.scope_' . $job->scope) }}
                </p>
            </div>
            <div class="lz-header__actions">
                <a href="{{ getAdminPanelUrl('/localization/jobs') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left lz-flip mr-1"></i>{{ trans('localization.all_jobs') }}</a>
                @if($languages->has($job->target_locale))
                    <a href="{{ getAdminPanelUrl('/localization/languages/' . $job->target_locale . '?status=needs_review') }}" class="btn btn-outline-primary"><i class="fas fa-check-double mr-1"></i>{{ trans('localization.review_results') }}</a>
                @endif
            </div>
        </div>

        <div class="section-body">
            @include('admin.localization.partials.alerts', ['queue' => $queue])

            <div class="card lz-job js-lz-job" data-progress="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/progress') }}" data-active="{{ in_array($job->status, ['running', 'pending']) ? 1 : 0 }}">
                <div class="card-body">
                    <div class="lz-job__head">
                        <div>
                            @include('admin.localization.partials.job_status', ['status' => $job->status])
                            <span class="lz-job__big"><span class="js-lz-job-percent">{{ $job->progressPercent() }}</span>%</span>
                        </div>
                        @if($canControl)
                            <div class="lz-job__controls">
                                @if(in_array($job->status, ['running', 'pending']))
                                    <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/pause') }}" method="post">@csrf<button type="submit" class="btn btn-outline-warning"><i class="fas fa-pause mr-1"></i>{{ trans('localization.pause') }}</button></form>
                                @endif
                                @if(in_array($job->status, ['paused', 'failed']))
                                    <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/resume') }}" method="post">@csrf<button type="submit" class="btn btn-primary"><i class="fas fa-play mr-1"></i>{{ trans('localization.resume') }}</button></form>
                                @endif
                                @if($job->failed > 0 and !in_array($job->status, ['running', 'pending']))
                                    <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/retry-failed') }}" method="post">@csrf<button type="submit" class="btn btn-outline-primary"><i class="fas fa-redo mr-1"></i>{{ trans('localization.retry_failed') }}</button></form>
                                @endif
                                @if($job->isActive())
                                    <form action="{{ getAdminPanelUrl('/localization/jobs/' . $job->id . '/cancel') }}" method="post" class="js-lz-confirm" data-confirm="{{ trans('localization.cancel_job_confirm') }}">@csrf<button type="submit" class="btn btn-outline-danger"><i class="fas fa-stop mr-1"></i>{{ trans('localization.cancel_job') }}</button></form>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="lz-progress lz-progress--lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $job->progressPercent() }}" aria-label="{{ trans('localization.progress') }}">
                        <div class="lz-progress__track"><div class="lz-progress__bar js-lz-job-bar" style="width: {{ $job->progressPercent() }}%"></div></div>
                    </div>
                    <p class="text-muted mt-2 mb-0"><span class="js-lz-job-done">{{ number_format($job->completed + $job->failed) }}</span> / {{ number_format($job->total) }} {{ trans('localization.strings') }}</p>

                    <div class="alert alert-danger mt-3 js-lz-job-error {{ $job->last_error ? '' : 'd-none' }}" role="alert">
                        <strong>{{ trans('localization.last_error') }}:</strong> <span class="js-lz-job-error-text">{{ $job->last_error }}</span>
                    </div>

                    <dl class="lz-summary__stats mt-4">
                        <div><dt>{{ trans('localization.completed') }}</dt><dd class="js-lz-job-completed">{{ number_format($job->completed) }}</dd></div>
                        <div><dt>{{ trans('localization.remaining') }}</dt><dd class="js-lz-job-remaining">{{ number_format($job->remaining()) }}</dd></div>
                        <div><dt>{{ trans('localization.failed') }}</dt><dd class="js-lz-job-failed {{ $job->failed ? 'text-danger' : '' }}">{{ number_format($job->failed) }}</dd></div>
                        <div><dt>{{ trans('localization.batches') }}</dt><dd><span class="js-lz-job-batches">{{ $job->batches }}</span> / {{ $batchesTotal }}</dd></div>
                        <div><dt>{{ trans('localization.tokens_used') }}</dt><dd class="js-lz-job-tokens">{{ number_format($job->prompt_tokens + $job->completion_tokens) }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-lg-5">
                    <div class="card">
                        <div class="card-header"><h4>{{ trans('localization.details') }}</h4></div>
                        <div class="card-body">
                            <dl class="lz-details">
                                <dt>{{ trans('localization.started') }}</dt><dd>{{ optional($job->started_at)->format('Y-m-d H:i:s') ?? '—' }}</dd>
                                <dt>{{ trans('localization.finished') }}</dt><dd>{{ optional($job->finished_at)->format('Y-m-d H:i:s') ?? '—' }}</dd>
                                <dt>{{ trans('localization.duration') }}</dt><dd>{{ $job->started_at ? $job->started_at->diffForHumans($job->finished_at ?? now(), true) : '—' }}</dd>
                                <dt>{{ trans('localization.provider') }}</dt><dd>{{ ucfirst($job->provider) }}</dd>
                                <dt>{{ trans('localization.model') }}</dt><dd><code>{{ $job->model }}</code></dd>
                                <dt>{{ trans('localization.batch_size') }}</dt><dd>{{ $job->batch_size }}</dd>
                                <dt>{{ trans('localization.files') }}</dt><dd>{{ $job->groups ? implode(', ', $job->groups) : trans('localization.all_files') }}</dd>
                                <dt>{{ trans('localization.publish_when_done') }}</dt><dd>{{ $job->publish_on_finish ? trans('localization.yes') : trans('localization.no') }}</dd>
                                <dt>{{ trans('localization.tokens_in_out') }}</dt><dd>{{ number_format($job->prompt_tokens) }} / {{ number_format($job->completion_tokens) }}</dd>
                                <dt>{{ trans('localization.user') }}</dt><dd>{{ optional($job->creator)->full_name ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="card">
                        <div class="card-header"><h4>{{ trans('localization.problems') }}</h4></div>
                        <div class="card-body p-0">
                            @if($problems->isEmpty())
                                <div class="lz-empty lz-empty--sm"><i class="fas fa-check-circle text-success"></i><p>{{ trans('localization.no_problems') }}</p></div>
                            @else
                                <div class="table-responsive">
                                    <table class="table lz-table mb-0">
                                        <thead><tr><th>{{ trans('localization.key') }}</th><th>{{ trans('localization.status') }}</th><th>{{ trans('localization.reason') }}</th></tr></thead>
                                        <tbody>
                                        @foreach($problems as $item)
                                            <tr>
                                                <td><code>{{ $item->group }}.{{ \Illuminate\Support\Str::limit($item->key, 50) }}</code></td>
                                                <td><span class="badge badge-{{ $item->status === 'failed' ? 'danger' : 'light' }}">{{ trans('localization.item_' . $item->status) }}</span></td>
                                                <td class="small">{{ $item->error }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        @if($problems->hasPages())
                            <div class="card-footer lz-pagination">{{ $problems->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts_bottom')
    @include('admin.localization.partials.script')
@endpush
