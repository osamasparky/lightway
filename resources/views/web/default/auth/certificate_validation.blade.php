@extends(getTemplate().'.layouts.app')

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => trans('site.certificate_validation'),
        'subtitle' => trans('site.certificate_validation_hint'),
        'breadcrumbs' => [['title' => trans('site.certificate_validation')]],
    ])

    <div class="ms-container lw-page">
        <div class="lw-auth lw-cert">
            <div class="lw-auth-card lw-cert__card">
                <h2 class="lw-form-card__title">
                    @include('web.default.includes.manuscript.star', ['size' => 22, 'dot' => '#FFFDF8'])
                    {{ trans('site.certificate_validation') }}
                </h2>

                <form method="post" action="/certificate_validation/validate">
                    {{ csrf_field() }}

                    <div class="form-group">
                        <label class="input-label" for="certificate_id">{{ trans('public.certificate_id') }}</label>
                        <input type="tel" name="certificate_id" class="form-control" id="certificate_id" inputmode="numeric" aria-describedby="certificate_idHelp">
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="form-group">
                        <label class="input-label" for="lwCertCaptcha">{{ trans('site.captcha') }}</label>
                        <div class="lw-captcha">
                            <div class="lw-captcha__input">
                                <input type="text" name="captcha" id="lwCertCaptcha" class="form-control" autocomplete="off">
                                <div class="invalid-feedback"></div>
                            </div>
                            <img id="captchaImageComment" class="captcha-image" src="" alt="{{ trans('site.captcha') }}">
                            <button type="button" id="refreshCaptcha" class="lw-icon-btn" aria-label="{{ trans('home.lw_refresh_captcha') }}">
                                <i data-feather="refresh-ccw" width="18" height="18" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" id="formSubmit" class="lw-btn lw-btn--cta lw-btn--block">{{ trans('cart.validate') }}</button>
                </form>
            </div>

            {{-- Shape of the result; the real result opens in #certificateModal after validation --}}
            <aside class="lw-cert-preview" aria-label="{{ trans('home.lw_cert_preview') }}">
                <span class="lw-cert-preview__tag">{{ trans('home.lw_cert_preview') }}</span>
                @include('web.default.includes.lightway.medallion', ['value' => '✓', 'tone' => 'blue', 'size' => 'sm'])
                <h2 class="lw-cert-preview__title">{{ trans('site.certificate_is_valid') }}</h2>
                <p class="lw-cert-preview__hint">{{ trans('site.certificate_is_valid_hint') }}</p>

                <dl class="lw-specs lw-cert-preview__rows">
                    <div><dt>{{ trans('quiz.student') }}</dt><dd>—</dd></div>
                    <div><dt>{{ trans('public.date') }}</dt><dd>—</dd></div>
                    <div><dt>{{ trans('webinars.webinar') }}</dt><dd>—</dd></div>
                </dl>
            </aside>
        </div>
    </div>

    <div id="certificateModal" class="d-none">
        <h3 class="section-title after-line">{{ trans('site.certificate_is_valid') }}</h3>
        <div class="mt-25 d-flex flex-column align-items-center">
            <img loading="lazy" src="/assets/default/img/check.png" alt="" width="120" height="117">
            <p class="mt-10">{{ trans('site.certificate_is_valid_hint') }}</p>
            <div class="w-75">

                <div class="mt-15 d-flex justify-content-between">
                    <span class="text-gray font-weight-bold">{{ trans('quiz.student') }}:</span>
                    <span class="text-gray modal-student"></span>
                </div>

                <div class="mt-10 d-flex justify-content-between">
                    <span class="text-gray font-weight-bold">{{ trans('public.date') }}:</span>
                    <span class="text-gray"><span class="modal-date"></span></span>
                </div>

                <div class="mt-10 d-flex justify-content-between">
                    <span class="text-gray font-weight-bold">{{ trans('webinars.webinar') }}:</span>
                    <span class="text-gray"><span class="modal-webinar"></span></span>
                </div>
            </div>
        </div>

        <div class="mt-30 d-flex align-items-center justify-content-end">
            <button type="button" class="btn btn-sm btn-danger ml-10 close-swl">{{ trans('public.close') }}</button>
        </div>
    </div>
@endsection

@push('scripts_bottom')
    <script>
        var certificateNotFound = '{{ trans('site.certificate_not_found') }}';
        var close = '{{ trans('public.close') }}';
    </script>

    <script src="/assets/default/js/parts/certificate_validation.min.js"></script>
@endpush
