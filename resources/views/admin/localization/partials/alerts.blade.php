@if(!empty($queue['stalled']))
    <div class="alert alert-warning lz-callout" role="alert">
        <strong><i class="fas fa-exclamation-triangle mr-1"></i>{{ trans('localization.queue_stalled_title') }}</strong>
        <div class="small">{{ trans('localization.queue_stalled_hint') }} <code>php artisan queue:work --stop-when-empty</code></div>
    </div>
@elseif(($queue['driver'] ?? null) === 'sync')
    <div class="alert alert-light lz-callout" role="status">
        <i class="fas fa-info-circle mr-1"></i>{{ trans('localization.queue_sync_hint') }}
    </div>
@endif

@if(isset($aiReady) and !$aiReady)
    @can('admin_translation_manager_settings')
        <div class="alert alert-light lz-callout d-flex flex-wrap align-items-center justify-content-between" role="status">
            <span><i class="fas fa-robot mr-1"></i>{{ trans('localization.ai_not_configured') }}</span>
            <a href="{{ getAdminPanelUrl('/localization/settings') }}" class="btn btn-sm btn-outline-primary">{{ trans('localization.configure_ai') }}</a>
        </div>
    @endcan
@endif
