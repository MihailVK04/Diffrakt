<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class BrightnessFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $level  = (int)\max(-255, \min(255, $params['level'] ?? 20));
        $width  = \imagesx($image);
        $height = \imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb  = \imagecolorat($image, $x, $y);
                $newR = (int)\max(0, \min(255, (($rgb >> 16) & 0xFF) + $level));
                $newG = (int)\max(0, \min(255, (($rgb >> 8)  & 0xFF) + $level));
                $newB = (int)\max(0, \min(255, ( $rgb        & 0xFF) + $level));
                \imagesetpixel($image, $x, $y, ($newR << 16) | ($newG << 8) | $newB);
            }
        }

        return $image;
    }
}