<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

class ContrastFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $level = -(max(-255, min(255, (int)($params['level'] ?? 20))));
        imagefilter($image, IMG_FILTER_CONTRAST, $level);
        return $image;
    }
}