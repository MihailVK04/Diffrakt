<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class GrayscaleFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
    }
}