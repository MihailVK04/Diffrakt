<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class SaturationFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $value = isset($params['value']) ? (float)$params['value'] : 1.0;
        if ($value === 1.0) return;

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Изчисляваме яркостта (Luminance) по W3C стандарт
                $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

                // Отдалечаваме или приближаваме цветовете до сивото ($lum)
                $newR = (int)($lum + ($r - $lum) * $value);
                $newG = (int)($lum + ($g - $lum) * $value);
                $newB = (int)($lum + ($b - $lum) * $value);

                $newR = min(255, max(0, $newR));
                $newG = min(255, max(0, $newG));
                $newB = min(255, max(0, $newB));

                $color = imagecolorallocate($image, $newR, $newG, $newB);
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }
}