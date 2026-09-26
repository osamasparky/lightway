@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

@php
    $tabs = [
        'provider' => ['fa-plug', trans('localization.tab_provider')],
        'models' => ['fa-microchip', trans('localization.tab_models')],
        'profiles' => ['fa-language', trans('localization.tab_profiles')],
        'rules' => ['fa-feather-alt', trans('localization.tab_rules')],
        'glossary' => ['fa-book', trans('localization.tab_glossary')],
        'quality' => ['fa-shield-alt', trans('localization.tab_quality')],
        'limits' => ['fa-sliders-h', trans('localization.tab_limits')],
        'costs' => ['fa-coins', trans('localization.tab_costs')],
    ];
    $saveUrl = getAdminPanelUrl('/localization/settings');
    $modes = \App\Models\Localization\TranslationJob::MODES;
    $typeIcons = ['preferred' => 'fa-check', 'technical' => 'fa-cog', 'context' => 'fa-map-signs', 'do_not_translate' => 'fa-lock', 'brand' => 'fa-copyright', 'forbidden' => 'fa-ban'];
@endphp

@section('content')
    <section class="section lz">
        <div class="section-header lz-header">
            <div>
                <h1>{{ trans('localization.settings_title') }}</h1>
                <p class="lz-subtitle">{{ trans('localization.settings_subtitle') }}</p>
            </div>
            <div class="lz-header__actions">
                <a href="{{ getAdminPanelUrl('/localization') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left lz-flip mr-1"></i>{{ trans('localization.back_to_overview') }}</a>
            </div>
        </div>

        <div class="section-body">
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>{{ trans('localization.settings_not_saved') }}</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="lz-settings">
                <nav class="lz-settings__nav card" aria-label="{{ trans('localization.settings_title') }}">
                    <div class="nav flex-column nav-pills" role="tablist">
                        @foreach($tabs as $key => [$icon, $label])
                            <a class="nav-link js-lz-settings-tab {{ $tab === $key ? 'active' : '' }}" id="lz-tab-{{ $key }}" href="?tab={{ $key }}" role="tab"
                               aria-controls="lz-pane-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" data-tab="{{ $key }}">
                                <i class="fas {{ $icon }} fa-fw"></i> {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </nav>

                <div class="tab-content lz-settings__content">
                    {{-- AI Provider --}}
                    <div class="tab-pane fade {{ $tab === 'provider' ? 'show active' : '' }}" id="lz-pane-provider" role="tabpanel" aria-labelledby="lz-tab-provider">
                        <form action="{{ $saveUrl }}" method="post" autocomplete="off" class="card">
                            @csrf
                            <input type="hidden" name="tab" value="provider">
                            <div class="card-header"><h4>{{ trans('localization.tab_provider') }}</h4></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="lzProvider">{{ trans('localization.provider') }}</label>
                                    <select id="lzProvider" name="provider" class="form-control">
                                        @foreach($providers as $provider)
                                            <option value="{{ $provider }}" @selected($settings['provider'] === $provider)>{{ $provider === 'openai' ? 'OpenAI' : ucfirst($provider) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="lzApiKey">{{ trans('localization.api_key') }}</label>
                                    <input type="password" id="lzApiKey" name="api_key" class="form-control @error('api_key') is-invalid @enderror" autocomplete="new-password" spellcheck="false"
                                           placeholder="{{ $maskedKey ? trans('localization.api_key_saved', ['hint' => $maskedKey]) : 'sk-…' }}">
                                    <small class="form-text text-muted">{{ trans('localization.api_key_hint') }}</small>
                                    @if($maskedKey)
                                        <div class="lz-keybox mt-2"><i class="fas fa-lock"></i> {{ trans('localization.api_key_saved', ['hint' => $maskedKey]) }}</div>
                                        <div class="custom-control custom-checkbox mt-2">
                                            <input type="checkbox" class="custom-control-input" id="lzRemoveKey" name="remove_api_key" value="1">
                                            <label class="custom-control-label" for="lzRemoveKey">{{ trans('localization.remove_api_key') }}</label>
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center flex-wrap lz-gap">
                                    <button type="button" class="btn btn-outline-primary js-lz-test" data-url="{{ getAdminPanelUrl('/localization/settings/test') }}" data-has-saved="{{ $maskedKey ? 1 : 0 }}" @disabled(!$maskedKey)>
                                        <i class="fas fa-plug mr-1"></i>{{ trans('localization.test_connection') }}
                                    </button>
                                    <span class="lz-test-result js-lz-test-result" role="status"></span>
                                </div>
                                <small class="form-text text-muted">{{ trans('localization.test_hint') }}</small>
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button></div>
                        </form>
                    </div>

                    {{-- Models --}}
                    <div class="tab-pane fade {{ $tab === 'models' ? 'show active' : '' }}" id="lz-pane-models" role="tabpanel" aria-labelledby="lz-tab-models">
                        <form action="{{ $saveUrl }}" method="post" class="card">
                            @csrf
                            <input type="hidden" name="tab" value="models">
                            <div class="card-header"><h4>{{ trans('localization.tab_models') }}</h4></div>
                            <div class="card-body">
                                <p class="text-muted">{{ trans('localization.models_intro') }}</p>
                                <div class="alert alert-light lz-callout"><i class="fas fa-tachometer-alt mr-1"></i>{{ trans('localization.models_avoid_pro') }}</div>
                                @foreach(['model', 'qa_model', 'premium_model'] as $modelField)
                                    @if($settings[$modelField] and \App\Services\Localization\AI\OpenAITranslationProvider::isSlowModel($settings[$modelField]))
                                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>{{ trans('localization.slow_model_selected', ['model' => $settings[$modelField]]) }}</div>
                                    @endif
                                @endforeach
                                <div class="form-row">
                                    <div class="col-12 col-lg-4">
                                        @include('admin.localization.partials.model_select', ['name' => 'model', 'value' => $settings['model'], 'models' => $models, 'label' => trans('localization.translation_model'), 'hint' => trans('localization.translation_model_hint')])
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        @include('admin.localization.partials.model_select', ['name' => 'qa_model', 'value' => $settings['qa_model'], 'models' => $models, 'label' => trans('localization.qa_model'), 'hint' => trans('localization.qa_model_hint'), 'emptyLabel' => trans('localization.same_as_translation_model')])
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        @include('admin.localization.partials.model_select', ['name' => 'premium_model', 'value' => $settings['premium_model'], 'models' => $models, 'label' => trans('localization.premium_model'), 'hint' => trans('localization.premium_model_hint'), 'emptyLabel' => trans('localization.same_as_translation_model')])
                                    </div>
                                </div>
                                @unless(count($models))
                                    <div class="alert alert-light lz-callout mb-0"><i class="fas fa-info-circle mr-1"></i>{{ trans('localization.models_test_first') }}</div>
                                @endunless
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button></div>
                        </form>
                    </div>

                    {{-- Translation profiles --}}
                    <div class="tab-pane fade {{ $tab === 'profiles' ? 'show active' : '' }}" id="lz-pane-profiles" role="tabpanel" aria-labelledby="lz-tab-profiles">
                        <div class="card">
                            <div class="card-header"><h4>{{ trans('localization.tab_profiles') }}</h4></div>
                            <div class="card-body">
                                <p class="text-muted mb-0">{{ trans('localization.profiles_intro') }}</p>
                            </div>
                        </div>
                        @forelse($targets as $locale => $language)
                            @php $profile = $profiles[$locale] ?? null; @endphp
                            @php $effective = $resolved[$locale]; @endphp
                            <div class="card lz-profile" id="profile-{{ $locale }}">
                                <div class="card-header">
                                    <h4>
                                        <span class="lz-tag mr-1">{{ $locale }}</span>
                                        {{ $profile->name ?? $language['name'] }}
                                    </h4>
                                    <div class="card-header-action d-flex align-items-center lz-gap">
                                        @if($profile)
                                            <span class="lz-status {{ $profile->active ? 'lz-status--complete' : 'lz-status--progress' }}">{{ $profile->active ? trans('localization.profile_active') : trans('localization.profile_inactive') }}</span>
                                        @else
                                            <span class="lz-status lz-status--source">{{ trans('localization.profile_default') }}</span>
                                        @endif
                                        <span class="lz-tag">{{ trans('localization.mode_' . $effective['quality_mode']) }}</span>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#profile-form-{{ $locale }}" aria-expanded="false" aria-controls="profile-form-{{ $locale }}">
                                            {{ $profile ? trans('localization.edit') : trans('localization.create_profile') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="collapse" id="profile-form-{{ $locale }}">
                                    <form action="{{ getAdminPanelUrl('/localization/settings/profiles') }}" method="post" class="card-body border-top">
                                        @csrf
                                        <input type="hidden" name="target_locale" value="{{ $locale }}">
                                        <div class="form-row">
                                            <div class="col-12 col-md-6 form-group">
                                                <label>{{ trans('localization.profile_name') }}</label>
                                                <input type="text" name="name" class="form-control" maxlength="150" required value="{{ $profile->name ?? ($language['name'] . ' — ') }}">
                                            </div>
                                            <div class="col-6 col-md-3 form-group">
                                                <label>{{ trans('localization.locale_code') }}</label>
                                                <input type="text" name="locale_code" class="form-control" maxlength="20" dir="ltr" placeholder="{{ $locale }}-XX" value="{{ $profile->locale_code ?? '' }}">
                                            </div>
                                            <div class="col-6 col-md-3 form-group">
                                                <label>{{ trans('localization.region') }}</label>
                                                <input type="text" name="region" class="form-control" maxlength="100" value="{{ $profile->region ?? '' }}">
                                            </div>
                                            <div class="col-12 col-md-4 form-group">
                                                <label>{{ trans('localization.tone') }}</label>
                                                <input type="text" name="tone" class="form-control" maxlength="100" placeholder="{{ $settings['tone'] }}" value="{{ $profile->tone ?? '' }}">
                                            </div>
                                            <div class="col-12 col-md-8 form-group">
                                                <label>{{ trans('localization.audience') }}</label>
                                                <input type="text" name="audience" class="form-control" maxlength="300" placeholder="{{ $settings['audience'] }}" value="{{ $profile->audience ?? '' }}">
                                            </div>
                                            <div class="col-12 form-group">
                                                <label>{{ trans('localization.product_context') }}</label>
                                                <textarea name="product_context" rows="2" class="form-control" maxlength="1000" placeholder="{{ $settings['product_context'] }}">{{ $profile->product_context ?? '' }}</textarea>
                                            </div>
                                            @if($effective['language_rules'])
                                                <div class="col-12 form-group">
                                                    <label>{{ trans('localization.builtin_language_rules') }}</label>
                                                    <div class="lz-readonly" dir="ltr">{{ $effective['language_rules'] }}</div>
                                                </div>
                                            @endif
                                            <div class="col-12 col-lg-6 form-group">
                                                <label>{{ trans('localization.profile_rules') }}</label>
                                                <textarea name="rules" rows="4" class="form-control" maxlength="3000" placeholder="{{ trans('localization.profile_rules_placeholder') }}">{{ $profile->rules ?? '' }}</textarea>
                                            </div>
                                            <div class="col-12 col-lg-6 form-group">
                                                <label>{{ trans('localization.cultural_notes') }}</label>
                                                <textarea name="cultural_notes" rows="4" class="form-control" maxlength="2000" placeholder="{{ trans('localization.cultural_notes_placeholder') }}">{{ $profile->cultural_notes ?? '' }}</textarea>
                                            </div>
                                            <div class="col-12 col-md-4 form-group">
                                                <label>{{ trans('localization.quality_mode') }}</label>
                                                <select name="quality_mode" class="form-control">
                                                    <option value="">{{ trans('localization.use_default_mode', ['mode' => trans('localization.mode_' . $settings['default_quality_mode'])]) }}</option>
                                                    @foreach($modes as $mode)
                                                        <option value="{{ $mode }}" @selected(($profile->quality_mode ?? null) === $mode)>{{ trans('localization.mode_' . $mode) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-4 form-group">
                                                <label>{{ trans('localization.translation_model') }}</label>
                                                <input type="text" name="translation_model" class="form-control" list="lzModelList" dir="ltr" maxlength="100" placeholder="{{ trans('localization.inherit') }}" value="{{ $profile->translation_model ?? '' }}">
                                            </div>
                                            <div class="col-6 col-md-4 form-group">
                                                <label>{{ trans('localization.qa_model') }}</label>
                                                <input type="text" name="qa_model" class="form-control" list="lzModelList" dir="ltr" maxlength="100" placeholder="{{ trans('localization.inherit') }}" value="{{ $profile->qa_model ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center justify-content-between lz-gap">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="lzProfileActive{{ $locale }}" name="active" value="1" @checked($profile ? $profile->active : true)>
                                                <label class="custom-control-label" for="lzProfileActive{{ $locale }}">{{ trans('localization.profile_active') }}</label>
                                            </div>
                                            <div class="d-flex lz-gap">
                                                <button type="submit" class="btn btn-primary">{{ trans('localization.save_profile') }}</button>
                                            </div>
                                        </div>
                                    </form>
                                    @if($profile)
                                        <form action="{{ getAdminPanelUrl('/localization/settings/profiles/' . $locale . '/delete') }}" method="post" class="card-footer text-right js-lz-confirm" data-lz-confirm="{{ trans('localization.delete_profile_confirm') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-link text-danger">{{ trans('localization.delete_profile') }}</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="card-body lz-profile__summary">
                                    <dl class="lz-details mb-0">
                                        <dt>{{ trans('localization.locale_code') }}</dt><dd dir="ltr">{{ $effective['locale_code'] }}{{ $effective['region'] ? ' · ' . $effective['region'] : '' }}</dd>
                                        <dt>{{ trans('localization.tone') }} / {{ trans('localization.audience') }}</dt><dd>{{ $effective['tone'] }} — {{ $effective['audience'] }}</dd>
                                        <dt>{{ trans('localization.tab_models') }}</dt><dd dir="ltr">{{ $effective['translation_model'] ?: '—' }} · QA: {{ $effective['qa_model'] ?: '—' }}</dd>
                                    </dl>
                                </div>
                            </div>
                        @empty
                            <div class="card"><div class="card-body lz-empty lz-empty--sm"><i class="fas fa-language"></i><p>{{ trans('localization.no_target_languages') }}</p></div></div>
                        @endforelse
                        <datalist id="lzModelList">
                            @foreach($models as $model)
                                <option value="{{ $model }}">
                            @endforeach
                        </datalist>
                    </div>

                    {{-- Translation rules --}}
                    <div class="tab-pane fade {{ $tab === 'rules' ? 'show active' : '' }}" id="lz-pane-rules" role="tabpanel" aria-labelledby="lz-tab-rules">
                        <form action="{{ $saveUrl }}" method="post" class="card">
                            @csrf
                            <input type="hidden" name="tab" value="rules">
                            <div class="card-header"><h4>{{ trans('localization.tab_rules') }}</h4></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>{{ trans('localization.base_instructions') }}</label>
                                    <pre class="lz-readonly lz-readonly--pre" dir="ltr">{{ $baseInstructions }}</pre>
                                    <small class="form-text text-muted">{{ trans('localization.base_instructions_hint') }}</small>
                                </div>
                                <div class="form-group">
                                    <label for="lzRules">{{ trans('localization.extra_rules') }}</label>
                                    <textarea id="lzRules" name="translation_rules" rows="4" class="form-control" maxlength="3000" placeholder="{{ trans('localization.extra_rules_placeholder') }}">{{ old('translation_rules', $settings['translation_rules']) }}</textarea>
                                </div>
                                <div class="form-row">
                                    <div class="col-12 col-md-4 form-group">
                                        <label for="lzTone">{{ trans('localization.tone') }}</label>
                                        <input type="text" id="lzTone" name="tone" value="{{ old('tone', $settings['tone']) }}" class="form-control" maxlength="100" required>
                                    </div>
                                    <div class="col-12 col-md-8 form-group">
                                        <label for="lzAudience">{{ trans('localization.audience') }}</label>
                                        <input type="text" id="lzAudience" name="audience" value="{{ old('audience', $settings['audience']) }}" class="form-control" maxlength="300" required>
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="lzContext">{{ trans('localization.product_context') }}</label>
                                    <textarea id="lzContext" name="product_context" rows="3" class="form-control" maxlength="1000" required>{{ old('product_context', $settings['product_context']) }}</textarea>
                                    <small class="form-text text-muted">{{ trans('localization.product_context_hint') }}</small>
                                </div>
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button></div>
                        </form>
                    </div>

                    {{-- Glossary --}}
                    <div class="tab-pane fade {{ $tab === 'glossary' ? 'show active' : '' }}" id="lz-pane-glossary" role="tabpanel" aria-labelledby="lz-tab-glossary">
                        <div class="card">
                            <div class="card-header"><h4>{{ trans('localization.tab_glossary') }}</h4></div>
                            <div class="card-body">
                                <p class="text-muted">{{ trans('localization.glossary_intro') }}</p>
                                <form action="{{ getAdminPanelUrl('/localization/settings/glossary') }}" method="post" class="lz-glossary-form">
                                    @csrf
                                    <div class="form-row">
                                        <div class="col-12 col-md-3 form-group">
                                            <label for="lzTermType">{{ trans('localization.term_type') }}</label>
                                            <select id="lzTermType" name="type" class="form-control js-lz-term-type">
                                                @foreach(\App\Models\Localization\GlossaryTerm::TYPES as $type)
                                                    <option value="{{ $type }}" @selected(old('type', 'preferred') === $type)>{{ trans('localization.term_type_' . $type) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3 form-group">
                                            <label for="lzTerm">{{ trans('localization.term') }}</label>
                                            <input type="text" id="lzTerm" name="term" class="form-control" maxlength="255" required placeholder="Course" value="{{ old('term') }}">
                                        </div>
                                        <div class="col-12 col-md-3 form-group js-lz-term-translation">
                                            <label for="lzTermTranslation">{{ trans('localization.term_translation') }}</label>
                                            <input type="text" id="lzTermTranslation" name="translation" class="form-control" maxlength="255" dir="auto" placeholder="دورة" value="{{ old('translation') }}">
                                        </div>
                                        <div class="col-12 col-md-3 form-group">
                                            <label for="lzTermLocale">{{ trans('localization.language') }}</label>
                                            <select id="lzTermLocale" name="locale" class="form-control">
                                                <option value="">{{ trans('localization.all_languages') }}</option>
                                                @foreach($languages as $locale => $language)
                                                    <option value="{{ $locale }}" @selected(old('locale') === $locale)>{{ $language['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3 form-group js-lz-term-context">
                                            <label for="lzTermContext">{{ trans('localization.term_context') }}</label>
                                            <input type="text" id="lzTermContext" name="context" class="form-control" maxlength="255" placeholder="checkout, cart" value="{{ old('context') }}">
                                        </div>
                                        <div class="col-12 col-md-5 form-group">
                                            <label for="lzTermRule">{{ trans('localization.term_rule') }}</label>
                                            <input type="text" id="lzTermRule" name="rule" class="form-control" maxlength="1000" placeholder="{{ trans('localization.term_rule_placeholder') }}" value="{{ old('rule') }}">
                                        </div>
                                        <div class="col-12 col-md-3 form-group">
                                            <label for="lzTermNote">{{ trans('localization.note') }}</label>
                                            <input type="text" id="lzTermNote" name="note" class="form-control" maxlength="255" value="{{ old('note') }}">
                                        </div>
                                        <div class="col-12 col-md-1 form-group d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary btn-block">{{ trans('localization.add') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            @if($glossary->isEmpty())
                                <div class="lz-empty lz-empty--sm"><i class="fas fa-book-open"></i><p>{{ trans('localization.glossary_empty') }}</p></div>
                            @else
                                <div class="table-responsive">
                                    <table class="table lz-table mb-0">
                                        <thead><tr>
                                            <th>{{ trans('localization.term') }}</th><th>{{ trans('localization.term_type') }}</th><th>{{ trans('localization.term_translation') }}</th>
                                            <th>{{ trans('localization.language') }}</th><th>{{ trans('localization.term_rule') }}</th><th class="text-right">{{ trans('localization.actions') }}</th>
                                        </tr></thead>
                                        <tbody>
                                        @foreach($glossary as $term)
                                            <tr class="{{ $term->active ? '' : 'lz-row--inactive' }}">
                                                <td><strong>{{ $term->term }}</strong></td>
                                                <td class="text-nowrap"><span class="lz-term-type lz-term-type--{{ $term->type }}"><i class="fas {{ $typeIcons[$term->type] ?? 'fa-check' }}"></i> {{ trans('localization.term_type_' . $term->type) }}</span></td>
                                                <td dir="auto">
                                                    @if($term->keepsOriginal())
                                                        <span class="lz-tag">{{ trans('localization.keep_as_is') }}</span>
                                                    @else
                                                        {{ $term->translation }}
                                                    @endif
                                                </td>
                                                <td>{{ $term->locale ? ($languages[$term->locale]['name'] ?? $term->locale) : trans('localization.all_languages') }}</td>
                                                <td class="small text-muted">
                                                    {{ $term->rule }}
                                                    @if($term->context)<div><i class="fas fa-map-signs"></i> {{ $term->context }}</div>@endif
                                                    @if($term->note)<div>{{ $term->note }}</div>@endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <form action="{{ getAdminPanelUrl('/localization/settings/glossary/' . $term->id . '/toggle') }}" method="post" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-link">{{ $term->active ? trans('localization.deactivate') : trans('localization.activate') }}</button>
                                                    </form>
                                                    <form action="{{ getAdminPanelUrl('/localization/settings/glossary/' . $term->id . '/delete') }}" method="post" class="d-inline js-lz-confirm" data-lz-confirm="{{ trans('localization.delete_term_confirm') }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-link text-danger">{{ trans('localization.delete') }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Quality & validation --}}
                    <div class="tab-pane fade {{ $tab === 'quality' ? 'show active' : '' }}" id="lz-pane-quality" role="tabpanel" aria-labelledby="lz-tab-quality">
                        <form action="{{ $saveUrl }}" method="post" class="card">
                            @csrf
                            <input type="hidden" name="tab" value="quality">
                            <div class="card-header"><h4>{{ trans('localization.tab_quality') }}</h4></div>
                            <div class="card-body">
                                <label class="d-block">{{ trans('localization.default_quality_mode') }}</label>
                                <div class="lz-scopes mb-4">
                                    @foreach($modes as $mode)
                                        <label class="lz-scope">
                                            <input type="radio" name="default_quality_mode" value="{{ $mode }}" @checked($settings['default_quality_mode'] === $mode)>
                                            <span class="lz-scope__box">
                                                <i class="fas {{ ['economy' => 'fa-bolt', 'professional' => 'fa-user-check', 'premium' => 'fa-gem'][$mode] }}"></i>
                                                <strong>{{ trans('localization.mode_' . $mode) }}</strong>
                                                <span>{{ trans('localization.mode_' . $mode . '_hint') }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <input type="hidden" name="use_translation_memory" value="0">
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="lzMemory" name="use_translation_memory" value="1" @checked((int)$settings['use_translation_memory'] === 1)>
                                    <label class="custom-control-label" for="lzMemory"><strong>{{ trans('localization.use_memory') }}</strong><br><span class="small text-muted">{{ trans('localization.use_memory_hint') }}</span></label>
                                </div>
                                <input type="hidden" name="auto_approve_passed" value="0">
                                <div class="custom-control custom-switch mb-4">
                                    <input type="checkbox" class="custom-control-input" id="lzAutoApprove" name="auto_approve_passed" value="1" @checked((int)$settings['auto_approve_passed'] === 1)>
                                    <label class="custom-control-label" for="lzAutoApprove"><strong>{{ trans('localization.auto_approve') }}</strong><br><span class="small text-muted">{{ trans('localization.auto_approve_hint') }}</span></label>
                                </div>

                                <h6 class="lz-subhead">{{ trans('localization.expansion_title') }}</h6>
                                <p class="small text-muted">{{ trans('localization.expansion_hint') }}</p>
                                <div class="form-row">
                                    @foreach(['button', 'navigation', 'label', 'title'] as $kind)
                                        <div class="col-6 col-md-3 form-group">
                                            <label for="lzExp{{ $kind }}">{{ trans('localization.kind_' . $kind) }}</label>
                                            <div class="input-group">
                                                <input type="number" min="0" max="1000" id="lzExp{{ $kind }}" name="expansion_{{ $kind }}" value="{{ $settings['expansion_' . $kind] }}" class="form-control">
                                                <div class="input-group-append"><span class="input-group-text">%</span></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <h6 class="lz-subhead">{{ trans('localization.checks_title') }}</h6>
                                <ul class="lz-checklist">
                                    @foreach(['placeholders', 'html', 'urls', 'numbers', 'glossary', 'untranslated', 'length', 'language', 'suspicious'] as $check)
                                        <li><i class="fas fa-check-circle"></i> {{ trans('localization.check_' . $check) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button></div>
                        </form>
                    </div>

                    {{-- Batch & limits --}}
                    <div class="tab-pane fade {{ $tab === 'limits' ? 'show active' : '' }}" id="lz-pane-limits" role="tabpanel" aria-labelledby="lz-tab-limits">
                        <form action="{{ $saveUrl }}" method="post" class="card">
                            @csrf
                            <input type="hidden" name="tab" value="limits">
                            <div class="card-header"><h4>{{ trans('localization.tab_limits') }}</h4></div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="col-12 col-md-4 form-group">
                                        <label for="lzBatch">{{ trans('localization.batch_size') }}</label>
                                        <input type="number" id="lzBatch" name="batch_size" min="5" max="100" value="{{ old('batch_size', $settings['batch_size']) }}" class="form-control" required>
                                        <small class="form-text text-muted">{{ trans('localization.batch_size_hint') }} {{ trans('localization.batch_size_applies') }}</small>
                                    </div>
                                    <div class="col-12 col-md-4 form-group">
                                        <label for="lzTimeout">{{ trans('localization.timeout') }}</label>
                                        <input type="number" id="lzTimeout" name="timeout" min="15" max="300" value="{{ old('timeout', $settings['timeout']) }}" class="form-control" required>
                                    </div>
                                    <div class="col-12 col-md-4 form-group">
                                        <label for="lzMax">{{ trans('localization.max_strings_per_job') }}</label>
                                        <input type="number" id="lzMax" name="max_strings_per_job" min="1" value="{{ old('max_strings_per_job', $settings['max_strings_per_job']) }}" class="form-control" required>
                                        <small class="form-text text-muted">{{ trans('localization.max_strings_hint') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button></div>
                        </form>
                    </div>

                    {{-- Cost controls --}}
                    <div class="tab-pane fade {{ $tab === 'costs' ? 'show active' : '' }}" id="lz-pane-costs" role="tabpanel" aria-labelledby="lz-tab-costs">
                        <form action="{{ $saveUrl }}" method="post" class="card">
                            @csrf
                            <input type="hidden" name="tab" value="costs">
                            <div class="card-header"><h4>{{ trans('localization.tab_costs') }}</h4></div>
                            <div class="card-body">
                                <p class="text-muted">{{ trans('localization.costs_intro') }}</p>
                                @php
                                    $priced = array_values(array_unique(array_filter(array_merge(
                                        [$settings['model'], $settings['qa_model'], $settings['premium_model']],
                                        array_keys($modelPrices)
                                    ))));
                                @endphp
                                <div class="table-responsive">
                                    <table class="table lz-table mb-3">
                                        <thead><tr><th>{{ trans('localization.model') }}</th><th>{{ trans('localization.price_input') }}</th><th>{{ trans('localization.price_output') }}</th></tr></thead>
                                        <tbody>
                                        @forelse($priced as $model)
                                            <tr>
                                                <td dir="ltr"><code>{{ $model }}</code></td>
                                                <td><input type="number" step="0.001" min="0" name="model_prices[{{ $model }}][in]" value="{{ $modelPrices[$model]['in'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ $model }} {{ trans('localization.price_input') }}"></td>
                                                <td><input type="number" step="0.001" min="0" name="model_prices[{{ $model }}][out]" value="{{ $modelPrices[$model]['out'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ $model }} {{ trans('localization.price_output') }}"></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-muted">{{ trans('localization.choose_models_first') }}</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-row">
                                    <div class="col-6 col-md-4 form-group">
                                        <label for="lzPriceIn">{{ trans('localization.fallback_price_input') }}</label>
                                        <input type="number" step="0.001" min="0" id="lzPriceIn" name="price_input_per_million" value="{{ old('price_input_per_million', $settings['price_input_per_million']) }}" class="form-control">
                                    </div>
                                    <div class="col-6 col-md-4 form-group">
                                        <label for="lzPriceOut">{{ trans('localization.fallback_price_output') }}</label>
                                        <input type="number" step="0.001" min="0" id="lzPriceOut" name="price_output_per_million" value="{{ old('price_output_per_million', $settings['price_output_per_million']) }}" class="form-control">
                                    </div>
                                    <div class="col-12 col-md-4 form-group">
                                        <label for="lzCostCap">{{ trans('localization.max_cost_per_job') }}</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                            <input type="number" step="0.01" min="0" id="lzCostCap" name="max_cost_per_job" value="{{ old('max_cost_per_job', $settings['max_cost_per_job']) }}" class="form-control" placeholder="{{ trans('localization.no_limit') }}">
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">{{ trans('localization.price_hint') }}</small>
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts_bottom')
    @include('admin.localization.partials.script')
@endpush
