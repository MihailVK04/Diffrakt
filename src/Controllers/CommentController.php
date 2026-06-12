<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Models\Comment;
use Diffrakt\Models\Post;

class CommentController {

    public function listByPost(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        $userId = $request->userId();
        
        $cursor = $request->input('cursor') ? (int)$request->input('cursor') : null;
        $limit = $request->input('limit') ? (int)$request->input('limit') : 20;

        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }

        $commentsData = Comment::findByPost($postId, $cursor, $limit + 1, $userId);
        
        $hasMore = false;
        $nextCursor = null;
        if (count($commentsData) > $limit) {
            $hasMore = true;
            array_pop($commentsData);
        }
        
        if (count($commentsData) > 0) {
            $nextCursor = (int)end($commentsData)['id'];
        }

        $formattedComments = [];
        foreach ($commentsData as $c) {
            $formatted = $this->formatComment($c);
            $formatted['reply_count'] = (int)($c['reply_count'] ?? 0);
            
            $formatted['replies'] = $this->getRepliesRecursive((int)$c['id'], $userId);
            
            $formattedComments[] = $formatted;
        }

        Response::json([
            'comments' => $formattedComments,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore
        ]);
    }

    private function getRepliesRecursive(int $parentId, ?int $userId): array {
        $repliesData = Comment::findReplies($parentId, $userId, 100);
        $formattedReplies = [];
        
        foreach ($repliesData as $r) {
            $formatted = $this->formatComment($r);
            $formatted['replies'] = $this->getRepliesRecursive((int)$r['id'], $userId);
            $formattedReplies[] = $formatted;
        }
        
        return $formattedReplies;
    }

    public function create(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        $data = $request->body() ?? [];

        $errors = Validator::validate($data, [
            'body' => ['required', 'max_length:1000']
        ]);

        if (!empty($errors)) {
            Response::unprocessable($errors);
        }

        if (trim($data['body']) === '') {
            Response::unprocessable(['body' => 'Comment cannot be empty.']);
        }

        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }

        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

        if ($parentId !== null) {
            $parent = Comment::findById($parentId);
            if (!$parent || (int)$parent['post_id'] !== $postId) {
                Response::unprocessable(['parent_id' => 'Invalid parent comment.']);
            }
        }

        $commentId = Comment::create(
            $postId,
            $request->userId(),
            trim($data['body']),
            $parentId
        );

        $newCommentData = Comment::getCommentWithDetails($commentId, $request->userId());
        $formatted = $this->formatComment($newCommentData);
        if ($parentId === null) {
            $formatted['reply_count'] = 0;
            $formatted['replies'] = [];
        }

        Response::json($formatted, 201);
    }

    public function update(Request $request): void {
        $commentId = (int)($request->params['id'] ?? 0);
        $data = $request->body() ?? [];

        $errors = Validator::validate($data, [
            'body' => ['required', 'max_length:1000']
        ]);

        if (!empty($errors)) Response::unprocessable($errors);
        if (trim($data['body']) === '') Response::unprocessable(['body' => 'Comment cannot be empty.']);

        $comment = Comment::findById($commentId);
        if (!$comment) Response::notFound('Comment not found.');
        if ((int)$comment['user_id'] !== $request->userId()) Response::forbidden('Access denied.');

        Comment::update($commentId, trim($data['body']));
        
        $updatedData = Comment::getCommentWithDetails($commentId, $request->userId());
        Response::json($this->formatComment($updatedData));
    }

    public function delete(Request $request): void {
        $commentId = (int)($request->params['id'] ?? 0);
        
        $comment = Comment::findById($commentId);
        if (!$comment) Response::notFound('Comment not found.');

        $post = Post::findById((int)$comment['post_id']);
        $isCommentAuthor = (int)$comment['user_id'] === $request->userId();
        $isPostAuthor = $post && (int)$post['user_id'] === $request->userId();

        if (!$isCommentAuthor && !$isPostAuthor) Response::forbidden('Access denied.');

        Comment::delete($commentId);
        Response::json([], 204);
    }
    
    private function formatComment(array $c): array {
        return [
            'id' => (int)$c['id'],
            'post_id' => (int)$c['post_id'],
            'parent_id' => $c['parent_id'] !== null ? (int)$c['parent_id'] : null,
            'user' => [
                'id' => (int)$c['user_id'],
                'username' => $c['username'] ?? '',
                'avatar_path' => $c['avatar_path'] ?? null
            ],
            'body' => $c['body'],
            'like_count' => (int)($c['like_count'] ?? 0),
            'user_reaction' => $c['user_reaction'] ?? null,
            'created_at' => $c['created_at'],
            'updated_at' => $c['updated_at']
        ];
    }
}