<div class="lw-forum-row">
    <div class="lw-forum-row__main">
        <span class="lw-topic__icon lw-topic__icon--sm"><img loading="lazy" src="{{ $forum->icon }}" alt=""></span>
        <div>
            <a href="{{ $forum->getUrl() }}" class="lw-forum-row__title" dir="auto">{{ $forum->title }}</a>
            @if(!empty($forum->description))
                <p class="lw-forum-row__desc" dir="auto">{{ $forum->description }}</p>
            @endif
        </div>
    </div>

    <div class="lw-forum-row__counts">
        <span><strong>{{ $forum->topics_count }}</strong> {{ trans('update.topics') }}</span>
        <span><strong>{{ $forum->posts_count }}</strong> {{ trans('site.posts') }}</span>
    </div>

    <div class="lw-forum-row__last">
        @if(!empty($forum->lastTopic))
            <img loading="lazy" src="{{ $forum->lastTopic->creator->getAvatar(39) }}" alt="{{ $forum->lastTopic->creator->full_name }}">
            <div>
                <a href="{{ $forum->lastTopic->getPostsUrl() }}" dir="auto">{{ truncate($forum->lastTopic->title, 30) }}</a>
                <span><strong>{{ $forum->lastTopic->creator->full_name }}</strong> · {{ dateTimeFormat($forum->lastTopic->created_at, 'j M Y | H:i') }}</span>
            </div>
        @endif
    </div>
</div>
