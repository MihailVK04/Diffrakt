<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class HueRotateFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $angle = isset($params['angle']) ? (int)$params['angle'] % 360 : 90;
        if ($angle < 0) $angle += 360;

        // БЪГ ФИКС: Ако ъгълът е 0, няма ротация (Identity transform)
        if ($angle === 0) {
            return;
        }

        $r = 0; $g = 0; $b = 0;
        
        if ($angle < 120) {
            $r = 255 - (int)(($angle / 120) * 255);
            $g = (int)(($angle / 120) * 255);
        } elseif ($angle < 240) {
            $angle -= 120;
            $g = 255 - (int)(($angle / 120) * 255);
            $b = (int)(($angle / 120) * 255);
        } else {
            $angle -= 240;
            $b = 255 - (int)(($angle / 120) * 255);
            $r = (int)(($angle / 120) * 255);
        }

        // Забележка: Това е апроксимация, а не същинска HSL ротация.
        imagefilter($image, IMG_FILTER_COLORIZE, $r, $g, $b, 50);
    }
}