@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

@section('content')
    <section class="section lz">
        <div class="section-header lz-header">
            <div>
                <h1>{{ trans('localization.ai_title') }}</h1>
                <p class="lz-subtitle">{{ trans('localization.ai_subtitle') }}</p>
            </div>
            <div class="lz-header__actions">
                <a href="{{ getAdminPanelUrl('/localization') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left lz-flip mr-1"></i>{{ trans('localization.back_to_overview') }}</a>
            </div>
        </div>

        <div class="section-body">
            @include('admin.localization.partials.alerts', ['queue' => $queue, 'aiReady' => $aiReady])

            @if(empty($targets))
                <div class="card"><div class="card-body lz-empty">
                    <i class="fas fa-language"></i>
                    <p>{{ trans('localization.no_target_languages') }}</p>
                    <a href="{{ getAdminPanelUrl('/settings/general') }}" class="btn btn-primary">{{ trans('localization.general_settings') }}</a>
                </div></div>
            @else
                <form action="{{ getAdminPanelUrl('/localization/ai') }}" method="post" class="lz-wizard js-lz-wizard" data-preview="{{ getAdminPanelUrl('/localization/ai/preview') }}">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-xl-8">
                            {{-- Step 1 --}}
                            <div class="card lz-step">
                                <div class="card-header"><h4><span class="lz-step__num">1</span>{{ trans('localization.step_languages') }}</h4></div>
                                <div class="card-body">
                                    <div class="form-row align-items-end">
                                        <div class="col-12 col-md-5 form-group">
                                            <label>{{ trans('localization.source_language') }}</label>
                                            <div class="form-control-plaintext lz-source-lang"><strong>{{ $sourceLanguage['name'] }}</strong> <code>{{ $sourceLanguage['locale'] }}</code></div>
                                        </div>
                                        <div class="col-12 col-md-2 text-center form-group d-none d-md-block"><i class="fas fa-long-arrow-alt-right fa-2x text-muted lz-flip"></i></div>
                                        <div class="col-12 col-md-5 form-group">
                                            <label for="lzTarget">{{ trans('localization.target_language') }}</label>
                                            <select id="lzTarget" name="target" class="form-control js-lz-preview-input" required>
                                                @foreach($targets as $locale => $language)
                                                    <option value="{{ $locale }}" @selected(old('target', $selected) === $locale) @disabled(isset($activeJobs[$locale]))>
                                                        {{ $language['name'] }} — {{ $language['native'] }}{{ isset($activeJobs[$locale]) ? ' (' . trans('localization.job_in_progress') . ')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <dl class="lz-summary__stats lz-summary__stats--compact">
                                        @foreach(['total', 'translated', 'missing', 'ai', 'reviewed'] as $name)
                                            <div><dt>{{ trans('localization.stat_' . $name) }}</dt><dd class="js-lz-preview-{{ $name }}">—</dd></div>
                                        @endforeach
                                    </dl>
                                    <p class="small text-muted mb-0">{{ trans('localization.targets_from_settings') }}</p>
                                </div>
                            </div>

                            {{-- Step 2 --}}
                            <div class="card lz-step">
                                <div class="card-header"><h4><span class="lz-step__num">2</span>{{ trans('localization.step_scope') }}</h4></div>
                                <div class="card-body">
                                    <div class="lz-scopes" role="radiogroup" aria-label="{{ trans('localization.step_scope') }}">
                                        @foreach(['missing' => 'fa-plus-circle', 'all' => 'fa-globe', 'retranslate' => 'fa-redo'] as $scopeName => $icon)
                                            <label class="lz-scope {{ $scopeName === 'retranslate' ? 'lz-scope--danger' : '' }}">
                                                <input type="radio" name="scope" value="{{ $scopeName }}" class="js-lz-preview-input" @checked(old('scope', $scope) === $scopeName)>
                                                <span class="lz-scope__box">
                                                    <i class="fas {{ $icon }}"></i>
                                                    <strong>{{ trans('localization.scope_' . $scopeName) }}</strong>
                                                    <span>{{ trans('localization.scope_' . $scopeName . '_hint') }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="form-group mt-4">
                                        <label for="lzGroups">{{ trans('localization.limit_to_files') }}</label>
                                        <select id="lzGroups" name="groups[]" class="form-control js-lz-select2 js-lz-preview-input" multiple data-placeholder="{{ trans('localization.all_files') }}">
                                            @foreach($groups as $group)
                                                <option value="{{ $group }}" @selected(in_array($group, old('groups', [])))>{{ $group }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">{{ trans('localization.limit_to_files_hint') }}</small>
                                    </div>

                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="lzPublish" name="publish" value="1" @checked(old('publish', true))>
                                        <label class="custom-control-label" for="lzPublish">{{ trans('localization.publish_when_done') }}</label>
                                        <small class="form-text text-muted">{{ trans('localization.publish_when_done_hint') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Step 3 --}}
                        <div class="col-12 col-xl-4">
                            <div class="card lz-step lz-estimate">
                                <div class="card-header"><h4><span class="lz-step__num">3</span>{{ trans('localization.step_confirm') }}</h4></div>
                                <div class="card-body">
                                    <div class="lz-estimate__big"><span class="js-lz-preview-selected">—</span> <small>{{ trans('localization.strings') }}</small></div>
                                    <dl class="lz-estimate__list">
                                        <div><dt>{{ trans('localization.batches') }}</dt><dd class="js-lz-preview-batches">—</dd></div>
                                        <div><dt>{{ trans('localization.est_input_tokens') }}</dt><dd class="js-lz-preview-input_tokens">—</dd></div>
                                        <div><dt>{{ trans('localization.est_output_tokens') }}</dt><dd class="js-lz-preview-output_tokens">—</dd></div>
                                        <div><dt>{{ trans('localization.est_cost') }}</dt><dd class="js-lz-preview-cost">—</dd></div>
                                        <div><dt>{{ trans('localization.model') }}</dt><dd><code>{{ $settings['model'] ?: '—' }}</code></dd></div>
                                    </dl>
                                    <div class="alert alert-warning small d-none js-lz-over-limit" role="alert"></div>

                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input" id="lzConfirmCost" name="confirm_cost" value="1" required>
                                        <label class="custom-control-label js-lz-confirm-text" for="lzConfirmCost" data-template="{{ trans('localization.confirm_cost', ['count' => '__COUNT__', 'provider' => 'OpenAI']) }}">{{ trans('localization.confirm_cost', ['count' => '…', 'provider' => 'OpenAI']) }}</label>
                                    </div>
                                    <div class="custom-control custom-checkbox mb-3 d-none js-lz-overwrite">
                                        <input type="checkbox" class="custom-control-input" id="lzConfirmOverwrite" name="confirm_overwrite" value="1">
                                        <label class="custom-control-label text-danger" for="lzConfirmOverwrite">{{ trans('localization.confirm_overwrite') }}</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg btn-block js-lz-start" @disabled(!$aiReady)>
                                        <i class="fas fa-magic mr-1"></i>{{ trans('localization.start_translation') }}
                                    </button>
                                    <p class="small text-muted mt-2 mb-0">{{ trans('localization.runs_in_background') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection

@push('scripts_bottom')
    @include('admin.localization.partials.script')
@endpush
