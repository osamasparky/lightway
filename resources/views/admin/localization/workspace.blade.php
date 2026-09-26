@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

@php
    $canEdit = auth()->user()->can('admin_translation_manager_edit');
    $canReview = auth()->user()->can('admin_translation_manager_review') && !$isSource;
    $canAi = auth()->user()->can('admin_translation_manager_ai') && $aiReady && !$isSource;
    $statusOptions = ['' => trans('localization.filter_all')] + collect(\App\Services\Localization\TranslationCatalog::STATUS_FILTERS)->mapWithKeys(fn($s) => [$s => trans('localization.filter_' . $s)])->all();
@endphp

@section('content')
    <section class="section lz">
        <div class="section-header lz-header">
            <div>
                <h1>
                    {{ $language['name'] }}
                    @if($language['native'] !== $language['name'])
                        <span class="lz-native">— <bdi dir="{{ $language['dir'] }}">{{ $language['native'] }}</bdi></span>
                    @endif
                </h1>
                <p class="lz-subtitle">
                    <code>{{ $language['locale'] }}</code> · {{ strtoupper($language['dir']) }}
                    @unless($isSource)
                        · {{ trans('localization.translating_from', ['language' => $sourceLanguage['name']]) }}
                    @endunless
                </p>
            </div>
            <div class="lz-header__actions">
                <a href="{{ getAdminPanelUrl('/localization') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left lz-flip mr-1"></i>{{ trans('localization.back_to_overview') }}</a>
                @if(!$isSource and ($stats['missing'] ?? 0) > 0)
                    @can('admin_translation_manager_ai')
                        <a href="{{ getAdminPanelUrl('/localization/ai?locale=' . $language['locale'] . '&scope=missing') }}" class="btn btn-primary"><i class="fas fa-magic mr-1"></i>{{ trans('localization.translate_missing') }}</a>
                    @endcan
                @endif
            </div>
        </div>

        <div class="section-body">
            @include('admin.localization.partials.alerts', ['queue' => [], 'aiReady' => $isSource ? true : $aiReady])

            {{-- Stats --}}
            <div class="card lz-summary">
                <div class="card-body">
                    <div class="lz-summary__progress">
                        <span class="lz-summary__percent js-lz-percent">{{ $stats['percent'] ?? 0 }}%</span>
                        <span class="text-muted">{{ trans('localization.translated_lower') }}</span>
                        @include('admin.localization.partials.progress', ['percent' => $stats['percent'] ?? 0])
                    </div>
                    <dl class="lz-summary__stats">
                        @foreach(['total', 'translated', 'missing', 'needs_review', 'reviewed', 'ai'] as $name)
                            <div>
                                <dt>{{ trans('localization.stat_' . $name) }}</dt>
                                <dd class="js-lz-stat-{{ $name }}">{{ number_format($stats[$name] ?? 0) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            {{-- Filters --}}
            <form method="get" class="card lz-filters" role="search">
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="col-12 col-lg-4 form-group">
                            <label for="lzQ">{{ trans('localization.search') }}</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                                <input type="search" id="lzQ" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ trans('localization.search_placeholder') }}">
                            </div>
                        </div>
                        <div class="col-6 col-lg-2 form-group">
                            <label for="lzStatus">{{ trans('localization.status') }}</label>
                            <select id="lzStatus" name="status" class="form-control js-lz-autosubmit">
                                @foreach($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['status'] === ($value ?: null))>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-3 form-group">
                            <label for="lzGroup">{{ trans('localization.file') }}</label>
                            <select id="lzGroup" name="group" class="form-control js-lz-autosubmit">
                                <option value="">{{ trans('localization.all_files') }}</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group['group'] }}" @selected($filters['group'] === $group['group'])>
                                        {{ $group['group'] }}{{ !$isSource && $group['missing'] ? ' · ' . trans('localization.n_missing', ['count' => $group['missing']]) : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-2 form-group">
                            <label for="lzSort">{{ trans('localization.sort') }}</label>
                            <select id="lzSort" name="sort" class="form-control js-lz-autosubmit">
                                @foreach(\App\Services\Localization\TranslationCatalog::SORTS as $sort)
                                    <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ trans('localization.sort_' . $sort) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-1 form-group d-flex lz-gap">
                            <button type="submit" class="btn btn-primary btn-block">{{ trans('localization.filter') }}</button>
                        </div>
                    </div>
                    @if($filters['q'] or $filters['status'] or $filters['group'])
                        <a href="{{ url()->current() }}" class="small">{{ trans('localization.clear_filters') }}</a>
                    @endif
                </div>
            </form>

            {{-- Rows --}}
            <div class="card">
                <div class="card-header">
                    <h4>{{ trans('localization.n_strings', ['count' => number_format($rows->total())]) }}</h4>
                    @if($canReview)
                        <div class="card-header-action">
                            <button type="button" class="btn btn-sm btn-outline-success js-lz-bulk-review" disabled>
                                <i class="fas fa-check-double mr-1"></i>{{ trans('localization.mark_selected_reviewed') }}
                            </button>
                        </div>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($rows->isEmpty())
                        <div class="lz-empty">
                            <i class="fas {{ $filters['status'] === 'missing' ? 'fa-check-circle text-success' : 'fa-search' }}"></i>
                            <p>{{ $filters['status'] === 'missing' ? trans('localization.empty_missing') : trans('localization.empty_filtered') }}</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table lz-table lz-workspace mb-0"
                                   data-locale="{{ $language['locale'] }}" data-dir="{{ $language['dir'] }}"
                                   data-can-edit="{{ $canEdit ? 1 : 0 }}" data-can-review="{{ $canReview ? 1 : 0 }}" data-can-ai="{{ $canAi ? 1 : 0 }}">
                                <thead>
                                <tr>
                                    @if($canReview)
                                        <th class="lz-col-check"><input type="checkbox" class="js-lz-select-all" aria-label="{{ trans('localization.select_all') }}"></th>
                                    @endif
                                    <th class="lz-col-key">{{ trans('localization.key') }}</th>
                                    @unless($isSource)
                                        <th class="lz-col-text">{{ $sourceLanguage['name'] }}</th>
                                    @endunless
                                    <th class="lz-col-text">{{ $language['name'] }}</th>
                                    <th class="lz-col-status">{{ trans('localization.status') }}</th>
                                    <th class="lz-col-actions"><span class="sr-only">{{ trans('localization.actions') }}</span></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $row)
                                    <tr class="lz-row" data-hash="{{ $row->key_hash }}" data-state="{{ $row->state }}">
                                        @if($canReview)
                                            <td class="lz-col-check"><input type="checkbox" class="js-lz-select" value="{{ $row->key_hash }}" aria-label="{{ trans('localization.select') }} {{ $row->group }}.{{ $row->key }}"></td>
                                        @endif
                                        <td class="lz-col-key">
                                            <button type="button" class="lz-key js-lz-copy" data-copy="{{ $row->group }}.{{ $row->key }}" title="{{ trans('localization.copy_key') }}">
                                                <span class="lz-key__group">{{ $row->group }}.</span>{{ $row->key }}
                                            </button>
                                            @if(count($row->placeholders))
                                                <div class="lz-tokens">
                                                    @foreach(array_slice($row->placeholders, 0, 6) as $token)
                                                        <code class="lz-token">{{ $token }}</code>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        @unless($isSource)
                                            <td class="lz-col-text"><div class="lz-text js-lz-source" dir="{{ $sourceLanguage['dir'] }}">{{ $row->source_value }}</div></td>
                                        @endunless
                                        <td class="lz-col-text js-lz-target-cell">
                                            <div class="lz-text js-lz-display {{ $row->state === 'missing' ? 'is-missing' : '' }}" dir="{{ $language['dir'] }}">@if($row->state === 'missing')<span class="lz-missing">{{ trans('localization.state_missing') }}</span>@else{{ $row->target_value }}@endif</div>
                                            <textarea class="d-none js-lz-value">{{ $row->target_value }}</textarea>
                                        </td>
                                        <td class="lz-col-status">
                                            <span class="lz-badge lz-badge--{{ $row->state }} js-lz-state">{{ trans('localization.state_' . $row->state) }}</span>
                                            @if($row->origin)
                                                <span class="lz-origin js-lz-origin">{{ trans('localization.origin_' . $row->origin) }}</span>
                                            @endif
                                            @if($row->unpublished)
                                                <span class="lz-unpublished js-lz-unpublished" title="{{ trans('localization.unpublished_row') }}"><i class="fas fa-circle"></i> {{ trans('localization.unpublished_short') }}</span>
                                            @endif
                                        </td>
                                        <td class="lz-col-actions">
                                            <div class="lz-actions">
                                                @if($canEdit)
                                                    <button type="button" class="btn btn-sm btn-light js-lz-edit" title="{{ trans('localization.edit') }}" aria-label="{{ trans('localization.edit') }}"><i class="fas fa-pen"></i></button>
                                                @endif
                                                @if($canAi and $canEdit)
                                                    <button type="button" class="btn btn-sm btn-light js-lz-ai" title="{{ $row->state === 'missing' ? trans('localization.ai_suggest') : trans('localization.ai_regenerate') }}" aria-label="{{ trans('localization.ai_suggest') }}"><i class="fas fa-magic"></i></button>
                                                @endif
                                                @if($canReview and in_array($row->state, ['ai_translated', 'needs_review', 'translated']))
                                                    <button type="button" class="btn btn-sm btn-light js-lz-review" title="{{ trans('localization.mark_reviewed') }}" aria-label="{{ trans('localization.mark_reviewed') }}"><i class="fas fa-check"></i></button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-light js-lz-context" title="{{ trans('localization.context') }}" aria-label="{{ trans('localization.context') }}"><i class="fas fa-info-circle"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @if($rows->hasPages())
                    <div class="card-footer lz-pagination">{{ $rows->onEachSide(1)->links() }}</div>
                @endif
            </div>
        </div>
    </section>

    {{-- Context --}}
    <div class="modal fade" id="lzContextModal" tabindex="-1" aria-labelledby="lzContextTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lzContextTitle">{{ trans('localization.context') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('localization.close') }}"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body js-lz-context-body"></div>
            </div>
        </div>
    </div>

    {{-- Inline editor template (cloned per row by localization.js) --}}
    <template id="lzEditorTemplate">
        <div class="lz-editor">
            <label class="sr-only">{{ trans('localization.translation') }}</label>
            <textarea class="form-control lz-editor__input" rows="3" dir="{{ $language['dir'] }}"></textarea>
            <div class="lz-editor__warning d-none" role="alert"></div>
            <div class="lz-suggestion d-none">
                <div class="lz-suggestion__label"><i class="fas fa-magic"></i> {{ trans('localization.ai_suggestion') }}</div>
                <div class="lz-suggestion__text" dir="{{ $language['dir'] }}"></div>
                <div class="lz-suggestion__actions">
                    <button type="button" class="btn btn-sm btn-primary js-lz-accept">{{ trans('localization.accept') }}</button>
                    <button type="button" class="btn btn-sm btn-light js-lz-regenerate">{{ trans('localization.regenerate') }}</button>
                    <button type="button" class="btn btn-sm btn-link js-lz-reject">{{ trans('localization.reject') }}</button>
                </div>
            </div>
            <div class="lz-editor__actions">
                <button type="button" class="btn btn-sm btn-primary js-lz-save">{{ trans('localization.save') }}</button>
                @if($canReview)
                    <button type="button" class="btn btn-sm btn-success js-lz-save-review">{{ trans('localization.save_and_review') }}</button>
                @endif
                <button type="button" class="btn btn-sm btn-light js-lz-cancel">{{ trans('localization.cancel') }}</button>
                @unless($isSource)
                    <button type="button" class="btn btn-sm btn-link js-lz-copy-source">{{ trans('localization.copy_source') }}</button>
                @endunless
                @if($canAi)
                    <button type="button" class="btn btn-sm btn-link js-lz-ai-inline"><i class="fas fa-magic"></i> {{ trans('localization.ai_suggest') }}</button>
                @endif
                <span class="lz-editor__hint">{{ trans('localization.editor_hint') }}</span>
            </div>
        </div>
    </template>
@endsection

@push('scripts_bottom')
    @include('admin.localization.partials.script', ['lzConfig' => ['locale' => $language['locale']]])
@endpush
