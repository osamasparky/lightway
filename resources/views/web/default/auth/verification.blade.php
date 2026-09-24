@extends(getTemplate().'.layouts.app')

@section('content')
    <section class="lw-auth-bg ms-lattice">
    <div class="ms-container lw-page">
        <div class="lw-auth login-container">

            <div class="lw-auth-col">

                <div class="lw-auth-card login-card">
                    <h1 class="lw-auth-card__title">{{ trans('auth.account_verification') }}</h1>

                    <p>{{ trans('auth.account_verification_hint',['username' => $username]) }}</p>
                    <form method="post" action="/verification" class="mt-35">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">

                        <input type="hidden" name="username" value="{{ $usernameValue }}">

                        <div class="form-group">
                            <label class="input-label" for="code">{{ trans('auth.code') }}:</label>
                            <input type="tel" name="code" class="form-control @error('code') is-invalid @enderror" id="code"
                                   aria-describedby="codeHelp">
                            @error('code')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-block mt-20">{{ trans('auth.verification') }}</button>
                    </form>

                    <div class="text-center mt-20">
                        <span class="text-secondary">
                            <a href="/verification/resend" class="font-weight-bold">{{ trans('auth.resend_code') }}</a>
                        </span>
                    </div>
                </div>
            </div>

            @include('web.default.includes.lightway.auth_panel', ['welcome' => trans('home.lw_auth_welcome_login'), 'hint' => trans('home.lw_auth_welcome_login_hint')])
        </div>
    </div>
    </section>
@endsection
