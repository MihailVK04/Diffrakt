<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Models\Post;

class FeedController {
    public function index(Request $request): void {
        $userId = $request->userId();
        $cursor = $request->query('cursor') ? (int)$request->query('cursor') : null;
        $limit = $request->query('limit') ? (int)$request->query('limit') : 10;
        $scope = $request->query('scope') === 'all' ? 'all' : 'following';

        $posts = Post::getFeed($userId, $cursor, $limit + 1, $scope);

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
                'created_at' => $p['created_at']
            ];
        }, $posts);

        Response::json([
            'posts' => $formattedPosts,
            'next_cursor' => $nextCursor
        ]);
    }
}