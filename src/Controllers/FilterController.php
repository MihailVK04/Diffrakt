<?php

declare(strict_types=1);

namespace Diffrakt\Controllers;

use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Models\Filter;

class FilterController {

    public function list(Request $request): void {
        $filters = Filter::findAllPublicOrOwned($request->userId());
        Response::json(['filters' => $filters]);
    }

    public function get(Request $request): void {
        $id = $request->params['id'] ?? 0;
        $filter = Filter::findById((int)$id);

        if (!$filter) Response::notFound('Filter not found.');
        Response::json(['filter' => $filter]);
    }

    public function create(Request $request): void {
        $data = $request->body() ?? [];
        $errors = Validator::validate($data, ['name' => ['required', 'max_length:50']]);

        if (!empty($errors)) Response::unprocessable($errors);

        $id = Filter::createComposite([
            'name' => $data['name'],
            'owner_id' => $request->userId()
        ]);

        Response::json(['message' => 'Filter created.', 'id' => $id], 201);
    }

    public function delete(Request $request): void {
        $id = $request->params['id'] ?? 0;
        if (Filter::delete((int)$id, $request->userId()) === 0) {
            Response::notFound('Filter not found, already deleted, or you lack permission.');
        }

        Response::json(['message' => 'Filter deleted successfully.']);
    }
}