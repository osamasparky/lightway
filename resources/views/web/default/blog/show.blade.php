@extends(getTemplate().'.layouts.app')

@section('content')
    @php
        $postCrumbs = [['title' => trans('home.blog'), 'url' => '/blog']];
        if (!empty($post->category)) {
            $postCrumbs[] = ['title' => $post->category->title, 'url' => $post->category->getUrl()];
        }
        $postCrumbs[] = ['title' => $post->title];
    @endphp

    <section class="lw-banner lw-banner--item ms-lattice">
        <div class="ms-container lw-banner__inner">
            <div class="lw-banner__text">
                <nav class="lw-breadcrumb" aria-label="{{ trans('home.lw_breadcrumb') }}">
                    <ol>
                        <li><a href="/">{{ trans('home.ms_home_link') }}</a></li>
                        @foreach($postCrumbs as $crumb)
                            <li>
                                @if(!$loop->last and !empty($crumb['url']))
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                                @else
                                    <span @if($loop->last) aria-current="page" @endif>{{ $crumb['title'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <h1 class="lw-banner__title lw-banner__title--item" dir="auto">{{ $post->title }}</h1>

                <div class="lw-item-meta">
                    @if(!empty($post->author))
                        <span>
                            <i data-feather="user" width="16" height="16" aria-hidden="true"></i>
                            {{ trans('public.created_by') }}
                            @if($post->author->isTeacher())
                                <a href="{{ $post->author->getProfileUrl() }}" target="_blank" class="lw-item-meta__link">{{ $post->author->full_name }}</a>
                            @elseif(!empty($post->author->full_name))
                                <strong>{{ $post->author->full_name }}</strong>
                            @endif
                        </span>
                    @endif

                    @if(!empty($post->category))
                        <span>{{ trans('public.in') }} <a href="{{ $post->category->getUrl() }}" class="lw-item-meta__link">{{ $post->category->title }}</a></span>
                    @endif

                    <span>
                        <i data-feather="calendar" width="16" height="16" aria-hidden="true"></i>
                        <time datetime="{{ date('Y-m-d', $post->created_at) }}">{{ dateTimeFormat($post->created_at, 'j M Y') }}</time>
                    </span>

                    <button type="button" class="js-share-blog lw-link-btn lw-link-btn--strong">
                        <i data-feather="share-2" width="16" height="16" aria-hidden="true"></i>
                        {{ trans('public.share') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="ms-band lw-banner__band" aria-hidden="true"></div>
    </section>

    <div class="ms-container lw-page">
        <div class="lw-with-sidebar lw-with-sidebar--end">
            <div class="lw-stack">
                <article class="lw-panel lw-article">
                    @include('web.default.includes.lightway.arch', ['src' => $post->image, 'alt' => $post->title, 'class' => 'lw-arch--hero', 'lazy' => false])

                    <div class="lw-prose post-show" dir="auto">
                        {!! nl2br($post->content) !!}
                    </div>
                </article>

                {{-- post Comments --}}
                @if($post->enable_comment)
                    <div class="lw-panel lw-comments">
                        @include('web.default.includes.comments',[
                                'comments' => $post->comments,
                                'inputName' => 'blog_id',
                                'inputValue' => $post->id
                            ])
                    </div>
                @endif
            </div>

            <div class="lw-stack">
                @if(!empty($post->author) and !empty($post->author->full_name))
                    <div class="lw-card lw-instructor lw-instructor--sidebar">
                        <span class="lw-ring-avatar">
                            <img loading="lazy" src="{{ $post->author->getAvatar(100) }}" alt="{{ $post->author->full_name }}">
                        </span>
                        <h2 class="lw-instructor__name" dir="auto">{{ $post->author->full_name }}</h2>

                        @if(!empty($post->author->role))
                            <span class="lw-instructor__bio">{{ $post->author->role->caption }}</span>
                        @endif

                        <a href="/blog?author={{ $post->author->id }}" class="lw-btn lw-btn--dark lw-btn--block">{{ trans('public.author_posts') }}</a>
                    </div>
                @endif

                @include('web.default.includes.lightway.blog_sidebar')
            </div>
        </div>
    </div>

    @include('web.default.blog.share_modal')
@endsection

@push('scripts_bottom')
    <script>
        var webinarDemoLang = '{{ trans('webinars.webinar_demo') }}';
        var replyLang = '{{ trans('panel.reply') }}';
        var closeLang = '{{ trans('public.close') }}';
        var saveLang = '{{ trans('public.save') }}';
        var reportLang = '{{ trans('panel.report') }}';
        var reportSuccessLang = '{{ trans('panel.report_success') }}';
        var messageToReviewerLang = '{{ trans('public.message_to_reviewer') }}';
        var copyLang = '{{ trans('public.copy') }}';
        var copiedLang = '{{ trans('public.copied') }}';
    </script>

    <script src="/assets/default/js/parts/comment.min.js"></script>
    <script src="/assets/default/js/parts/blog.min.js"></script>
@endpush
