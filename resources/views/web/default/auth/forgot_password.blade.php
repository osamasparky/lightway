@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/select2/select2.min.css">
@endpush

@section('content')

    <section class="lw-auth-bg ms-lattice">
    <div class="ms-container lw-page">
        <div class="lw-auth login-container">

            <div class="lw-auth-col">

                <div class="lw-auth-card login-card">
                    <h1 class="lw-auth-card__title">{{ trans('auth.forget_password') }}</h1>

                    <form method="post" action="/forget-password" class="mt-35">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">

                        @include('web.default.auth.includes.register_methods')

                        @if(!empty(getGeneralSecuritySettings('captcha_for_forgot_pass')))
                            @include('web.default.includes.captcha_input')
                        @endif


                        <button type="submit" class="btn btn-primary btn-block mt-20">{{ trans('auth.reset_password') }}</button>
                    </form>

                    <div class="text-center mt-20">
                        <span class="badge badge-circle-gray300 text-secondary d-inline-flex align-items-center justify-content-center">or</span>
                    </div>

                    <div class="text-center mt-20">
                        <span class="text-secondary">
                            <a href="/login" class="text-secondary font-weight-bold">{{ trans('auth.login') }}</a>
                        </span>
                    </div>
                </div>
            </div>

            @include('web.default.includes.lightway.auth_panel', ['welcome' => trans('home.lw_auth_welcome_login'), 'hint' => trans('home.lw_auth_welcome_login_hint')])
        </div>
    </div>
    </section>
@endsection

@push('scripts_bottom')
    <script src="/assets/default/vendors/select2/select2.min.js"></script>
    <script src="/assets/default/js/parts/forgot_password.min.js"></script>
@endpush
