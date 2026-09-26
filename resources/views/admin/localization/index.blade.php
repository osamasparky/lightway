@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

@section('content')
    <section class="section lz">
        <div class="section-header lz-header">
            <div>
                <h1>{{ trans('localization.title') }}</h1>
                <p class="lz-subtitle">{{ trans('localization.subtitle') }}</p>
            </div>
            <div class="lz-header__actions">
                @can('admin_translation_manager_ai')
                    <a href="{{ getAdminPanelUrl('/localization/ai') }}" class="btn btn-primary"><i class="fas fa-magic mr-1"></i>{{ trans('localization.ai_translate_language') }}</a>
                @endcan
                @can('admin_translation_manager_edit')
                    <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#lzImportModal"><i class="fas fa-file-import mr-1"></i>{{ trans('localization.import') }}</button>
                    <form action="{{ getAdminPanelUrl('/localization/tools/publish') }}" method="post" class="d-inline js-lz-confirm" data-confirm="{{ trans('localization.publish_all_confirm') }}">
                        @csrf
                        <input type="hidden" name="group" value="*">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-file-export mr-1"></i>{{ trans('localization.export') }}</button>
                    </form>
                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#lzLanguageModal"><i class="fas fa-plus mr-1"></i>{{ trans('localization.add_language') }}</button>
                @endcan
                @can('admin_translation_manager_settings')
                    <a href="{{ getAdminPanelUrl('/localization/settings') }}" class="btn btn-outline-secondary"><i class="fas fa-cog mr-1"></i>{{ trans('localization.settings') }}</a>
                @endcan
            </div>
        </div>

        <div class="section-body">
            @include('admin.localization.partials.alerts', ['queue' => $queue, 'aiReady' => $aiReady])

            @if(count($unpublished))
                <div class="alert alert-light lz-callout d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <strong><i class="fas fa-cloud-upload-alt mr-1"></i>{{ trans('localization.unpublished_title', ['count' => array_sum($unpublished)]) }}</strong>
                        <div class="text-muted small">{{ trans('localization.unpublished_hint') }}</div>
                        <div class="mt-2">
                            @foreach(array_slice($unpublished, 0, 12, true) as $group => $changes)
                                <span class="badge badge-light lz-chip">{{ $group }} · {{ $changes }}</span>
                            @endforeach
                        </div>
                    </div>
                    @can('admin_translation_manager_edit')
                        <form action="{{ getAdminPanelUrl('/localization/tools/publish') }}" method="post" class="mt-2 mt-md-0">
                            @csrf
                            <input type="hidden" name="group" value="*">
                            <button type="submit" class="btn btn-primary">{{ trans('localization.publish_now') }}</button>
                        </form>
                    @endcan
                </div>
            @endif

            {{-- Totals --}}
            <div class="row lz-tiles">
                @foreach([
                    ['languages', 'fa-globe', $totals['languages']],
                    ['keys', 'fa-key', $totals['keys']],
                    ['missing', 'fa-exclamation-circle', $totals['missing']],
                    ['ai', 'fa-robot', $totals['ai']],
                    ['reviewed', 'fa-check-double', $totals['reviewed']],
                    ['jobs', 'fa-tasks', $totals['jobs']],
                ] as [$name, $icon, $value])
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card lz-tile lz-tile--{{ $name }}">
                            <i class="fas {{ $icon }} lz-tile__icon" aria-hidden="true"></i>
                            <span class="lz-tile__value">{{ number_format($value) }}</span>
                            <span class="lz-tile__label">{{ trans('localization.total_' . $name) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Languages --}}
            <div class="card">
                <div class="card-header">
                    <h4>{{ trans('localization.languages') }}</h4>
                    <div class="card-header-action text-muted small">{{ trans('localization.source_is', ['language' => $languages[$source]['name'] ?? $source]) }}</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table lz-table mb-0">
                            <thead>
                            <tr>
                                <th>{{ trans('localization.language') }}</th>
                                <th class="lz-col-progress">{{ trans('localization.completion') }}</th>
                                <th class="text-right">{{ trans('localization.translated') }}</th>
                                <th class="text-right">{{ trans('localization.missing') }}</th>
                                <th class="text-right">{{ trans('localization.ai_translated') }}</th>
                                <th class="text-right">{{ trans('localization.reviewed') }}</th>
                                <th>{{ trans('localization.last_updated') }}</th>
                                <th>{{ trans('localization.status') }}</th>
                                <th class="text-right">{{ trans('localization.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($languages as $locale => $language)
                                @php($row = $stats[$locale] ?? null)
                                <tr>
                                    <td>
                                        <a href="{{ getAdminPanelUrl('/localization/languages/' . $locale) }}" class="lz-lang">
                                            <strong>{{ $language['name'] }}</strong>
                                            <span class="lz-lang__native" dir="{{ $language['dir'] }}">{{ $language['native'] }}</span>
                                        </a>
                                        <div class="lz-lang__meta">
                                            <code>{{ $locale }}</code>
                                            <span class="badge badge-light">{{ strtoupper($language['dir']) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @include('admin.localization.partials.progress', ['percent' => $row['percent'] ?? 0])
                                    </td>
                                    <td class="text-right lz-num">{{ number_format($row['translated'] ?? 0) }} <span class="text-muted">/ {{ number_format($row['total'] ?? 0) }}</span></td>
                                    <td class="text-right lz-num">
                                        @if(($row['missing'] ?? 0) > 0)
                                            <a href="{{ getAdminPanelUrl('/localization/languages/' . $locale . '?status=missing') }}" class="text-danger font-weight-bold">{{ number_format($row['missing']) }}</a>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-right lz-num">{{ number_format($row['ai'] ?? 0) }}</td>
                                    <td class="text-right lz-num">{{ number_format($row['reviewed'] ?? 0) }}</td>
                                    <td class="small text-muted">
                                        {{ !empty($row['updated_at']) ? \Carbon\Carbon::parse($row['updated_at'])->diffForHumans() : '—' }}
                                        @if(!empty($lastAi[$locale]))
                                            <div>{{ trans('localization.last_ai_run') }}: {{ \Carbon\Carbon::parse($lastAi[$locale])->diffForHumans() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($locale === $source)
                                            <span class="badge badge-info">{{ trans('localization.status_source') }}</span>
                                        @elseif(($row['missing'] ?? 0) === 0)
                                            <span class="badge badge-success">{{ trans('localization.status_complete') }}</span>
                                        @else
                                            <span class="badge badge-warning">{{ trans('localization.status_in_progress') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ getAdminPanelUrl('/localization/languages/' . $locale) }}" class="btn btn-sm btn-outline-primary">{{ trans('localization.manage') }}</a>
                                        @if($locale !== $source and ($row['missing'] ?? 0) > 0)
                                            @can('admin_translation_manager_ai')
                                                <a href="{{ getAdminPanelUrl('/localization/ai?locale=' . $locale) }}" class="btn btn-sm btn-primary"><i class="fas fa-magic"></i> {{ trans('localization.translate_with_ai') }}</a>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Jobs --}}
                <div class="col-12 col-xl-7">
                    <div class="card">
                        <div class="card-header">
                            <h4>{{ trans('localization.recent_jobs') }}</h4>
                            <div class="card-header-action"><a href="{{ getAdminPanelUrl('/localization/jobs') }}">{{ trans('localization.view_all') }}</a></div>
                        </div>
                        <div class="card-body p-0">
                            @if($recentJobs->isEmpty())
                                <div class="lz-empty">
                                    <i class="fas fa-robot"></i>
                                    <p>{{ trans('localization.no_jobs') }}</p>
                                </div>
                            @else
                                @include('admin.localization.partials.jobs_table', ['jobs' => $recentJobs, 'registry' => app(\App\Services\Localization\LanguageRegistry::class)])
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Activity --}}
                <div class="col-12 col-xl-5">
                    <div class="card">
                        <div class="card-header"><h4>{{ trans('localization.recent_activity') }}</h4></div>
                        <div class="card-body">
                            @forelse($activity as $entry)
                                <div class="lz-activity">
                                    <span class="lz-activity__icon lz-activity__icon--{{ $entry->source }}"><i class="fas {{ $entry->source === 'ai' ? 'fa-robot' : 'fa-user-edit' }}"></i></span>
                                    <div class="lz-activity__body">
                                        <code>{{ $entry->group }}.{{ \Illuminate\Support\Str::limit($entry->key, 40) }}</code>
                                        <span class="badge badge-light">{{ $entry->locale }}</span>
                                        <div class="text-muted small text-truncate" dir="auto">{{ \Illuminate\Support\Str::limit($entry->value, 80) }}</div>
                                    </div>
                                    <span class="small text-muted text-nowrap">{{ optional($entry->updated_at)->diffForHumans() }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ trans('localization.no_activity') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @can('admin_translation_manager_edit')
                @include('admin.localization.partials.tools', ['groups' => $groups, 'fileLocales' => $fileLocales, 'languages' => $languages])
            @endcan
        </div>
    </section>

    @can('admin_translation_manager_edit')
        {{-- Import --}}
        <div class="modal fade" id="lzImportModal" tabindex="-1" aria-labelledby="lzImportTitle" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" action="{{ getAdminPanelUrl('/localization/tools/import') }}" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="lzImportTitle">{{ trans('localization.import_title') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('localization.close') }}"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">{{ trans('localization.import_hint') }}</p>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="lzImportAppend" name="replace" value="0" class="custom-control-input" checked>
                            <label class="custom-control-label" for="lzImportAppend"><strong>{{ trans('localization.import_append') }}</strong><br><span class="small text-muted">{{ trans('localization.import_append_hint') }}</span></label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="lzImportReplace" name="replace" value="1" class="custom-control-input">
                            <label class="custom-control-label" for="lzImportReplace"><strong>{{ trans('localization.import_replace') }}</strong><br><span class="small text-muted">{{ trans('localization.import_replace_hint') }}</span></label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">{{ trans('localization.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ trans('localization.import') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Add language --}}
        <div class="modal fade" id="lzLanguageModal" tabindex="-1" aria-labelledby="lzLanguageTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="lzLanguageTitle">{{ trans('localization.add_language') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('localization.close') }}"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <ol class="lz-steps-list">
                            <li>{!! trans('localization.add_language_step1', ['link' => '<a href="' . getAdminPanelUrl('/settings/general') . '">' . e(trans('localization.general_settings')) . '</a>']) !!}</li>
                            <li>{{ trans('localization.add_language_step2') }}</li>
                            <li>{{ trans('localization.add_language_step3') }}</li>
                        </ol>
                        <form action="{{ getAdminPanelUrl('/localization/tools/locales/add') }}" method="post" class="form-inline mt-3">
                            @csrf
                            <label class="sr-only" for="lzNewLocale">{{ trans('localization.locale_code') }}</label>
                            <input type="text" id="lzNewLocale" name="locale" class="form-control mr-2" placeholder="fr" pattern="[a-z]{2,3}([_-][A-Za-z]{2,4})?" required>
                            <button type="submit" class="btn btn-primary">{{ trans('localization.create_language_folder') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts_bottom')
    @include('admin.localization.partials.script')
@endpush
