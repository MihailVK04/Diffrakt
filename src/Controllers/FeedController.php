<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Models\Post;

class FeedController {
    public function index(Request $request): void {
        $userId = $request->userId();
        $cursor = $request->input('cursor') ? (int)$request->input('cursor') : null;
        $limit = $request->input('limit') ? (int)$request->input('limit') : 10;

        $posts = Post::getFeed($userId, $cursor, $limit + 1);
        
        $nextCursor = null;
        if (count($posts) > $limit) {
            array_pop($posts);
            $nextCursor = (int)end($posts)['id'];
        }

        $formattedPosts = array_map(function($p) {
            return [
                'id' => $p['id'],
                'caption' => $p['caption'],
                'thumb_url' => '/api/v1/files?path=' . urlencode($p['processed_path'] ?? $p['thumb_path'] ?? ''),
                'author' => [
                    'username' => $p['username'] ?? '',
                    'avatar_url' => !empty($p['avatar_path']) ? '/api/v1/files?path=' . urlencode($p['avatar_path']) : null
                ],
                'created_at' => $p['created_at'],
                'like_count' => (int)($p['like_count'] ?? 0),
                'comment_count' => (int)($p['comment_count'] ?? 0),
                'user_reaction' => $p['user_reaction'] ?? null
            ];
        }, $posts);

        Response::json([
            'posts' => $formattedPosts,
            'next_cursor' => $nextCursor
        ]);
    }
}