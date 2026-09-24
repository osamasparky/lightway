@extends(getTemplate().'.layouts.app')

@section('content')
    <section class="lw-auth-bg ms-lattice">
    <div class="ms-container lw-page">
        <div class="lw-auth login-container">

            <div class="lw-auth-col">
                <div class="lw-auth-card login-card">
                    <h1 class="lw-auth-card__title">{{ trans('auth.reset_password') }}</h1>
                    <form method="post" action="/reset-password" class="mt-35">
                        {{ csrf_field() }}

                        <div class="form-group">
                            <label class="input-label" for="email">{{ trans('auth.email') }}:</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                   value="{{ request()->get('email') }}" aria-describedby="emailHelp">
                            @error('email')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="input-label" for="password">{{ trans('auth.password') }}:</label>
                            <input name="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror" id="password"
                                   aria-describedby="passwordHelp">
                            @error('password')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="input-label" for="confirm_password">{{ trans('auth.retype_password') }}:</label>
                            <input name="password_confirmation" type="password"
                                   class="form-control @error('password_confirmation') is-invalid @enderror" id="confirm_password"
                                   aria-describedby="confirmPasswordHelp">
                            @error('password_confirmation')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <input hidden name="token" placeholder="token" value="{{ $token }}">

                        <button type="submit" class="btn btn-primary btn-block mt-20">{{ trans('auth.reset_password') }}</button>
                    </form>
                </div>
            </div>

            @include('web.default.includes.lightway.auth_panel', ['welcome' => trans('home.lw_auth_welcome_login'), 'hint' => trans('home.lw_auth_welcome_login_hint')])
        </div>
    </div>
    </section>
@endsection
