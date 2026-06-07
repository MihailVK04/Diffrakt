<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class BrightnessFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        // Clamping (ограничаване) на нивото от -255 до 255
        $level = isset($params['level']) ? max(-255, min(255, (int)$params['level'])) : 20;
        imagefilter($image, IMG_FILTER_BRIGHTNESS, $level);
    }
}