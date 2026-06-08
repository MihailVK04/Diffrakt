<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Models\Post;

class FeedController {

    public function index(Request $request): void {
        $cursor = $request->input('cursor');
        $posts = Post::getFeed($request->userId(), $cursor ? (int)$cursor : null);

        Response::json([
            'message' => 'Feed retrieved successfully.',
            'feed' => $posts,
            'next_cursor' => !empty($posts) ? end($posts)['id'] : null
        ]);
    }
}