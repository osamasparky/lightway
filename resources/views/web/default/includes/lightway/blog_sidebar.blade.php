{{-- Blog sidebar: categories + popular posts (blog index and post page). --}}
<aside class="lw-stack" aria-label="{{ trans('site.posts') }}">
    @if(!empty($blogCategories) and count($blogCategories))
        @component('web.default.includes.lightway.panel', ['title' => trans('categories.categories')])
            <nav aria-label="{{ trans('categories.categories') }}">
                @foreach($blogCategories as $blogCategory)
                    @php $isCurrent = (request()->is('blog/categories/' . $blogCategory->slug)); @endphp
                    <a href="{{ $blogCategory->getUrl() }}" class="lw-panel-link {{ $isCurrent ? 'is-active' : '' }}" @if($isCurrent) aria-current="page" @endif dir="auto">{{ $blogCategory->title }}</a>
                @endforeach
            </nav>
        @endcomponent
    @endif

    @if(!empty($popularPosts) and count($popularPosts))
        @component('web.default.includes.lightway.panel', ['title' => trans('site.popular_posts')])
            <ul class="lw-mini-posts">
                @foreach($popularPosts as $popularPost)
                    <li class="lw-mini-post">
                        <span class="lw-mini-post__img">
                            <img loading="lazy" src="{{ $popularPost->image }}" alt="">
                        </span>
                        <span class="lw-mini-post__text">
                            <a href="{{ $popularPost->getUrl() }}" dir="auto">{{ truncate($popularPost->title, 50) }}</a>
                            <time datetime="{{ date('Y-m-d', $popularPost->created_at) }}">{{ dateTimeFormat($popularPost->created_at, 'j M Y') }}</time>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endcomponent

        <a href="/blog" class="lw-btn lw-btn--outline lw-btn--block">{{ trans('home.view_all') }} {{ trans('site.posts') }}</a>
    @endif
</aside>
