@extends('admin.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/admin/css/localization.css?v={{ @filemtime(public_path('assets/admin/css/localization.css')) }}">
@endpush

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
            <form action="{{ getAdminPanelUrl('/localization/settings') }}" method="post" autocomplete="off">
                @csrf
                <div class="row">
                    <div class="col-12 col-xl-6">
                        <div class="card">
                            <div class="card-header"><h4><i class="fas fa-robot mr-1"></i>{{ trans('localization.ai_provider') }}</h4></div>
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
                                        <div class="custom-control custom-checkbox mt-2">
                                            <input type="checkbox" class="custom-control-input" id="lzRemoveKey" name="remove_api_key" value="1">
                                            <label class="custom-control-label" for="lzRemoveKey">{{ trans('localization.remove_api_key') }}</label>
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="lzModel">{{ trans('localization.model') }}</label>
                                    <input type="text" id="lzModel" name="model" value="{{ old('model', $settings['model']) }}" class="form-control @error('model') is-invalid @enderror" list="lzModels" spellcheck="false" placeholder="{{ trans('localization.model_placeholder') }}">
                                    <datalist id="lzModels"></datalist>
                                    <small class="form-text text-muted">{{ trans('localization.model_hint') }}</small>
                                </div>

                                <div class="d-flex align-items-center flex-wrap lz-gap">
                                    <button type="button" class="btn btn-outline-primary js-lz-test" data-url="{{ getAdminPanelUrl('/localization/settings/test') }}" @disabled(!$maskedKey)>
                                        <i class="fas fa-plug mr-1"></i>{{ trans('localization.test_connection') }}
                                    </button>
                                    <span class="lz-test-result js-lz-test-result" role="status"></span>
                                </div>
                                @unless($maskedKey)
                                    <small class="form-text text-muted">{{ trans('localization.test_after_save') }}</small>
                                @endunless
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h4><i class="fas fa-sliders-h mr-1"></i>{{ trans('localization.limits') }}</h4></div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="col-6 form-group">
                                        <label for="lzBatch">{{ trans('localization.batch_size') }}</label>
                                        <input type="number" id="lzBatch" name="batch_size" min="5" max="100" value="{{ old('batch_size', $settings['batch_size']) }}" class="form-control" required>
                                        <small class="form-text text-muted">{{ trans('localization.batch_size_hint') }}</small>
                                    </div>
                                    <div class="col-6 form-group">
                                        <label for="lzTimeout">{{ trans('localization.timeout') }}</label>
                                        <input type="number" id="lzTimeout" name="timeout" min="15" max="300" value="{{ old('timeout', $settings['timeout']) }}" class="form-control" required>
                                    </div>
                                    <div class="col-12 form-group">
                                        <label for="lzMax">{{ trans('localization.max_strings_per_job') }}</label>
                                        <input type="number" id="lzMax" name="max_strings_per_job" min="1" value="{{ old('max_strings_per_job', $settings['max_strings_per_job']) }}" class="form-control" required>
                                        <small class="form-text text-muted">{{ trans('localization.max_strings_hint') }}</small>
                                    </div>
                                    <div class="col-6 form-group">
                                        <label for="lzPriceIn">{{ trans('localization.price_input') }}</label>
                                        <input type="number" step="0.001" min="0" id="lzPriceIn" name="price_input_per_million" value="{{ old('price_input_per_million', $settings['price_input_per_million']) }}" class="form-control">
                                    </div>
                                    <div class="col-6 form-group">
                                        <label for="lzPriceOut">{{ trans('localization.price_output') }}</label>
                                        <input type="number" step="0.001" min="0" id="lzPriceOut" name="price_output_per_million" value="{{ old('price_output_per_million', $settings['price_output_per_million']) }}" class="form-control">
                                    </div>
                                </div>
                                <small class="text-muted">{{ trans('localization.price_hint') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="card">
                            <div class="card-header"><h4><i class="fas fa-feather-alt mr-1"></i>{{ trans('localization.translation_style') }}</h4></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="lzTone">{{ trans('localization.tone') }}</label>
                                    <input type="text" id="lzTone" name="tone" value="{{ old('tone', $settings['tone']) }}" class="form-control" maxlength="100" required>
                                    <small class="form-text text-muted">{{ trans('localization.tone_hint') }}</small>
                                </div>
                                <div class="form-group">
                                    <label for="lzAudience">{{ trans('localization.audience') }}</label>
                                    <input type="text" id="lzAudience" name="audience" value="{{ old('audience', $settings['audience']) }}" class="form-control" maxlength="300" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="lzContext">{{ trans('localization.product_context') }}</label>
                                    <textarea id="lzContext" name="product_context" rows="4" class="form-control" maxlength="1000" required>{{ old('product_context', $settings['product_context']) }}</textarea>
                                    <small class="form-text text-muted">{{ trans('localization.product_context_hint') }}</small>
                                </div>
                            </div>
                            <div class="card-footer text-right">
                                <button type="submit" class="btn btn-primary">{{ trans('localization.save_settings') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Glossary --}}
            <div class="card" id="glossary">
                <div class="card-header">
                    <h4><i class="fas fa-book mr-1"></i>{{ trans('localization.glossary') }}</h4>
                    <div class="card-header-action small text-muted">{{ trans('localization.glossary_hint') }}</div>
                </div>
                <div class="card-body">
                    <form action="{{ getAdminPanelUrl('/localization/settings/glossary') }}" method="post" class="form-row align-items-end">
                        @csrf
                        <div class="col-12 col-md-3 form-group">
                            <label for="lzTerm">{{ trans('localization.term') }}</label>
                            <input type="text" id="lzTerm" name="term" class="form-control" maxlength="255" required placeholder="Meem LMS">
                        </div>
                        <div class="col-12 col-md-3 form-group">
                            <label for="lzTermTranslation">{{ trans('localization.term_translation') }}</label>
                            <input type="text" id="lzTermTranslation" name="translation" class="form-control" maxlength="255" placeholder="{{ trans('localization.keep_as_is') }}">
                        </div>
                        <div class="col-6 col-md-2 form-group">
                            <label for="lzTermLocale">{{ trans('localization.language') }}</label>
                            <select id="lzTermLocale" name="locale" class="form-control">
                                <option value="">{{ trans('localization.all_languages') }}</option>
                                @foreach($languages as $locale => $language)
                                    <option value="{{ $locale }}">{{ $language['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3 form-group">
                            <label for="lzTermNote">{{ trans('localization.note') }}</label>
                            <input type="text" id="lzTermNote" name="note" class="form-control" maxlength="255">
                        </div>
                        <div class="col-12 col-md-1 form-group">
                            <button type="submit" class="btn btn-primary btn-block">{{ trans('localization.add') }}</button>
                        </div>
                    </form>

                    @if($glossary->isEmpty())
                        <div class="lz-empty lz-empty--sm"><i class="fas fa-book-open"></i><p>{{ trans('localization.glossary_empty') }}</p></div>
                    @else
                        <div class="table-responsive">
                            <table class="table lz-table mb-0">
                                <thead><tr><th>{{ trans('localization.term') }}</th><th>{{ trans('localization.term_translation') }}</th><th>{{ trans('localization.language') }}</th><th>{{ trans('localization.note') }}</th><th></th></tr></thead>
                                <tbody>
                                @foreach($glossary as $term)
                                    <tr>
                                        <td><strong>{{ $term->term }}</strong></td>
                                        <td dir="auto">{!! $term->keepsOriginal() ? '<span class="badge badge-light">' . e(trans('localization.keep_as_is')) . '</span>' : e($term->translation) !!}</td>
                                        <td>{{ $term->locale ? ($languages[$term->locale]['name'] ?? $term->locale) : trans('localization.all_languages') }}</td>
                                        <td class="small text-muted">{{ $term->note }}</td>
                                        <td class="text-right">
                                            <form action="{{ getAdminPanelUrl('/localization/settings/glossary/' . $term->id . '/delete') }}" method="post" class="js-lz-confirm" data-confirm="{{ trans('localization.delete_term_confirm') }}">
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
        </div>
    </section>
@endsection

@push('scripts_bottom')
    @include('admin.localization.partials.script')
@endpush
