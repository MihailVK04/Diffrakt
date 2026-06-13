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
                'thumb_url' => (new \Diffrakt\Services\StorageService())->url($p['processed_path'] ?? $p['thumb_path'] ?? ''),
                'author' => [
                    'username' => $p['username'] ?? '',
                    'avatar_url' => !empty($p['avatar_path']) ? (new \Diffrakt\Services\StorageService())->url($p['avatar_path']) : null
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