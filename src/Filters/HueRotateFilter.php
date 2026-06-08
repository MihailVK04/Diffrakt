<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class HueRotateFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $angle = (float)($params['angle'] ?? 0.0);
        $width = imagesx($image);
        $height = imagesy($image);
        
        $cosA = cos(deg2rad($angle));
        $sinA = sin(deg2rad($angle));
        
        $matrix = [
            0.213 + $cosA * 0.787 - $sinA * 0.213, 0.715 - $cosA * 0.715 - $sinA * 0.715, 0.072 - $cosA * 0.072 + $sinA * 0.928,
            0.213 - $cosA * 0.213 + $sinA * 0.143, 0.715 + $cosA * 0.285 + $sinA * 0.140, 0.072 - $cosA * 0.072 - $sinA * 0.283,
            0.213 - $cosA * 0.213 - $sinA * 0.787, 0.715 - $cosA * 0.715 + $sinA * 0.715, 0.072 + $cosA * 0.928 + $sinA * 0.072
        ];

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $newR = max(0, min(255, (int)($r * $matrix[0] + $g * $matrix[1] + $b * $matrix[2])));
                $newG = max(0, min(255, (int)($r * $matrix[3] + $g * $matrix[4] + $b * $matrix[5])));
                $newB = max(0, min(255, (int)($r * $matrix[6] + $g * $matrix[7] + $b * $matrix[8])));

                $color = ($newR << 16) | ($newG << 8) | $newB;
                imagesetpixel($image, $x, $y, $color);
            }
        }
        
        return $image;
    }
}