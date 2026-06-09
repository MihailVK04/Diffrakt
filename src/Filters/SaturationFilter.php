<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class SaturationFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $level = (float)($params['level'] ?? 1.0); 
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;

                $newR = max(0, min(255, (int)($lum + ($r - $lum) * $level)));
                $newG = max(0, min(255, (int)($lum + ($g - $lum) * $level)));
                $newB = max(0, min(255, (int)($lum + ($b - $lum) * $level)));

                $color = ($newR << 16) | ($newG << 8) | $newB;
                imagesetpixel($image, $x, $y, $color);
            }
        }
        
        return $image; 
    }
}