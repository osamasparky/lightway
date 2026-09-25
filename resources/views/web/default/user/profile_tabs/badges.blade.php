@if(!empty($userBadges) and count($userBadges))
    @component('web.default.includes.lightway.panel', ['title' => trans('site.badges')])
        <div class="lw-badge-grid">
            @foreach($userBadges as $userBadge)
                <div class="lw-badge-tile">
                    <img loading="lazy" src="{{ !empty($userBadge->badge_id) ? $userBadge->badge->image : $userBadge->image }}" width="44" height="44" alt="">
                    <span>
                        <strong dir="auto">{{ !empty($userBadge->badge_id) ? $userBadge->badge->title : $userBadge->title }}</strong>
                        <span dir="auto">{!! (!empty($userBadge->badge_id) ? nl2br($userBadge->badge->description) : nl2br($userBadge->description)) !!}</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endcomponent
@else
    <div class="lw-empty">
        @include(getTemplate() . '.includes.no-result',[
            'file_name' => 'badge.png',
            'title' => trans('site.instructor_not_have_badge'),
            'hint' => '',
        ])
    </div>
@endif
