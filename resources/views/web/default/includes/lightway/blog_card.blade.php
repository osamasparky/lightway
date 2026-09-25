{{--
    Blog post card (horizontal on wide screens). Params: $post, $variant ('row'|'grid')
    Keeps: custom badges, date, excerpt, author, comments count.
--}}
<article class="lw-card lw-post {{ ($variant ?? 'row') == 'grid' ? 'lw-post--grid' : '' }}">
    <div class="lw-post__media">
        @include('web.default.includes.lightway.arch', ['src' => $post->image, 'alt' => $post->title, 'class' => 'lw-arch--post'])

        <div class="lw-course__badges">
            @include('web.default.includes.product_custom_badge', ['itemTarget' => $post])
        </div>
    </div>

    <div class="lw-post__body">
        <span class="lw-post__date">
            <i data-feather="calendar" width="15" height="15" aria-hidden="true"></i>
            <time datetime="{{ date('Y-m-d', $post->created_at) }}">{{ dateTimeFormat($post->created_at, 'j M Y') }}</time>
        </span>

        <h2 class="lw-post__title" dir="auto">
            <a href="{{ $post->getUrl() }}" class="lw-stretched-link">{{ $post->title }}</a>
        </h2>

        <p class="lw-post__excerpt" dir="auto">{{ truncate(strip_tags($post->description), 160) }}</p>

        <div class="lw-post__foot">
            <span>
                <i data-feather="user" width="16" height="16" aria-hidden="true"></i>
                <span dir="auto">{{ $post->author->full_name ?? '' }}</span>
            </span>
            <span>
                <i data-feather="message-circle" width="16" height="16" aria-hidden="true"></i>
                {{ trans('home.lw_comments_count', ['count' => $post->comments_count ?? 0]) }}
            </span>
        </div>
    </div>
</article>
