<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

interface FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage;
}