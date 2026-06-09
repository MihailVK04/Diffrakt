<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

class BrightnessFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $level = max(-255, min(255, (int)($params['level'] ?? 20)));
        imagefilter($image, IMG_FILTER_BRIGHTNESS, $level);
        return $image;
    }
}