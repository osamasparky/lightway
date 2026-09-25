@if($user->offline)
    <div class="lw-panel lw-offline-card">
        <img loading="lazy" src="/assets/default/img/profile/time-icon.png" alt="" width="48" height="48">
        <div>
            <h3>{{ trans('public.instructor_is_not_available') }}</h3>
            <p>{{ $user->offline_message }}</p>
        </div>
    </div>
@endif

@if((!empty($educations) and !$educations->isEmpty()) or (!empty($experiences) and !$experiences->isEmpty()) or (!empty($occupations) and !$occupations->isEmpty()) or !empty($user->about))
    <div class="lw-stack">
        @if(!empty($user->about))
            @component('web.default.includes.lightway.panel', ['title' => trans('site.about')])
                <div class="lw-prose" dir="auto">{!! nl2br($user->about) !!}</div>
            @endcomponent
        @endif

        @if((!empty($educations) and !$educations->isEmpty()) or (!empty($occupations) and !$occupations->isEmpty()))
            <div class="lw-two-col">
                @if(!empty($educations) and !$educations->isEmpty())
                    @component('web.default.includes.lightway.panel', ['title' => trans('site.education')])
                        <ul class="lw-diamond-list">
                            @foreach($educations as $education)
                                <li dir="auto">{{ $education->value }}</li>
                            @endforeach
                        </ul>
                    @endcomponent
                @endif

                @if(!empty($occupations) and !$occupations->isEmpty())
                    @component('web.default.includes.lightway.panel', ['title' => trans('site.occupations')])
                        <div class="lw-tags">
                            @foreach($occupations as $occupation)
                                <span class="lw-tag">{{ $occupation->category->title }}</span>
                            @endforeach
                        </div>
                    @endcomponent
                @endif
            </div>
        @endif

        @if(!empty($experiences) and !$experiences->isEmpty())
            @component('web.default.includes.lightway.panel', ['title' => trans('site.experiences')])
                <ul class="lw-diamond-list">
                    @foreach($experiences as $experience)
                        <li dir="auto">{{ $experience->value }}</li>
                    @endforeach
                </ul>
            @endcomponent
        @endif
    </div>
@else
    <div class="lw-empty">
        @include(getTemplate() . '.includes.no-result',[
            'file_name' => 'bio.png',
            'title' => trans('site.not_create_bio'),
            'hint' => '',
        ])
    </div>
@endif
