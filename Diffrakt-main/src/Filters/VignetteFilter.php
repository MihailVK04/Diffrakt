<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class VignetteFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $width   = \imagesx($image);
        $height  = \imagesy($image);
        $cx      = $width  / 2;
        $cy      = $height / 2;
        $maxDist = \sqrt($cx * $cx + $cy * $cy);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $dx   = $x - $cx;
                $dy   = $y - $cy;
                $dist = \sqrt($dx * $dx + $dy * $dy) / $maxDist;

                $factor = \max(0.0, 1.0 - $dist * $dist * 1.5);

                $rgb  = \imagecolorat($image, $x, $y);
                $newR = (int)((($rgb >> 16) & 0xFF) * $factor);
                $newG = (int)((($rgb >> 8)  & 0xFF) * $factor);
                $newB = (int)(( $rgb        & 0xFF) * $factor);

                \imagesetpixel($image, $x, $y, ($newR << 16) | ($newG << 8) | $newB);
            }
        }

        return $image;
    }
}