<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class GrayscaleFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        return $image;
    }
}