<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class SepiaFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $intensity = (float)($params['intensity'] ?? 1.0);
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $newR = min(255, (int)(($r * 0.393) + ($g * 0.769) + ($b * 0.189)));
                $newG = min(255, (int)(($r * 0.349) + ($g * 0.686) + ($b * 0.168)));
                $newB = min(255, (int)(($r * 0.272) + ($g * 0.534) + ($b * 0.131)));

                $finalR = (int)($r + ($newR - $r) * $intensity);
                $finalG = (int)($g + ($newG - $g) * $intensity);
                $finalB = (int)($b + ($newB - $b) * $intensity);

                $color = ($finalR << 16) | ($finalG << 8) | ($finalB);
                imagesetpixel($image, $x, $y, $color);
            }
        }
        
        return $image;
    }
}