@extends('web.default.layouts.app')

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => trans('update.forums'),
        'subtitle' => trans('update.need_help?') . ' ' . trans('update.create_a_topic_in_forum'),
        'breadcrumbs' => [['title' => trans('update.forums')]],
        'search' => ['action' => '/forums/search', 'name' => 'search', 'placeholder' => trans('update.search_discussions')],
    ])

    <div class="ms-container lw-page lw-stack lw-forum">
        {{-- Stats --}}
        <div class="lw-forum-stats">
            @include('web.default.includes.lightway.medallion', ['value' => $forumsCount, 'label' => trans('update.forums'), 'tone' => 'blue'])
            @include('web.default.includes.lightway.medallion', ['value' => $topicsCount, 'label' => trans('update.topics'), 'tone' => 'orange'])
            @include('web.default.includes.lightway.medallion', ['value' => $postsCount, 'label' => trans('site.posts'), 'tone' => 'blue'])
            @include('web.default.includes.lightway.medallion', ['value' => $membersCount, 'label' => trans('update.members'), 'tone' => 'orange'])
        </div>

        {{-- Featured topics --}}
        @if(!empty($featuredTopics) and count($featuredTopics))
            <section class="lw-section">
                <div class="lw-section__head">
                    <h2 class="lw-section__title">
                        @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#FBF6EC'])
                        {{ trans('update.featured_topics') }}
                    </h2>
                    <p class="lw-section__hint">{{ trans('update.featured_topics_hint') }}</p>
                </div>

                <div class="lw-list">
                    @foreach($featuredTopics as $featuredTopic)
                        <article class="lw-card lw-topic">
                            <span class="lw-topic__icon"><img loading="lazy" src="{{ $featuredTopic->icon }}" alt=""></span>

                            <div class="lw-topic__body">
                                <h3 class="lw-card__title" dir="auto">
                                    <a href="{{ $featuredTopic->topic->getPostsUrl() }}" class="lw-stretched-link">{{ $featuredTopic->topic->title }}</a>
                                </h3>
                                <p class="lw-topic__excerpt" dir="auto">{!! truncate(strip_tags($featuredTopic->topic->description), 100) !!}</p>

                                <div class="lw-topic__meta">
                                    @if(!empty($featuredTopic->usersAvatars) and count($featuredTopic->usersAvatars))
                                        <span class="lw-avatars">
                                            @foreach($featuredTopic->usersAvatars as $userAvatar)
                                                <img loading="lazy" src="{{ $userAvatar->getAvatar(32) }}" alt="{{ $userAvatar->full_name }}">
                                            @endforeach
                                            @if(($featuredTopic->topic->posts_count - count($featuredTopic->usersAvatars)) > 0)
                                                <span>+{{ ($featuredTopic->topic->posts_count - count($featuredTopic->usersAvatars)) }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    <span>{{ trans('public.created_by') }} <strong>{{ $featuredTopic->topic->creator->full_name }}</strong></span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ trans('update.n_posts', ['count' => $featuredTopic->topic->posts_count]) }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Forums --}}
        @if(!empty($forums) and count($forums))
            <section class="lw-section">
                <div class="lw-section__head">
                    <h2 class="lw-section__title">
                        @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#FBF6EC'])
                        {{ trans('update.forums') }}
                    </h2>
                    <p class="lw-section__hint">{{ trans('update.forums_categories_hints') }}</p>
                </div>

                <div class="lw-stack">
                    @foreach($forums as $forum)
                        @component('web.default.includes.lightway.panel', ['title' => $forum->title])
                            <div class="lw-forum-rows">
                                @if(!empty($forum->subForums) and count($forum->subForums))
                                    @foreach($forum->subForums as $subForum)
                                        @include('web.default.forum.forum_card', ['forum' => $subForum])
                                    @endforeach
                                @else
                                    @include('web.default.forum.forum_card', ['forum' => $forum])
                                @endif
                            </div>
                        @endcomponent
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Recommended topics --}}
        @if(!empty($recommendedTopics) and count($recommendedTopics))
            <section class="lw-section">
                <div class="lw-section__head">
                    <h2 class="lw-section__title">
                        @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#FBF6EC'])
                        {{ trans('update.recommended_topics') }}
                    </h2>
                    <p class="lw-section__hint">{{ trans('update.recommended_topics_hint') }}</p>
                </div>

                <div class="lw-grid lw-grid--4">
                    @foreach($recommendedTopics as $recommendedTopic)
                        <div class="lw-card lw-recommended">
                            <span class="lw-topic__icon"><img loading="lazy" src="{{ $recommendedTopic->icon }}" alt=""></span>
                            <h3 class="lw-recommended__title" dir="auto">{{ $recommendedTopic->title }}</h3>
                            <ul class="lw-recommended__list">
                                @foreach($recommendedTopic->topics as $topic)
                                    <li><a href="{{ $topic->getPostsUrl() }}" dir="auto">{{ truncate($topic->title, 25) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ask a question --}}
        <section class="lw-cta-band ms-lattice-dark">
            <div class="lw-cta-band__text">
                <h2 class="lw-cta-band__title">{{ trans('update.have_a_question?') }} {{ trans('update.ask_it_in_forum_and_get_answer') }}</h2>
                <p>{{ trans('update.have_a_question_hint') }}</p>
            </div>
            <div class="lw-cta-band__actions">
                <a href="/forums/create-topic" class="lw-btn lw-btn--cta">
                    <i data-feather="edit-3" width="16" height="16" aria-hidden="true"></i>
                    {{ trans('update.create_a_new_topic') }}
                </a>
                <a href="/forums" class="lw-btn lw-btn--ghost-light">{{ trans('update.browse_forums') }}</a>
            </div>
        </section>
    </div>
@endsection
