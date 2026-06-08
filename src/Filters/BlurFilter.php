<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class BlurFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        // Позволяваме на потребителя да избере колко пъти да се блърне (сила на ефекта)
        $intensity = isset($params['intensity']) ? max(1, min(50, (int)$params['intensity'])) : 1;
        for ($i = 0; $i < $intensity; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
    }
}