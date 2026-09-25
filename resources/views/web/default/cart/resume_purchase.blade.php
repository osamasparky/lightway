@extends(getTemplate().'.layouts.app')

@section('content')
    <div class="ms-container lw-page">
        <div class="lw-form-card lw-form lw-resume">
            <h1 class="lw-form-card__title">
                @include('web.default.includes.manuscript.star', ['size' => 22, 'dot' => '#FFFDF8'])
                {{ trans('update.buy_now') }}
            </h1>

            <form action="{{ $action }}" method="post" id="lwResumePurchase">
                {{ csrf_field() }}
                @foreach($fields as [$name, $value])
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                <p class="lw-muted">{{ trans('home.lw_resume_purchase_hint') }}</p>
                <button type="submit" class="lw-btn lw-btn--cta">{{ trans('home.lw_resume_purchase_btn') }}</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts_bottom')
    <script>
        (function () {
            "use strict";
            // Continue the purchase the guest started before logging in.
            document.getElementById('lwResumePurchase').submit();
        })();
    </script>
@endpush
