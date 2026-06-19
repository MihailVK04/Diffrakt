<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class SaturationFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $level  = (float)\max(-100, \min(0, $params['level'] ?? -50));
        $factor = -$level / 100.0;

        $width  = \imagesx($image);
        $height = \imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = \imagecolorat($image, $x, $y);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8)  & 0xFF;
                $b   =  $rgb        & 0xFF;

                $lum  = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $newR = (int)\max(0, \min(255, $r + $factor * ($lum - $r)));
                $newG = (int)\max(0, \min(255, $g + $factor * ($lum - $g)));
                $newB = (int)\max(0, \min(255, $b + $factor * ($lum - $b)));

                \imagesetpixel($image, $x, $y, ($newR << 16) | ($newG << 8) | $newB);
            }
        }

        return $image;
    }
}