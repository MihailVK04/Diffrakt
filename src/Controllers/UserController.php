<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Core\Database;
use Diffrakt\Models\User;
use Diffrakt\Models\Post;

class UserController {
    public function profile(Request $request): void {
        $username = $request->params['username'] ?? '';
        $user = User::findByUsername($username);
        
        if (!$user) {
            Response::notFound('User not found.');
        }

        $db = Database::getInstance();
        $isFollowing = false;
        
        if ($request->userId()) {
            $row = $db->fetchOne(
                'SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?',
                [$request->userId(), $user['id']]
            );
            $isFollowing = (bool)$row;
        }

        $followersCount = $db->fetchOne('SELECT COUNT(*) as count FROM follows WHERE followee_id = ?', [$user['id']])['count'] ?? 0;
        $followingCount = $db->fetchOne('SELECT COUNT(*) as count FROM follows WHERE follower_id = ?', [$user['id']])['count'] ?? 0;

        $user['is_following'] = $isFollowing;
        $user['follower_count'] = (int)$followersCount;
        $user['following_count'] = (int)$followingCount;

        $postCount = $db->fetchOne(
            'SELECT COUNT(*) as count FROM posts WHERE user_id = ?',
            [$user['id']]
        )['count'] ?? 0;
        $user['post_count'] = (int)$postCount;

        Response::json(['user' => $user]);
    }

    public function posts(Request $request): void {
        $username = $request->params['username'] ?? '';
        $user = User::findByUsername($username);

        if (!$user) {
            Response::notFound('User not found.');
        }

        $cursor = $request->input('cursor') ? (int)$request->input('cursor') : null;
        $limit = $request->input('limit') ? (int)$request->input('limit') : 10;

        $posts = Post::getUserPosts((int)$user['id'], $cursor, $limit);
        
        $nextCursor = null;
        if (count($posts) === $limit) {
            $nextCursor = (int)end($posts)['id'];
        }

        Response::json([
            'posts' => $posts,
            'next_cursor' => $nextCursor
        ]);
    }

    public function update(Request $request): void {
        $data = $request->body() ?? [];
        $errors = Validator::validate($data, [
            'bio' => ['max_length:500']
        ]);

        if (!empty($errors)) {
            Response::unprocessable($errors);
        }

        $db = Database::getInstance();

        if (isset($data['bio'])) {
            $db->execute('UPDATE users SET bio = ? WHERE id = ?', [$data['bio'], $request->userId()]);
        }

        // Handle avatar upload
        $avatarFile = $request->file('avatar');
        if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));
            $storage = new \Diffrakt\Services\StorageService();
            $avatarPath = $storage->storeUploadedFile($avatarFile, 'avatars', $ext);
            User::updateAvatar($request->userId(), $avatarPath);
        }

        // Return updated user so frontend can refresh avatar
        $user = User::findById($request->userId());
        $avatarUrl = null;
        if ($user['avatar_path']) {
            $avatarUrl = 'api/v1/files?path=' . urlencode($user['avatar_path']);
        }

        Response::json([
            'message' => 'Profile updated successfully.',
            'avatar_url' => $avatarUrl,
            'bio' => $user['bio'],
        ]);
    }

    public function follow(Request $request): void {
        $username = $request->params['username'] ?? '';
        $userToFollow = User::findByUsername($username);

        if (!$userToFollow) {
            Response::notFound('User not found.');
        }

        if ((int)$userToFollow['id'] === $request->userId()) {
            Response::badRequest('You cannot follow yourself.');
        }

        Database::getInstance()->execute(
            'INSERT IGNORE INTO follows (follower_id, followee_id) VALUES (?, ?)',
            [$request->userId(), $userToFollow['id']]
        );

        Response::json(['message' => 'Followed successfully.']);
    }

    public function unfollow(Request $request): void {
        $username = $request->params['username'] ?? '';
        $userToUnfollow = User::findByUsername($username);

        if (!$userToUnfollow) {
            Response::notFound('User not found.');
        }

        Database::getInstance()->execute(
            'DELETE FROM follows WHERE follower_id = ? AND followee_id = ?',
            [$request->userId(), $userToUnfollow['id']]
        );

        Response::json(['message' => 'Unfollowed successfully.']);
    }

    public function search(Request $request): void {
        $q = trim($_GET['q'] ?? '');

        if (mb_strlen($q) < 2) {
            Response::json(['users' => []]);
            return;
        }

        $q = mb_substr($q, 0, 50);

        $db = Database::getInstance();

        $rows = $db->fetchAll(
            'SELECT id, username, avatar_path, bio
            FROM users
            WHERE username LIKE ?
        ORDER BY username
            LIMIT 20',
            ['%' . $q . '%']
        );

        Response::json(['users' => $rows]);
    }
}