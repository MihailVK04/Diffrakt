<?php
declare(strict_types=1);

namespace Diffrakt\Filters;

class EdgeDetectFilter implements FilterInterface {
    public function apply(\GdImage $image, array $params): \GdImage {
        $width  = \imagesx($image);
        $height = \imagesy($image);

        $sobelX = [-1, 0, 1, -2, 0, 2, -1, 0, 1];
        $sobelY = [-1, -2, -1, 0, 0, 0, 1, 2, 1];

        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = \imagecolorat($image, $x, $y);
                $pixels[$y][$x] = [
                    ($rgb >> 16) & 0xFF,
                    ($rgb >> 8)  & 0xFF,
                     $rgb        & 0xFF,
                ];
            }
        }

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $gx = 0.0;
                $gy = 0.0;

                for ($ky = -1; $ky <= 1; $ky++) {
                    for ($kx = -1; $kx <= 1; $kx++) {
                        [$r, $g, $b] = $pixels[$y + $ky][$x + $kx];
                        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                        $ki  = ($ky + 1) * 3 + ($kx + 1);
                        $gx += $lum * $sobelX[$ki];
                        $gy += $lum * $sobelY[$ki];
                    }
                }

                $mag = (int)\min(255, \sqrt($gx * $gx + $gy * $gy));
                \imagesetpixel($image, $x, $y, ($mag << 16) | ($mag << 8) | $mag);
            }
        }

        return $image;
    }
}