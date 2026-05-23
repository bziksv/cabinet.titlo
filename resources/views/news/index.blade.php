@component('component.card', ['title' => __('News and updates')])
    @if(\App\User::isUserAdmin())
        @slot('tools')
            <a href="{{ route('create.news') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-plus me-1"></i>{{ __('Add News') }}
            </a>
        @endslot
    @endif

    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/scroll/style.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-news.css') }}"/>
    @endslot

    @if(!$canComment)
        <div class="alert alert-warning small mb-3">
            <i class="bi bi-slash-circle me-1"></i>{{ __('You are blocked from commenting on news. Contact support if you think this is a mistake.') }}
        </div>
    @endif

    @isset($news[0])
        <div class="cabinet-news-feed position-relative">
            <div class="scroll-to d-flex flex-column">
                <a href="#header-nav-bar" class="fa fa-arrow-circle-up scroll_arrow text-muted" aria-label="Up"></a>
                <a href="#main-footer" class="fa fa-arrow-circle-down scroll_arrow text-muted" aria-label="Down"></a>
            </div>

            @foreach($news as $item)
                <article class="cabinet-news-post d-flex gap-3 pb-4 mb-4 border-bottom" id="news-{{ $item->id }}">
                    <img class="rounded-circle flex-shrink-0 cabinet-news-post__avatar"
                         src="{{ $item->user->image }}"
                         alt="{{ $item->user->name }}">

                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h4 class="h6 mb-0">{{ $item->user->name }}</h4>
                                <small class="text-secondary">{{ $item->created_at->diffForHumans() }}</small>
                            </div>
                            @if($item->user_id === \Illuminate\Support\Facades\Auth::id() || $admin)
                                <div class="btn-group btn-group-sm flex-shrink-0">
                                    <a href="{{ route('edit.news', $item->id) }}"
                                       class="btn btn-outline-secondary btn-sm"
                                       title="{{ __('Edit') }}">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#remove-news-{{ $item->id }}"
                                            title="{{ __('Remove') }}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="cabinet-news-post__content mt-2 mb-3">{!! $item->content !!}</div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary like-news @isset($item->like) text-danger @endisset"
                                    data-news-id="{{ $item->id }}">
                                <i class="far fa-thumbs-up me-1"></i>
                                <span class="number-of-likes">{{ $item->number_of_likes }}</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary comments-toggle">
                                <i class="far fa-comments me-1"></i>
                                {{ __('Comments') }}
                                (<span class="comment-count">{{ count($item->comments) }}</span>)
                            </button>
                        </div>

                        <div id="comments-{{ $item->id }}" class="cabinet-news-comments mt-3" style="display: none;">
                            @if($canComment)
                                <div class="input-group input-group-sm mb-3">
                                    <input type="hidden" name="news_id" value="{{ $item->id }}">
                                    <textarea name="comment" class="form-control" rows="2" required
                                              placeholder="{{ __('Comment') }}"></textarea>
                                    <button type="button" class="btn btn-secondary send-comment">{{ __('Send') }}</button>
                                </div>
                            @endif
                            <ul class="list-unstyled mb-0 news-comments-list">
                                @foreach($item->comments as $comment)
                                    <li class="d-flex gap-2 mb-3 cabinet-news-comment" id="comment-{{ $comment->id }}">
                                        <img class="rounded-circle flex-shrink-0 cabinet-news-comment__avatar"
                                             src="{{ $comment->user->image }}"
                                             alt="{{ $comment->user->name }}">
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <span class="small fw-semibold">
                                                    {{ $comment->user->name }}
                                                    @if($comment->user->isNewsCommentsBlocked())
                                                        <span class="badge text-bg-danger ms-1">{{ __('Comments blocked') }}</span>
                                                    @endif
                                                </span>
                                                <small class="text-secondary flex-shrink-0">
                                                    {{ $comment->created_at->diffForHumans() }}
                                                </small>
                                            </div>
                                            @if($comment->user_id === \Illuminate\Support\Facades\Auth::id() || $admin)
                                                <div class="float-end ms-2 d-flex flex-wrap gap-1 justify-content-end">
                                                    @if($admin && $comment->user_id !== \Illuminate\Support\Facades\Auth::id())
                                                        <button type="button"
                                                                class="btn btn-link btn-sm p-0 {{ $comment->user->isNewsCommentsBlocked() ? 'text-success' : 'text-danger' }} toggle-news-comment-block"
                                                                data-user-id="{{ $comment->user_id }}"
                                                                data-blocked="{{ $comment->user->isNewsCommentsBlocked() ? '1' : '0' }}"
                                                                title="{{ $comment->user->isNewsCommentsBlocked() ? __('Unblock comments') : __('Block comments (spam)') }}">
                                                            <i class="bi {{ $comment->user->isNewsCommentsBlocked() ? 'bi-unlock' : 'bi-slash-circle' }}"></i>
                                                        </button>
                                                    @endif
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary edit-comment-btn" title="{{ __('Edit') }}">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-link btn-sm p-0 text-secondary remove-comment"
                                                            data-comment-id="{{ $comment->id }}"
                                                            title="{{ __('Remove') }}">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            @endif
                                            <p class="mb-0 small cabinet-news-comment__text mt-1">{{ $comment->comment }}</p>
                                            <textarea rows="3"
                                                      class="form-control form-control-sm mt-1 d-none edit-comment-field"
                                                      data-comment-id="{{ $comment->id }}">{{ $comment->comment }}</textarea>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </article>

                <div class="modal fade" id="remove-news-{{ $item->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body">
                                <p class="mb-1">{{ __('Delete a news item') }}</p>
                                <p class="mb-0 text-secondary">{{ __('Are you sure?') }}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Back') }}</button>
                                <button type="button" class="btn btn-danger remove-news" data-bs-dismiss="modal"
                                        data-news-id="{{ $item->id }}">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endisset

    @slot('js')
        <script>
            (function ($) {
                var roleLabel = @json($admin ? __('Admin') : __('User'));

                $('.scroll_arrow').on('click', function (e) {
                    e.preventDefault();
                    var anchor = $(this).attr('href');
                    $('html, body').stop().animate({scrollTop: $(anchor).offset().top - 60}, 800);
                });

                $('.remove-news').on('click', function () {
                    var id = $(this).data('news-id');
                    $.post("{{ route('remove.news') }}", {
                        id: id,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, function () {
                        $('#news-' + id).remove();
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('overflow', '');
                    }, 'json');
                });

                $('.cabinet-news-feed').on('click', '.remove-comment', function () {
                    var $btn = $(this);
                    var id = $btn.data('comment-id');
                    var $post = $btn.closest('.cabinet-news-post');
                    $.post("{{ route('remove.comment') }}", {
                        id: id,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, function () {
                        $post.find('.comment-count').text(function (i, n) {
                            return Math.max(0, parseInt(n, 10) - 1);
                        });
                        $('#comment-' + id).remove();
                    }, 'json');
                });

                $('.cabinet-news-feed').on('click', '.like-news', function () {
                    var $btn = $(this);
                    var id = $btn.data('news-id');
                    $.post("{{ route('like') }}", {
                        id: id,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, function (response) {
                        var $count = $btn.find('.number-of-likes');
                        var n = parseInt($count.text(), 10) || 0;
                        if (response[0] === 'like') {
                            $count.text(n + 1);
                            $btn.addClass('text-danger');
                        } else {
                            $count.text(Math.max(0, n - 1));
                            $btn.removeClass('text-danger');
                        }
                    }, 'json');
                });

                $('.cabinet-news-feed').on('click', '.toggle-news-comment-block', function () {
                    var $btn = $(this);
                    var userId = $btn.data('user-id');
                    var blocked = String($btn.data('blocked')) === '1';
                    var url = blocked
                        ? @json(route('news.unblock-comment-user'))
                        : @json(route('news.block-comment-user'));
                    if (!blocked && !window.confirm(@json(__('Block this user from commenting on news?')))) {
                        return;
                    }
                    $.post(url, {
                        user_id: userId,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, function () {
                        location.reload();
                    }, 'json').fail(function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : @json(__('Error'));
                        alert(msg);
                    });
                });

                $('.cabinet-news-feed').on('click', '.send-comment', function () {
                    var $btn = $(this);
                    var $block = $btn.closest('.cabinet-news-comments');
                    var $post = $btn.closest('.cabinet-news-post');
                    var textarea = $block.find('textarea[name="comment"]');
                    var newsId = $block.find('input[name="news_id"]').val();
                    $.post("{{ route('create.comment') }}", {
                        news_id: newsId,
                        comment: textarea.val(),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, function (response) {
                        textarea.val('');
                        $post.find('.comment-count').text(function (i, n) {
                            return parseInt(n, 10) + 1;
                        });
                        $block.find('.news-comments-list').append(
                            '<li class="d-flex gap-2 mb-3 cabinet-news-comment" id="comment-' + response.commentId + '">' +
                            '<img class="rounded-circle flex-shrink-0 cabinet-news-comment__avatar" src="' + response.avatar + '" alt="">' +
                            '<div class="flex-grow-1 min-w-0">' +
                            '<div class="d-flex justify-content-between align-items-start gap-2">' +
                            '<span class="small fw-semibold">' + response.userName +
                            ' <span class="text-info fw-normal">' + roleLabel + '</span></span>' +
                            '<small class="text-secondary flex-shrink-0">{{ __('Just now') }}</small></div>' +
                            '<div class="float-end ms-2">' +
                            '<button type="button" class="btn btn-link btn-sm p-0 text-secondary edit-comment-btn"><i class="fa fa-edit"></i></button>' +
                            '<button type="button" class="btn btn-link btn-sm p-0 text-secondary remove-comment" data-comment-id="' + response.commentId + '">' +
                            '<i class="fas fa-times"></i></button></div>' +
                            '<p class="mb-0 small cabinet-news-comment__text mt-1">' + $('<div>').text(response.comment).html() + '</p>' +
                            '<textarea rows="3" class="form-control form-control-sm mt-1 d-none edit-comment-field" data-comment-id="' + response.commentId + '">' +
                            $('<div>').text(response.comment).html() + '</textarea></div></li>'
                        );
                    }, 'json').fail(function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : @json(__('Error'));
                        alert(msg);
                    });
                });

                function openCommentFromHash() {
                    var hash = window.location.hash;
                    if (!hash || hash.indexOf('comment-') !== 1) {
                        return;
                    }
                    var $comment = $(hash);
                    if (!$comment.length) {
                        return;
                    }
                    $comment.closest('.cabinet-news-comments').show();
                    $comment.addClass('cabinet-news-comment--highlight');
                    setTimeout(function () {
                        $comment[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                    }, 150);
                }

                openCommentFromHash();

                $('.cabinet-news-feed').on('click', '.comments-toggle', function () {
                    $(this).closest('.cabinet-news-post').find('.cabinet-news-comments').slideToggle(300);
                });

                $('.cabinet-news-feed').on('click', '.edit-comment-btn', function () {
                    var $li = $(this).closest('.cabinet-news-comment');
                    $li.find('.cabinet-news-comment__text').addClass('d-none');
                    $li.find('.edit-comment-field').removeClass('d-none').focus();
                });

                $('.cabinet-news-feed').on('blur', '.edit-comment-field', function () {
                    var $field = $(this);
                    var $li = $field.closest('.cabinet-news-comment');
                    var $text = $li.find('.cabinet-news-comment__text');
                    $.post("{{ route('edit.comment') }}", {
                        id: $field.data('comment-id'),
                        comment: $field.val(),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }, function () {
                        $text.text($field.val()).removeClass('d-none');
                        $field.addClass('d-none');
                    }, 'json');
                });
            })(jQuery);
        </script>
    @endslot
@endcomponent
