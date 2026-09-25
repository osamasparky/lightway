@if(!empty($user->blog) and !$user->blog->isEmpty())
    <div class="lw-grid">
        @foreach($user->blog as $post)
            @include('web.default.includes.lightway.blog_card', ['post' => $post, 'variant' => 'grid'])
        @endforeach
    </div>
@else
    <div class="lw-empty">
        @include(getTemplate() . '.includes.no-result',[
            'file_name' => 'webinar.png',
            'title' => trans('update.instructor_not_have_posts'),
            'hint' => '',
        ])
    </div>
@endif
