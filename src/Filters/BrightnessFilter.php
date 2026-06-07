<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class BrightnessFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        // Ниво: от -255 (напълно черно) до 255 (напълно бяло)
        $level = isset($params['level']) ? (int)$params['level'] : 20;
        imagefilter($image, IMG_FILTER_BRIGHTNESS, $level);
    }
}