<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class BlurFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        return $image;
    }
}