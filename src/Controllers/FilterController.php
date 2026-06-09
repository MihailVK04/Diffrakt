<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Models\Filter;

class FilterController {

    public function list(Request $request): void {
        $filters = Filter::findAllPublicOrOwned($request->userId());
        Response::json(['filters' => $filters]);
    }

    public function get(Request $request): void {
        $id = (int)($request->params['id'] ?? 0);
        $filter = Filter::findById($id);
        if (!$filter) {
            Response::notFound('Filter not found.');
        }
        Response::json(['filter' => $filter]);
    }

    public function create(Request $request): void {
        $data = $request->body() ?? [];
        
        if (empty($data['name'])) {
            Response::badRequest('Filter name is required.');
        }
        if (empty($data['pipeline_id'])) {
            Response::badRequest('pipeline_id is required to create a composite filter.');
        }

        $filterId = Filter::createComposite(
            $request->userId(), 
            $data['name'], 
            (int)$data['pipeline_id']
        );

        Response::json(['message' => 'Filter created', 'id' => $filterId], 201);
    }

    public function delete(Request $request): void {
        $id = (int)($request->params['id'] ?? 0);
        if (Filter::delete($id, $request->userId()) === 0) {
            Response::notFound('Filter not found, not a composite, or permission denied.');
        }
        Response::json(['message' => 'Filter deleted.']);
    }
}