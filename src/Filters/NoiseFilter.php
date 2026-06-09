<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

class NoiseFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $intensity = max(1, min(100, (int)($params['intensity'] ?? 10)));
        $amount = $intensity / 100.0;
        
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                if (mt_rand() / mt_getrandmax() < $amount) {
                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $noise = mt_rand(-30, 30);
                    $newR = max(0, min(255, $r + $noise));
                    $newG = max(0, min(255, $g + $noise));
                    $newB = max(0, min(255, $b + $noise));

                    $color = ($newR << 16) | ($newG << 8) | $newB;
                    imagesetpixel($image, $x, $y, $color);
                }
            }
        }
        return $image;
    }
}