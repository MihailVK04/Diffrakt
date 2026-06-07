<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class HueRotateFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $angle = isset($params['angle']) ? (float)$params['angle'] : 0.0;
        $angle = fmod($angle, 360.0);
        if ($angle < 0) $angle += 360.0;
        if ($angle === 0.0) return;

        $angleRad = deg2rad($angle);
        $cosA = cos($angleRad);
        $sinA = sin($angleRad);

        // Матрица за Hue Rotation (SVG стандарт)
        $m00 = 0.213 + $cosA * 0.787 - $sinA * 0.213;
        $m01 = 0.715 - $cosA * 0.715 - $sinA * 0.715;
        $m02 = 0.072 - $cosA * 0.072 + $sinA * 0.928;

        $m10 = 0.213 - $cosA * 0.213 + $sinA * 0.143;
        $m11 = 0.715 + $cosA * 0.285 + $sinA * 0.140;
        $m12 = 0.072 - $cosA * 0.072 - $sinA * 0.283;

        $m20 = 0.213 - $cosA * 0.213 - $sinA * 0.787;
        $m21 = 0.715 - $cosA * 0.715 + $sinA * 0.715;
        $m22 = 0.072 + $cosA * 0.928 + $sinA * 0.072;

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $newR = (int)($r * $m00 + $g * $m01 + $b * $m02);
                $newG = (int)($r * $m10 + $g * $m11 + $b * $m12);
                $newB = (int)($r * $m20 + $g * $m21 + $b * $m22);

                $newR = min(255, max(0, $newR));
                $newG = min(255, max(0, $newG));
                $newB = min(255, max(0, $newB));

                $color = imagecolorallocate($image, $newR, $newG, $newB);
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }
}