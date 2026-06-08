<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Models\Pipeline;
use Diffrakt\Models\PipelineStep;
use Diffrakt\Models\Post;
use Diffrakt\Services\CycleDetector;
use Diffrakt\Services\StorageService;
use Diffrakt\Services\PipelineRunner;

class PipelineController {

    public function create(Request $request): void {
        $data = $request->body() ?? [];
        $errors = Validator::validate($data, ['name' => ['required', 'max_length:100']]);

        if (!empty($errors)) Response::unprocessable($errors);

        $id = Pipeline::create($request->userId(), $data['name'], $data['description'] ?? '');
        Response::json(['message' => 'Pipeline created.', 'id' => $id], 201);
    }

    public function get(Request $request): void {
        $id = (int)($request->params['id'] ?? 0);
        $pipeline = Pipeline::findById($id);
        
        if (!$pipeline) Response::notFound('Pipeline not found.');

        // ИДЕЯТА НА ДРУГИЯ AI: Слагаме try/catch, за да хванем грешката при depth > 5
        try {
            $pipeline['steps'] = PipelineStep::getFlattenedSteps($id);
            Response::json(['pipeline' => $pipeline]);
        } catch (\RuntimeException $e) {
            Response::badRequest($e->getMessage());
        }
    }

    public function replaceSteps(Request $request): void {
        $pipelineId = (int)($request->params['id'] ?? 0);
        $steps = $request->body()['steps'] ?? [];

        if (CycleDetector::hasCycle($pipelineId, $steps)) {
            Response::unprocessable(['steps' => 'Pipeline would create a cycle or exceed the maximum depth limit.']);
        }

        PipelineStep::replaceSteps($pipelineId, $steps);
        Response::json(['message' => 'Pipeline steps updated.']);
    }

    public function delete(Request $request): void {
        $id = (int)($request->params['id'] ?? 0);
        if (Pipeline::delete($id, $request->userId()) === 0) {
            Response::notFound('Pipeline not found or permission denied.');
        }
        Response::json(['message' => 'Pipeline deleted.']);
    }

    public function apply(Request $request): void {
        $pipelineId = (int)($request->params['id'] ?? 0);
        $data = $request->body() ?? [];
        $postId = isset($data['post_id']) ? (int)$data['post_id'] : 0;

        if ($postId === 0) Response::badRequest('Missing post_id in request body.');

        $post = Post::findById($postId);
        if (!$post) Response::notFound('Target post not found.');
        if ((int)$post['user_id'] !== $request->userId()) Response::forbidden('You do not own this post.');

        $pipeline = Pipeline::findById($pipelineId);
        if (!$pipeline) Response::notFound('Pipeline not found.');

        try {
            $runner = new PipelineRunner(new StorageService());
            $processedRelativePath = $runner->run($post['original_path'], $pipelineId);

            Response::json([
                'message' => 'Pipeline applied successfully.',
                'processed_path' => $processedRelativePath
            ]);
        } catch (\Exception $e) {
            Response::badRequest('Pipeline processing failed: ' . $e->getMessage());
        }
    }
}