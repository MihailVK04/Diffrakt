<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class ContrastFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        // Clamping и обръщане на знака заради спецификите на GD библиотеката
        $level = isset($params['level']) ? max(-255, min(255, (int)$params['level'])) : 20;
        $level = -$level; 
        
        imagefilter($image, IMG_FILTER_CONTRAST, $level);
    }
}