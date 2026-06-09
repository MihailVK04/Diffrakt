<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class BlurFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $passes = (int)\max(1, \min(50, $params['intensity'] ?? 5));

        for ($i = 0; $i < $passes; $i++) {
            \imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $image;
    }
}