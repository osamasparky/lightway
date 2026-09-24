@extends(getTemplate().'.layouts.app')

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => trans('home.blog'),
        'subtitle' => $blogCount . ' ' . trans('site.posts'),
        'breadcrumbs' => [['title' => trans('home.blog')]],
        'search' => ['action' => '/blog', 'name' => 'search', 'placeholder' => trans('home.blog_search_placeholder'), 'keep' => ['author']],
    ])

    <div class="ms-container lw-page">
        <div class="lw-with-sidebar lw-with-sidebar--end">
            <div class="lw-stack">
                @if($blog->count())
                    <div class="lw-list">
                        @foreach($blog as $post)
                            @include('web.default.includes.lightway.blog_card', ['post' => $post])
                        @endforeach
                    </div>
                @else
                    <div class="lw-empty">
                        @include(getTemplate() . '.includes.no-result', [
                            'file_name' => 'webinar.png',
                            'title' => trans('site.no_result_search'),
                            'hint' => trans('home.lw_try_other_filters'),
                        ])
                    </div>
                @endif

                {{ $blog->appends(request()->input())->links('web.default.includes.lightway.pagination') }}
            </div>

            @include('web.default.includes.lightway.blog_sidebar')
        </div>
    </div>
@endsection
