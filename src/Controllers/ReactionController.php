<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Models\PostReaction;
use Diffrakt\Models\CommentReaction;
use Diffrakt\Models\Post;
use Diffrakt\Models\Comment;

class ReactionController {

    public function reactToPost(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        $post = Post::findById($postId);
        
        if (!$post) Response::notFound('Post not found.');

        if ((int)$post['user_id'] === $request->userId()) {
            Response::badRequest('You cannot react to your own post.');
        }
        $data = $request->body() ?? [];
        $reaction = $data['reaction'] ?? '';
        
        $errors = Validator::validate($data, [
            'reaction' => ['required']
        ]);

        if (!empty($errors)) {
            Response::unprocessable($errors);
        }

        $reaction = $data['reaction'];
        if ($reaction !== 'like') {
            Response::unprocessable(['reaction' => 'Invalid reaction type.']);
        }

        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }

        PostReaction::react($request->userId(), $postId, $reaction);
        Response::json(['message' => 'Reaction saved.']);
    }

    public function removePostReaction(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        
        PostReaction::remove($request->userId(), $postId);
        Response::json(['message' => 'Reaction removed.']);
    }

    public function reactToComment(Request $request): void {
        $commentId = (int)($request->params['id'] ?? 0);
        $data = $request->body() ?? [];
        
        $errors = Validator::validate($data, [
            'reaction' => ['required']
        ]);

        if (!empty($errors)) {
            Response::unprocessable($errors);
        }

        $reaction = $data['reaction'];
        if ($reaction !== 'like') {
            Response::badRequest('Invalid reaction type.');
        }

        $comment = Comment::findById($commentId);
        if (!$comment) {
            Response::notFound('Comment not found.');
        }

        CommentReaction::react($request->userId(), $commentId, $reaction);
        Response::json(['message' => 'Comment reaction saved.']);
    }

    public function removeCommentReaction(Request $request): void {
        $commentId = (int)($request->params['id'] ?? 0);
        
        CommentReaction::remove($request->userId(), $commentId);
        Response::json(['message' => 'Comment reaction removed.']);
    }
}