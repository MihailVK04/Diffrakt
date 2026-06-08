<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Database;
use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Models\User;
use Diffrakt\Models\Post;

class UserController {

    public function profile(Request $request): void {
        $user = User::findByUsername($request->params['username'] ?? '');
        if (!$user) Response::notFound('User not found.');

        $followers = Database::getInstance()->fetchOne('SELECT COUNT(*) as count FROM follows WHERE followed_id = ?', [$user['id']]);
        $user['followers'] = $followers['count'];
        Response::json(['user' => $user]);
    }

    public function posts(Request $request): void {
        $user = User::findByUsername($request->params['username'] ?? '');
        if (!$user) Response::notFound('User not found.');

        $cursor = $request->input('cursor');
        $posts = Post::getUserPosts((int)$user['id'], $cursor ? (int)$cursor : null);

        // ФИКС: Връщаме next_cursor за улеснение на фронтенда
        Response::json([
            'message' => 'User posts retrieved.', 
            'posts' => $posts,
            'next_cursor' => !empty($posts) ? end($posts)['id'] : null
        ]);
    }

    public function update(Request $request): void {
        $data = $request->body() ?? [];
        if (isset($data['email'])) User::updateEmail($request->userId(), $data['email']);
        Response::json(['message' => 'Profile updated.']);
    }

    public function follow(Request $request): void {
        $target = User::findByUsername($request->params['username'] ?? '');
        if (!$target) Response::notFound('Target user not found.');
        if ($request->userId() === (int)$target['id']) Response::badRequest('Cannot follow self.');

        Database::getInstance()->execute('INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)', [$request->userId(), $target['id']]);
        Response::json(['message' => "Following."]);
    }

    public function unfollow(Request $request): void {
        $target = User::findByUsername($request->params['username'] ?? '');
        if ($target) {
            Database::getInstance()->execute('DELETE FROM follows WHERE follower_id = ? AND followed_id = ?', [$request->userId(), $target['id']]);
        }
        Response::json(['message' => "Unfollowed."]);
    }
}