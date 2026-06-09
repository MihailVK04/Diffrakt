<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class VignetteFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $strength = (float)($params['strength'] ?? 0.5);
        $width = imagesx($image);
        $height = imagesy($image);
        
        $cx = $width / 2;
        $cy = $height / 2;
        $maxDist = sqrt($cx * $cx + $cy * $cy);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $dist = sqrt(pow($x - $cx, 2) + pow($y - $cy, 2));
                $factor = 1.0 - ($dist / $maxDist) * $strength;
                $factor = max(0, $factor);

                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $newR = (int)($r * $factor);
                $newG = (int)($g * $factor);
                $newB = (int)($b * $factor);

                $color = ($newR << 16) | ($newG << 8) | $newB;
                imagesetpixel($image, $x, $y, $color);
            }
        }
        
        return $image;
    }
}