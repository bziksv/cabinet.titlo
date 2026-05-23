<?php

namespace App\Http\Controllers;

use App\News;
use App\NewsComments;
use App\NewsLikes;
use App\NewsNotification;
use App\Support\NewsBadge;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use JavaScript;


class NewsController extends Controller
{
    /**
     * Без User::$with (pay, roles) — иначе на каждого автора/комментатора 2+ запроса к удалённой БД.
     */
    private function newsIndexRelations(): array
    {
        $userColumns = ['id', 'name', 'last_name', 'image', 'news_comments_blocked_at'];
        $userLoader = static function ($query) use ($userColumns) {
            $query->without(['pay', 'roles'])->select($userColumns);
        };

        $commentsLoader = static function ($query) use ($userLoader) {
            $query->with(['user' => $userLoader]);
            if (cabinet_skip_heavy_web()) {
                $query->latest('id')->limit(5);
            }
        };

        $likeLoader = static function ($query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        };

        return [
            'user' => $userLoader,
            'like' => $likeLoader,
            'comments' => $commentsLoader,
        ];
    }

    /**
     * @return array|Application|Factory|View|mixed
     */
    public function index()
    {
        NewsBadge::markNewsSeen((int) Auth::id());
        NewsBadge::markCommentsSeenForAdmin((int) Auth::id());

        $newsQuery = News::query()
            ->with($this->newsIndexRelations())
            ->orderByDesc('created_at');

        // Local + удалённая БД: не тянуть все новости и все комментарии.
        if (cabinet_skip_heavy_web()) {
            $news = $newsQuery->limit(15)->get();
        } else {
            $news = $newsQuery->get();
        }
        $admin = NewsController::isUserAdmin();
        if ($admin) {
            JavaScript::put([
                'role' => __('Admin'),
            ]);
        } else {
            JavaScript::put([
                'role' => __('User'),
            ]);
        }

        $canComment = Auth::user()->canCommentOnNews();

        return view('news.index', compact('news', 'admin', 'canComment'));
    }

    /**
     * @return array|Application|Factory|View|mixed
     */
    public function createView()
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        return view('news.create');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        $news = $request->all();
        $news['user_id'] = Auth::id();
        $news = new News($news);
        $news->save();

        flash()->overlay(__('The news was successfully created'), ' ')->success();

        return Redirect::back();
    }


    public function remove(Request $request)
    {
        News::destroy($request->id);

        return response([], 200);
    }

    /**
     * @param Request $request
     * @return Application|ResponseFactory|Response
     */
    public function storeComment(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->canCommentOnNews()) {
            return response(['message' => __('You are blocked from commenting on news.')], 403);
        }

        $request = $request->all();
        $request['user_id'] = Auth::id();
        $comment = new NewsComments($request);
        $comment->save();

        return response([
            'commentId' => $comment->id,
            'userName' => Auth::user()->name,
            'createdAt' => 'Только что',
            'avatar' => Auth::user()->image,
            'comment' => $comment->comment
        ], 200);
    }

    /**
     * @param Request $request
     * @return Application|ResponseFactory|Response
     */
    public function removeComment(Request $request)
    {
        NewsComments::destroy($request->id);

        return response([], 200);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function likeNews(Request $request)
    {
        $userId = Auth::id();
        $like = NewsLikes::where(['user_id' => $userId, 'news_id' => $request->id])->first();
        $news = News::where('id', '=', $request->id)->first();

        if (isset($like)) {
            $response = 'unlike';
            $like->delete();
            $news->number_of_likes--;
        } else {
            $response = 'like';
            $like = new NewsLikes(['user_id' => $userId, 'news_id' => $request->id]);
            $news->number_of_likes++;
            $like->save();
        }
        $news->save();

        return response([
            $response
        ], 200);
    }

    /**
     * @param $id
     * @return array|false|Application|Factory|View|mixed
     */
    public function editNewsView($id)
    {
        if (! User::isUserAdmin()) {
            return abort(403);
        }

        $news = News::query()->findOrFail($id);

        return view('news.edit', compact('news'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function editNews(Request $request): RedirectResponse
    {
        News::where('id', '=', $request->id)->update([
            'content' => $request['content']
        ]);

        return Redirect::route('news');
    }

    public function editComment(Request $request)
    {
        NewsComments::where('id', '=', $request->id)->update([
            'comment' => $request->comment
        ]);

        return response([], 200);
    }

    /**
     * @return bool
     */
    public static function isUserAdmin(): bool
    {
        return User::isUserAdmin();
    }

    /**
     * @return Application|ResponseFactory|Response
     */
    public static function calculateCountNewNews()
    {
        $userId = (int) Auth::id();
        $payload = [
            'count' => NewsBadge::unreadNewsCount($userId),
        ];

        if (User::isUserAdmin()) {
            $comments = NewsBadge::unreadCommentsForAdmin($userId);
            $payload['commentCount'] = $comments['count'];
            $payload['commentUrl'] = $comments['url'];
        }

        return response($payload, 200);
    }

    public function blockCommentUser(Request $request)
    {
        if (! User::isUserAdmin()) {
            return abort(403);
        }

        $target = User::findOrFail((int) $request->input('user_id'));
        if ($target->id === Auth::id()) {
            return response(['message' => __('You cannot block yourself.')], 422);
        }

        apply_global_team_permissions();
        $target->loadMissing('roles');
        if ($target->hasRole(['admin', 'Super Admin'])) {
            return response(['message' => __('Cannot block an administrator.')], 422);
        }

        $target->news_comments_blocked_at = Carbon::now();
        $target->save();

        return response(['blocked' => true], 200);
    }

    public function unblockCommentUser(Request $request)
    {
        if (! User::isUserAdmin()) {
            return abort(403);
        }

        $target = User::findOrFail((int) $request->input('user_id'));
        $target->news_comments_blocked_at = null;
        $target->save();

        return response(['blocked' => false], 200);
    }
}
