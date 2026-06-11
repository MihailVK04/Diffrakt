<?php
declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Database;
use Diffrakt\Models\Post;
use Diffrakt\Services\ImageService;
use Diffrakt\Services\PipelineRunner;
use Diffrakt\Services\StorageService;

class PostController {
    public function upload(Request $request): void {
        $uploadedFile = $request->file('image');
        if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            Response::badRequest('Image upload failed or missing.');
        }

        $caption = $request->input('caption') ?? '';

        try {
            $storage = new StorageService();
            $imageService = new ImageService($storage);
            
            $paths = $imageService->processUpload($uploadedFile);

            $postId = Post::create([
                'user_id' => $request->userId(),
                'original_path' => $paths['original'],
                'thumb_path' => $paths['thumb'],
                'caption' => $caption
            ]);

            Response::json([
                'message' => 'Post uploaded successfully',
                'id' => $postId,
                'thumb_url' => 'api/v1/files?path=' . urlencode($paths['thumb'])
            ], 201);

        } catch (\Exception $e) {
            Response::badRequest('Upload processing failed: ' . $e->getMessage());
        }
    }

    public function get(Request $request): void {
        $id = (int)($request->params['id'] ?? 0);
        $post = Post::findById($id);
        if (!$post) {
            Response::notFound('Post not found.');
        }

        $displayPath = $post['processed_path'] ?? $post['thumb_path'];
        $post['thumb_url'] = 'api/v1/files?path=' . urlencode($displayPath);
        if ($post['processed_path']) {
            $post['processed_url'] = 'api/v1/files?path=' . urlencode($post['processed_path']);
        }
        $post['original_url'] = 'api/v1/files?path=' . urlencode($post['original_path']);
        
        Response::json(['post' => $post]);
    }

    public function update(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        $caption = $request->input('caption');

        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }
        if ((int)$post['user_id'] !== $request->userId()) {
            Response::forbidden('Access denied.');
        }

        if ($caption !== null) {
            Database::getInstance()->execute(
                'UPDATE posts SET caption = ? WHERE id = ?', 
                [$caption, $postId]
            );
        }

        Response::json(['message' => 'Post updated.']);
    }

    public function delete(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }
        if ((int)$post['user_id'] !== $request->userId()) {
            Response::forbidden('Access denied.');
        }

        $storage = new StorageService();
        
        $storage->deleteFile($post['original_path']);
        $storage->deleteFile($post['thumb_path']);
        
        if ($post['processed_path']) {
            $storage->deleteFile($post['processed_path']);
        }

        Post::delete($postId, $request->userId());
        Response::json(['message' => 'Post deleted.']);
    }

    public function export(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);

        $data = $request->body() ?? [];
        $pipelineId = isset($data['pipeline_id']) ? (int)$data['pipeline_id'] : 0;

        if ($pipelineId === 0) {
            Response::badRequest('pipeline_id is required.');
        }

        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }
        if ((int)$post['user_id'] !== $request->userId()) {
            Response::forbidden('Access denied.');
        }

        try {
            $storage = new StorageService();

            if (!empty($post['processed_path'])) {
                $downloadPath = $post['processed_path'];
            } else {
                $runner = new PipelineRunner($storage);
                $downloadPath = $runner->run($post['original_path'], $pipelineId);
            }

            Response::json([
                'message' => 'Export complete.',
                'download_url' => 'api/v1/files?path=' . urlencode($downloadPath)
            ]);
        } catch (\Exception $e) {
            Response::badRequest('Export failed: ' . $e->getMessage());
        }
    }

    public function publish(Request $request): void {
        $postId = (int)($request->params['id'] ?? 0);
        $data = $request->body() ?? [];
        $pipelineId = (int)($data['pipeline_id'] ?? 0);

        if ($pipelineId === 0) {
            Response::badRequest('pipeline_id is required.');
        }

        $post = Post::findById($postId);
        if (!$post) {
            Response::notFound('Post not found.');
        }
        if ((int)$post['user_id'] !== $request->userId()) {
            Response::forbidden('Access denied.');
        }

        try {
            $storage = new StorageService();
            $runner = new PipelineRunner($storage);

            $sourcePath = !empty($post['processed_path']) ? $post['processed_path'] : $post['original_path'];
            $processedPath = $runner->run($sourcePath, $pipelineId);

            Database::getInstance()->execute(
                'UPDATE posts SET processed_path = ?, is_published = 1 WHERE id = ?',
                [$processedPath, $postId]
            );

            Response::json(['message' => 'Post published.']);
        } catch (\Exception $e) {
            Response::badRequest('Publish failed: ' . $e->getMessage());
        }
    }
}