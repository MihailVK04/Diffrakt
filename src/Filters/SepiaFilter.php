<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class SepiaFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        // GD няма директен "Sepia" филтър. 
        // Правим го като първо обезцветим снимката, а после я тонираме с кафяво-жълт нюанс.
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_COLORIZE, 90, 60, 30);
    }
}