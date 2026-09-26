@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

@section('content')
    <section class="section lz">
        <div class="section-header lz-header">
            <div>
                <h1>{{ trans('localization.jobs_title') }}</h1>
                <p class="lz-subtitle">{{ trans('localization.jobs_subtitle') }}</p>
            </div>
            <div class="lz-header__actions">
                <a href="{{ getAdminPanelUrl('/localization') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left lz-flip mr-1"></i>{{ trans('localization.back_to_overview') }}</a>
                @can('admin_translation_manager_ai')
                    <a href="{{ getAdminPanelUrl('/localization/ai') }}" class="btn btn-primary"><i class="fas fa-magic mr-1"></i>{{ trans('localization.ai_translate_language') }}</a>
                @endcan
            </div>
        </div>

        <div class="section-body">
            @include('admin.localization.partials.alerts', ['queue' => $queue])

            <div class="card">
                <div class="card-body p-0">
                    @if($jobs->isEmpty())
                        <div class="lz-empty">
                            <i class="fas fa-robot"></i>
                            <p>{{ trans('localization.no_jobs') }}</p>
                        </div>
                    @else
                        @include('admin.localization.partials.jobs_table', ['jobs' => $jobs, 'registry' => $languages])
                    @endif
                </div>
                @if($jobs->hasPages())
                    <div class="card-footer lz-pagination">{{ $jobs->links() }}</div>
                @endif
            </div>
        </div>
    </section>
@endsection
