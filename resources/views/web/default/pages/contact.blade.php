@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.css">
@endpush

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => trans('site.contact_us'),
        'subtitle' => trans('home.lw_contact_hint'),
        'breadcrumbs' => [['title' => trans('site.contact_us')]],
    ])

    <div class="ms-container lw-page lw-stack lw-contact">
        @if(!empty($contactSettings['latitude']) and !empty($contactSettings['longitude']))
            <div class="lw-map">
                <div class="contact-map" id="contactMap"
                     data-latitude="{{ $contactSettings['latitude'] }}"
                     data-longitude="{{ $contactSettings['longitude'] }}"
                     data-zoom="{{ $contactSettings['map_zoom'] ?? 12 }}"
                ></div>
            </div>
        @endif

        <div class="lw-contact-cards">
            <div class="lw-card lw-contact-card">
                <span class="lw-contact-card__icon" aria-hidden="true"><i data-feather="map-pin" width="22" height="22"></i></span>
                <h2 class="lw-contact-card__title">{{ trans('site.our_address') }}</h2>
                <p dir="auto">
                    @if(!empty($contactSettings['address']))
                        {!! nl2br($contactSettings['address']) !!}
                    @else
                        {{ trans('site.not_defined') }}
                    @endif
                </p>
            </div>

            <div class="lw-card lw-contact-card">
                <span class="lw-contact-card__icon" aria-hidden="true"><i data-feather="phone" width="22" height="22"></i></span>
                <h2 class="lw-contact-card__title">{{ trans('site.phone_number') }}</h2>
                <p dir="ltr">
                    @if(!empty($contactSettings['phones']))
                        {!! nl2br(str_replace(',','<br/>',$contactSettings['phones'])) !!}
                    @else
                        {{ trans('site.not_defined') }}
                    @endif
                </p>
            </div>

            <div class="lw-card lw-contact-card">
                <span class="lw-contact-card__icon" aria-hidden="true"><i data-feather="mail" width="22" height="22"></i></span>
                <h2 class="lw-contact-card__title">{{ trans('public.email') }}</h2>
                <p dir="auto">
                    @if(!empty($contactSettings['emails']))
                        {!! nl2br(str_replace(',','<br/>',$contactSettings['emails'])) !!}
                    @else
                        {{ trans('site.not_defined') }}
                    @endif
                </p>
            </div>
        </div>

        <section class="lw-form-card lw-form" aria-labelledby="lwContactFormTitle">
            <h2 class="lw-form-card__title" id="lwContactFormTitle">
                @include('web.default.includes.manuscript.star', ['size' => 22, 'dot' => '#FFFDF8'])
                {{ trans('site.send_your_message_directly') }}
            </h2>

            @if(!empty(session()->has('msg')))
                <div class="lw-alert lw-alert--success" role="status">
                    <i data-feather="check-circle" width="22" height="22" aria-hidden="true"></i>
                    {{ session()->get('msg') }}
                </div>
            @endif

            <form action="/contact/store" method="post">
                {{ csrf_field() }}

                <div class="lw-form-grid lw-form-grid--3">
                    <div class="form-group">
                        <label class="input-label" for="contactName">{{ trans('site.your_name') }}</label>
                        <input type="text" id="contactName" name="name" value="{{ old('name') }}" autocomplete="name" class="form-control @error('name') is-invalid @enderror"/>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="input-label" for="contactEmail">{{ trans('public.email') }}</label>
                        <input type="text" id="contactEmail" name="email" value="{{ old('email') }}" autocomplete="email" class="form-control @error('email') is-invalid @enderror"/>
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="input-label" for="contactPhone">{{ trans('site.phone_number') }}</label>
                        <input type="text" id="contactPhone" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror"/>
                        @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="input-label" for="contactSubject">{{ trans('site.subject') }}</label>
                    <input type="text" id="contactSubject" name="subject" value="{{ old('subject') }}" class="form-control @error('subject') is-invalid @enderror"/>
                    @error('subject')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="input-label" for="contactMessage">{{ trans('site.message') }}</label>
                    <textarea name="message" id="contactMessage" rows="8" class="form-control @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                    @error('message')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="lw-form-foot">
                    <div class="lw-form-foot__captcha">
                        @include('web.default.includes.captcha_input')
                    </div>

                    <button type="submit" class="lw-btn lw-btn--dark">{{ trans('site.send_message') }}</button>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts_bottom')
    <script src="/assets/vendors/leaflet/leaflet.min.js"></script>
    <script>
        var leafletApiPath = '{{ getLeafletApiPath() }}';
    </script>
    <script src="/assets/default/js/parts/contact.min.js"></script>
@endpush
