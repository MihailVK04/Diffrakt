<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class EdgeDetectFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        imagefilter($image, IMG_FILTER_EDGEDETECT);
        return $image;
    }
}