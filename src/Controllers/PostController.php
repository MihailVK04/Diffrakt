<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Services\ImageService;
use Diffrakt\Services\StorageService;
use Diffrakt\Models\Post;

class PostController {
    private StorageService $storage;
    private ImageService $imageService;

    public function __construct() {
        $this->storage = new StorageService();
        $this->imageService = new ImageService($this->storage);
    }

    public function upload(Request $request): void {
        $file = $request->file('image');
        if (!$file) Response::badRequest('No image provided.');

        $caption = (string) $request->input('caption', '');

        try {
            $paths = $this->imageService->processUpload($file);
            $postId = Post::create([
                'user_id' => $request->userId(),
                'original_path' => $paths['original'],
                'thumb_path' => $paths['thumb'],
                'caption' => $caption
            ]);
            
            // ФИКС: Изрично задаваме ключовете, за да не счупим фронтенда на Мишо
            Response::json([
                'post' => [
                    'id'            => $postId,
                    'original_path' => $paths['original'],
                    'thumb_path'    => $paths['thumb'],
                    'caption'       => $caption
                ]
            ], 201);
        } catch (\Exception $e) {
            Response::badRequest($e->getMessage());
        }
    }

    public function get(Request $request): void {
        $post = Post::findById((int)($request->params['id'] ?? 0));
        if (!$post) Response::notFound('Post not found.');
        Response::json(['post' => $post]);
    }

    public function update(Request $request): void {
        Response::json(['message' => 'Update post endpoint is pending.']);
    }

    public function delete(Request $request): void {
        if (Post::delete((int)($request->params['id'] ?? 0), $request->userId()) === 0) {
            Response::notFound('Post not found or access denied.');
        }
        Response::json(['message' => 'Deleted.']);
    }

    public function export(Request $request): void {
        Response::json(['message' => 'Export post endpoint is pending.']);
    }
}