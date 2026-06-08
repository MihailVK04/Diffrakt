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

        Response::json(['user' => $user]);
    }

    public function posts(Request $request): void {
        $username = $request->params['username'] ?? '';
        $user = User::findByUsername($username);

        if (!$user) {
            Response::notFound('User not found.');
        }

        $cursor = isset($_GET['cursor']) ? (int)$_GET['cursor'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

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

        $avatarFile = $request->file('avatar');
        if ($avatarFile !== null) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime = mime_content_type($avatarFile['tmp_name']);

            if (!in_array($mime, $allowed, true)) {
                Response::unprocessable(['avatar' => 'Avatar must be a JPEG, PNG, WebP, or GIF.']);
            }

            if ($avatarFile['size'] > 5 * 1024 * 1024) {
                Response::unprocessable(['avatar' => 'Avatar must be under 5 MB.']);
            }

            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
            };

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $storagePath = ($_ENV['STORAGE_PATH'] ?? getenv('STORAGE_PATH') ?: ROOT_PATH . '/storage');
            $dest = $storagePath . '/avatars/' . $filename;

            if (!move_uploaded_file($avatarFile['tmp_name'], $dest)) {
                Response::serverError('Could not save avatar.');
            }

            $db->execute(
                'UPDATE users SET avatar_path = ? WHERE id = ?',
                ['/avatars/' . $filename, $request->userId()]
            );
        }
        
        if (isset($data['email'])) {
            $emailErrors = Validator::validate($data, ['email' => ['required', 'email']]);
            if (!empty($emailErrors)) {
                Response::unprocessable($emailErrors);
            }
            User::updateEmail($request->userId(), $data['email']);
        }

        Response::json(['message' => 'Profile updated successfully.']);
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
}