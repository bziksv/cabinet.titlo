<?php

namespace App\ViewComposers;

use App\Support\NewsBadge;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CountUnreadNewsComposer
{
    public function compose(View $view): void
    {
        if (! Auth::check()) {
            $view->with([
                'count' => 0,
                'newsCommentCount' => 0,
                'newsCommentUrl' => null,
                'newsCommentTitle' => '',
            ]);

            return;
        }

        $userId = (int) Auth::id();
        $comments = User::isUserAdmin()
            ? NewsBadge::unreadCommentsForAdmin($userId)
            : ['count' => 0, 'url' => null];

        $view->with([
            'count' => NewsBadge::unreadNewsCount($userId),
            'newsCommentCount' => $comments['count'],
            'newsCommentUrl' => $comments['url'],
            'newsCommentTitle' => $comments['count'] > 0
                ? __('New comments on news (:count)', ['count' => $comments['count']])
                : '',
        ]);
    }
}
